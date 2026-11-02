/**
 * Vue 核心状态管理 (Store) - 重构版
 * 职责：整合 Socket、API、Storage，管理响应式状态
 */
(function (global) {
    'use strict';

    global.VueApp = global.VueApp || {};
    const { Tools, Socket, Api, Storage, Handler } = global.VueApp;

    // 辅助去重缓存
    const selfMessageCache = new Map();
    const msgIdCache = new Map();

    // 创建 Reactive Store
    const store = Vue.reactive({
        // ==================== State (状态) ====================
        isConnected: false,
        connectionError: null,
        // 重连状态
        isReconnecting: false,
        reconnectInfo: null,  // { attempt, maxAttempts, delay }
        identity: null,
        config: null,
        
        visitors: [],
        servedVisitors: [],
        currentVisitor: null,
        visitorListLoading: false,
        
        offlineMessages: Storage.loadOfflineMessages(),
        conversations: Storage.loadChat(),

        // ==================== Computed ====================
        getMessages() {
            if (!this.identity) return [];
            const key = this.identity.role === 'visitor' ? this.identity.token : (this.currentVisitor?.token);
            return key ? (this.conversations[key] || []) : [];
        },

        // ==================== Init & Lifecycle ====================
        async init(identity) {
            this.identity = identity;
            
            // 绑定 Socket 事件
            this.bindSocketEvents();

            // 访客端数据迁移
            if (identity.role === 'visitor' && identity.token) {
                if (this.conversations['self'] && !this.conversations[identity.token]) {
                    this.conversations[identity.token] = this.conversations['self'];
                }
                if (this.conversations['self']) {
                    delete this.conversations['self'];
                    Storage.saveChat(this.conversations, true);
                }
                // 拉取历史
                this.doFetchHistory();
            }

            // 加载配置
            this.config = await Api.getConfig();

            // 连接
            Socket.connect(identity, this.config);
        },

        bindSocketEvents() {
            Socket.on('open', () => {
                this.isConnected = true;
                // 连接成功，清除重连状态
                this.isReconnecting = false;
                this.reconnectInfo = null;
                this.resendOfflineMessages();
            });

            Socket.on('close', () => {
                this.isConnected = false;
                this.visitorListLoading = false;
            });

            Socket.on('error', (e) => {
                // error 处理
            });

            Socket.on('message', (data) => {
                this.handleMessage(data);
            });

            // 重连中事件
            Socket.on('reconnecting', (info) => {
                this.isReconnecting = true;
                this.reconnectInfo = info;
                console.log(`🔄 [Store] 正在重连 (${info.attempt}/${info.maxAttempts})，${info.delay / 1000}秒后重试...`);
            });

            // 重连失败事件
            Socket.on('reconnectFailed', (info) => {
                this.isReconnecting = false;
                this.reconnectInfo = null;
                this.connectionError = `连接失败，已尝试 ${info.attempts} 次`;
                console.error(`❌ [Store] 重连失败，已达最大尝试次数 (${info.maxAttempts})`);
                // 显示错误提示
                if (window.ElementPlus && window.ElementPlus.ElMessage) {
                    window.ElementPlus.ElMessage.error({
                        message: '网络连接失败，请刷新页面重试',
                        duration: 0,
                        showClose: true
                    });
                }
            });
        },

        // ==================== Business Logic ====================
        
        // 核心消息处理分发
        handleMessage(data) {
            if (!data || !data.type) return;

            switch (data.type) {
                case 'error':
                    this.connectionError = data.message || '连接错误';
                    if (window.ElementPlus) {
                        window.ElementPlus.ElMessage.error(this.connectionError);
                    }
                    this.isConnected = false;
                    Socket.close();
                    break;

                case 'visitor_list':
                case 'visitor_sync':
                    this.updateVisitorList(data.data ? data.data.items : (data.items || []));
                    break;

                case 'served_sync':
                    this.updateServedList(data.items || []);
                    break;
                
                case 'visitor_page_view':
                    this.handlePageView(data);
                    break;

                case 'chat':
                case 'say':
                case 'say_to_one':
                case 'offline': // 离线回执也作为消息处理
                    // 统一适配
                    if (data.type === 'offline') {
                        // 离线消息特殊处理：模拟成一条系统提示或普通消息
                        // 如果有 msg_id，说明是对某条消息的确认
                        if (data.msg_id) {
                            data.is_self = true; // 视为回执
                        }
                    }
                    this.addMessage(data);
                    break;
            }
        },

        // 核心：添加消息 + 去重 + 保存
        addMessage(msg) {
            let key = '';
            if (this.identity.role === 'visitor') {
                key = this.identity.token;
            } else {
                // 客服端定位 key
                let targetToken = msg.target_token || msg.visitor_token;
                if (!targetToken) {
                    if (msg.from && msg.from.role === 'visitor') targetToken = msg.from.token;
                    else if (msg.to && msg.to.role === 'visitor') targetToken = msg.to.token;
                }
                if (!targetToken && this.currentVisitor) targetToken = this.currentVisitor.token;
                key = targetToken;
            }

            if (!key) return;
            if (!this.conversations[key]) this.conversations[key] = [];

            // 1. 去重逻辑 (简化版，复用之前逻辑)
            const list = this.conversations[key];
            const hasMsgId = !!msg.msg_id;
            
            // msg_id 去重
            const idCache = msgIdCache.get(key) || new Set();
            if (hasMsgId && idCache.has(msg.msg_id)) return; // 已存在

            if (hasMsgId) {
                idCache.add(msg.msg_id);
                // 防止内存泄漏：超过 500 条时保留最新 300 条
                if (idCache.size > 500) {
                    const arr = Array.from(idCache);
                    idCache.clear();
                    arr.slice(-300).forEach(id => idCache.add(id));
                }
                msgIdCache.set(key, idCache);
            }

            // 合并回执逻辑 (Local Echo Merge) - 改进版：支持并发消息合并
            let merged = false;
            
            // 只有当收到的消息是自己发的，且有 msg_id 时，才尝试去合并本地的 Pending 消息
            if ((msg.is_self || msg.is_me) && msg.msg_id) {
                // 倒序查找最近的 20 条消息（假设 Pending 消息一般在末尾）
                const searchLimit = 20;
                const len = list.length;
                const start = Math.max(0, len - searchLimit);
                
                for (let i = len - 1; i >= start; i--) {
                    const pendingMsg = list[i];
                    
                    // 只匹配本地没有 msg_id 的消息
                    if (pendingMsg.msg_id) continue;

                    // 匹配条件：内容相同 且 时间相近 (30秒内)
                    // ✅ 统一解析后再比较（处理 AI 消息 JSON 格式）
                    const pendingContent = Tools.parseAIMessageContent ? Tools.parseAIMessageContent(pendingMsg.content) : pendingMsg.content;
                    const incomingContent = Tools.parseAIMessageContent ? Tools.parseAIMessageContent(msg.content) : msg.content;
                    const sameContent = (pendingContent === incomingContent);
                    const timeDiff = Math.abs((msg.timestamp || Date.now()/1000) - (pendingMsg.timestamp || 0));
                    
                    if (sameContent && timeDiff < 30) {
                        // 找到匹配的本地消息，合并数据
                        Object.assign(pendingMsg, msg);
                        // 移除离线标记（如果存在）
                        if (pendingMsg.is_offline) delete pendingMsg.is_offline;
                        merged = true;
                        break; // 匹配一条即可
                    }
                }
            }

            if (!merged) {
                // 确保时间戳
                if (!msg.timestamp) msg.timestamp = Date.now() / 1000;
                list.push(msg);
            }

            // 保存
            Storage.saveChat(this.conversations);

            // 提示音 & 未读数
            this.handleNotification(msg, key);
        },

        handleNotification(msg, key) {
            // 委托给 Handler 处理
            if (Handler) {
                Handler.handleMessageNotification(
                    msg, key, this.identity, this.currentVisitor,
                    this.visitors, this.servedVisitors
                );
            }
        },

        // ==================== Actions ====================

        async doFetchHistory(visitorToken = null) {
            const list = await Api.fetchHistory(this.identity, visitorToken);
            if (!list || list.length === 0) return;

            // 归一化 + 合并去重
            const targetToken = visitorToken || this.identity.token;
            if (!this.conversations[targetToken]) this.conversations[targetToken] = [];
            const current = this.conversations[targetToken];

            // 简单去重：只加没有 ID 的
            const existIds = new Set(current.map(m => m.msg_id || m.id).filter(Boolean));
            
            const toAdd = [];
            list.forEach(raw => {
                // 转换结构
                let isMe = false;
                if (this.identity.role === 'visitor') isMe = (raw.from?.role === 'visitor');
                else isMe = (raw.from?.role === 'admin' && String(raw.from.admin_id) === String(this.identity.admin_id));

                const msg = {
                    id: raw.id, msg_id: raw.msg_id, content: raw.content, type: raw.type,
                    timestamp: raw.timestamp || (new Date(raw.created_at).getTime()/1000),
                    from: raw.from, to: raw.to, is_me: isMe, extra: raw.extra, _is_history: true
                };

                if (!existIds.has(msg.msg_id) && !existIds.has(msg.id)) {
                    toAdd.push(msg);
                }
            });

            if (toAdd.length > 0) {
                this.conversations[targetToken] = [...toAdd, ...current].sort((a,b) => a.timestamp - b.timestamp);
                Storage.saveChat(this.conversations);
                console.log(`📥 同步了 ${toAdd.length} 条历史`);
            }
        },

        selectVisitor(visitor) {
            this.currentVisitor = visitor;
            if (this.identity.role === 'admin' && visitor?.token) {
                this.doFetchHistory(visitor.token);
                // 清未读
                if (visitor.unread_count > 0) {
                    Api.clearUnread(visitor.token, this.identity.admin_id);
                    visitor.unread_count = 0;
                }
            }
        },

        sendChatMessage(content, isAi = false) {
             // ✅ 自动检测是否为 AI 消息（优先级：参数 > 内容判断）
             const isAIMessage = isAi || (Tools.isAIMessageJSON && Tools.isAIMessageJSON(content));

             const payload = {
                 type: 'say_to_one',
                 content: content,
                 scene: isAIMessage ? 'ai_assist' : undefined
             };

             let targetToken = '';
             if (this.identity.role === 'admin') {
                 if (!this.currentVisitor) return;
                 targetToken = this.currentVisitor.token;
                 payload.target_token = targetToken;
             } else {
                 payload.broadcast = true;
             }

             // 解析 AI 消息格式，用于本地回显
             let displayContent = content;
             let aiRole = null;

             // ✅ 使用公用函数解析 AI 消息
             if (Tools.isAIMessageJSON && Tools.isAIMessageJSON(content)) {
                 try {
                     const parsed = JSON.parse(content);
                     displayContent = parsed.content;
                     aiRole = parsed.role;
                 } catch (e) {
                     // JSON 解析失败，保持原内容
                 }
             }

             // 构造本地消息（本地回显用解析后的纯文本）
             const localMsg = {
                 ...payload,
                 type: 'chat',
                 timestamp: Date.now() / 1000,
                 is_me: true,
                 content: displayContent,  // 显示解析后的纯文本
                 msg_id: null, // 等回执
                 _local_id: Tools.generateUniqueId(), // 本地唯一ID，用于渲染 Key
                 from: { role: this.identity.role },
                 extra: aiRole ? { ai_role: aiRole } : {}  // AI 角色标签
             };

             if (Socket.send(payload)) {
                 // 发送成功，添加本地消息（显示解析后的纯文本）
                 this.addMessage(localMsg);
             } else {
                 // 离线处理
                 localMsg.is_offline = true;
                 this.offlineMessages.push(payload);
                 Storage.saveOfflineMessages(this.offlineMessages);
                 this.addMessage(localMsg);
             }
        },

        async resendOfflineMessages() {
            if (this.offlineMessages.length === 0) return;
            const queue = [...this.offlineMessages];
            this.offlineMessages = [];
            Storage.saveOfflineMessages([]);

            queue.forEach(payload => {
                if (!Socket.send(payload)) {
                    this.offlineMessages.push(payload);
                }
            });
            if (this.offlineMessages.length > 0) Storage.saveOfflineMessages(this.offlineMessages);
        },

        /**
         * 访客列表更新（diff 增量更新，保留本地状态）
         * @param {Array} list 新的访客列表
         */
        updateVisitorList(list) {
            if (!Array.isArray(list)) {
                this.visitorListLoading = false;
                return;
            }

            // 委托 Handler 处理上下线通知
            if (Handler) {
                Handler.handleVisitorListChange(this.visitors, list, this.identity);
            }

            // 构建新旧数据 Map
            const newMap = new Map(list.map(v => [v.token, v]));
            const oldMap = new Map(this.visitors.map(v => [v.token, v]));

            // 合并更新：保留本地状态（如 unread_count）
            const merged = list.map(newVisitor => {
                const oldVisitor = oldMap.get(newVisitor.token);
                if (oldVisitor) {
                    // 后端没有推送 unread_count 时，保留前端累加的值
                    const localUnread = oldVisitor.unread_count || 0;
                    const serverUnread = newVisitor.unread_count;

                    return {
                        ...oldVisitor,      // 保留旧数据
                        ...newVisitor,      // 覆盖服务端数据
                        // 未读数：服务端有值用服务端的，否则保留本地
                        unread_count: (serverUnread !== undefined && serverUnread !== null)
                            ? serverUnread
                            : localUnread
                    };
                }
                return newVisitor;
            });

            this.visitors = merged;
            this.visitorListLoading = false;
        },
        
        updateServedList(list) {
            this.servedVisitors = list;
            this.visitorListLoading = false;
        },

        /**
         * 处理访客页面浏览事件
         * @param {Object} data 页面浏览数据
         */
        handlePageView(data) {
            // 委托给 Handler 处理
            if (Handler) {
                Handler.handlePageView(data, this.identity, this.visitors, this.servedVisitors);
            }
        },
        
        // 接受/挂断操作
        async acceptVisitor(token) {
            return this.sendSocketRequest('hold_visitor', { visitor_token: token }, 'hold_result');
        },
        
        async hangupVisitor(token) {
            const res = await this.sendSocketRequest('release_visitor', { visitor_token: token }, 'release_result');
            return res.success;
        },

        /**
         * 通用 Socket 请求封装（带超时和响应匹配）
         * @param {string} type 消息类型
         * @param {object} data 请求数据
         * @param {string} responseType 期望的响应类型
         * @param {number} timeout 超时时间（毫秒）
         * @returns {Promise<object>}
         */
        sendSocketRequest(type, data, responseType, timeout = 5000) {
            return new Promise(resolve => {
                // 发送失败直接返回
                if (!Socket.send({ type, ...data })) {
                    return resolve({ success: false, error: '网络未连接' });
                }

                let resolved = false;
                let timeoutTimer = null;

                // 响应处理器
                const handler = (msg) => {
                    if (msg.type === responseType && !resolved) {
                        resolved = true;
                        if (timeoutTimer) clearTimeout(timeoutTimer);
                        Socket.off('message', handler);
                        resolve(msg);
                    }
                };

                // 注册监听
                Socket.on('message', handler);

                // 超时处理
                timeoutTimer = setTimeout(() => {
                    if (!resolved) {
                        resolved = true;
                        Socket.off('message', handler);
                        resolve({ success: false, error: '请求超时，请重试' });
                    }
                }, timeout);
            });
        }
    });

    // 自动保存
    window.addEventListener('beforeunload', () => {
        Storage.saveChat(store.conversations, true);
    });

    global.VueApp.Store = store;

})(window);