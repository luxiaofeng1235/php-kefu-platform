/**
 * Vue 3 主应用 (重构版)
 * 整合所有组件，绑定 VueApp.Store
 */
(function() {
    'use strict';

    const { createApp, toRefs, onMounted, watch, computed, ref } = Vue;
    const { ElMessage, ElMessageBox, ElNotification, ElLoading } = ElementPlus;
    const { Store, Tools, Config } = VueApp;

    const app = createApp({
        components: {
            ChatHeader: window.ChatHeader,
            VisitorSidebar: window.VisitorSidebar,
            VisitorCard: window.VisitorCard,
            MessagePanel: window.MessagePanel,
            MessageItem: window.MessageItem,
            MessageInput: window.MessageInput,
            ServedSidebar: window.ServedSidebar,
            ServedCard: window.ServedCard,
            AdminActions: window.AdminActions
        },

        setup() {
            // 1. 使用 Store
            const store = Store;
            
            // 2. UI 状态
            const inputMessage = ref('');
            const filterOnlineOnly = ref(false);
            const filterTodayOnly = ref(false);
            const messageDisplayCount = ref(50); // 当前显示的消息数量

            // 3. 计算属性
            const currentMessages = computed(() => {
                const key = store.identity?.role === 'visitor' ? store.identity?.token : (store.currentVisitor?.token);
                if (!key || !store.conversations[key]) return [];

                const allMessages = store.conversations[key];

                // 【性能优化】只显示指定数量的消息
                if (allMessages.length > messageDisplayCount.value) {
                    // 返回最后 N 条
                    return allMessages.slice(-messageDisplayCount.value);
                }

                return allMessages;
            });

            // 是否有更多历史消息
            const hasMoreMessages = computed(() => {
                const key = store.identity?.role === 'visitor' ? store.identity?.token : (store.currentVisitor?.token);
                if (!key || !store.conversations[key]) return false;

                const allMessages = store.conversations[key];
                return allMessages.length > messageDisplayCount.value;
            });

            // 总消息数
            const totalMessageCount = computed(() => {
                const key = store.identity?.role === 'visitor' ? store.identity?.token : (store.currentVisitor?.token);
                if (!key || !store.conversations[key]) return 0;
                return store.conversations[key].length;
            });

            const currentVisitorToken = computed(() => store.currentVisitor?.token || '');

            // 4. 初始化逻辑
            onMounted(async () => {
                const loadingInstance = ElLoading.service({
                    lock: true,
                    text: '正在连接系统...',
                    background: 'rgba(255, 255, 255, 0.9)'
                });

                try {
                    const params = Tools.getUrlParams();
                    if (!params.token) {
                        ElMessage.error('缺少 token 参数');
                        loadingInstance.close();
                        return;
                    }

                    // ==================== 客服端登录验证 ====================
                    if (params.role === 'admin') {
                        // 1. 检查必要参数
                        if (!params.adminId) {
                            ElMessage.error('客服模式需要 admin_id');
                            loadingInstance.close();
                            return;
                        }

                        // 2. 调用后端接口验证登录状态
                        try {
                            const response = await fetch('/app/admin/user/profile', {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json'
                                },
                                credentials: 'include' // 携带 Cookie
                            });

                            const result = await response.json();

                            // 检查返回状态
                            if (result.code === 401) {
                                // 未登录，跳转到后台首页，并附带 redirect 参数
                                loadingInstance.close();
                                const currentUrl = encodeURIComponent(window.location.href);
                                window.location.href = `/app/admin?redirect=${currentUrl}`;
                                return;
                            }

                            if (result.code !== 0 || !result.data) {
                                // 其他错误
                                loadingInstance.close();
                                ElMessage.error('登录验证失败，请重新登录');
                                setTimeout(() => {
                                    const currentUrl = encodeURIComponent(window.location.href);
                                    window.location.href = `/app/admin?redirect=${currentUrl}`;
                                }, 1500);
                                return;
                            }

                            console.log('✅ 登录验证通过，用户:', result.data.nickname);

                        } catch (error) {
                            // 网络错误或其他异常
                            console.error('登录验证异常:', error);
                            loadingInstance.close();
                            ElMessage.error('无法验证登录状态，请检查网络连接');
                            setTimeout(() => {
                                const currentUrl = encodeURIComponent(window.location.href);
                                window.location.href = `/app/admin?redirect=${currentUrl}`;
                            }, 1500);
                            return;
                        }
                    }

                    // ==================== 构建身份信息 ====================
                    const identity = {
                        role: params.role,
                        token: params.token,
                        admin_id: params.adminId,
                        nickname: params.nickname
                    };

                    // ==================== 初始化 WebSocket 连接 ====================
                    await store.init(identity);
                    
                    // 等待连接成功或超时，提升体验
                    const startTime = Date.now();
                    const checkConnection = () => {
                        if (store.isConnected || Date.now() - startTime > 3000) {
                            loadingInstance.close();
                            console.log('🚀 Vue App Initialized');
                        } else {
                            setTimeout(checkConnection, 200);
                        }
                    };
                    checkConnection();

                } catch (e) {
                    console.error('Init Error:', e);
                    loadingInstance.close();
                    ElMessage.error('系统初始化失败');
                }
            });

            // ==================== UI 事件处理 ====================

            const handleSelectVisitor = (token) => {
                const v1 = store.visitors.find(v => v.token === token);
                const v2 = store.servedVisitors.find(v => v.token === token);
                const visitor = v1 || v2;

                if (visitor) {
                    // 切换访客时重置显示数量
                    messageDisplayCount.value = 50;
                    store.selectVisitor(visitor);
                }
            };

            // 加载更多历史消息
            const handleLoadMoreMessages = () => {
                // 每次加载 50 条
                messageDisplayCount.value += 50;
            };

            const handleSendMessage = (content) => {
                // 优先使用参数内容（来自 emit），如果没有则使用 model 值
                const msg = (typeof content === 'string' ? content : inputMessage.value).trim();
                
                if (!msg) return;
                
                store.sendChatMessage(msg);
                inputMessage.value = ''; // 清空输入框
            };

            const handleConnect = async () => {
                if (!store.currentVisitor) {
                    ElMessage.warning('请先选择访客');
                    return;
                }

                // 检查访客是否已被当前客服接入
                if (store.currentVisitor.served_by_admin === store.identity.admin_id) {
                    ElMessage.info('您已接入该访客，无需重复操作');
                    return;
                }

                // 检查访客是否已被其他客服接入
                if (store.currentVisitor.served_by_admin && store.currentVisitor.served_by_admin !== store.identity.admin_id) {
                    const servedAdminNickname = store.currentVisitor.served_by_admin_nickname || `客服ID:${store.currentVisitor.served_by_admin}`;
                    ElMessage.warning(`该访客已被 ${servedAdminNickname} 接入，您无法重复接入`);
                    return;
                }

                // 如果未被接入，尝试接入
                const loading = ElLoading.service({
                    lock: true,
                    text: '正在接入访客...',
                    background: 'rgba(255, 255, 255, 0.7)'
                });

                try {
                    const result = await store.acceptVisitor(store.currentVisitor.token);
                    if (result.success) {
                        ElMessage.success('接入成功');
                    } else {
                        ElMessage.error(result.error || '接入失败');
                    }
                } finally {
                    loading.close();
                }
            };

            const handleHangup = async (token) => {
                if (!token) return;
                try {
                    await ElMessageBox.confirm(`确认挂断访客 ${Tools.shortenToken(token)}?`);
                    await store.hangupVisitor(token);
                    ElMessage.success('已挂断');
                } catch(e) {}
            };
            
            const handleRelease = () => {
                handleHangup(store.currentVisitor?.token);
            };

            return {
                // 核心状态
                store,
                identity: computed(() => store.identity), // 使用 computed 确保响应式

                // 映射属性
                connectionStatus: computed(() => {
                    if (store.connectionError) {
                        return `🔴 ${store.connectionError}`;
                    }
                    if (store.isReconnecting && store.reconnectInfo) {
                        const { attempt, maxAttempts } = store.reconnectInfo;
                        return `🟠 重连中 (${attempt}/${maxAttempts})...`;
                    }
                    return store.isConnected ? '🟢 已连接' : '🟡 连接中...';
                }),
                clientId: computed(() => store.identity?.token || ''),
                wsUrl: Config.WS_BASE,

                visitorItems: computed(() => store.visitors),
                servedVisitors: computed(() => store.servedVisitors),
                currentVisitorToken,
                currentMessages,

                // 消息分页状态
                hasMoreMessages,
                totalMessageCount,

                // UI 状态
                inputMessage,
                filterOnlineOnly,
                filterTodayOnly,
                messageLoading: false,
                visitorListLoading: computed(() => store.visitorListLoading),
                servedVisitorTokens: computed(() => store.servedVisitors.map(v => v.token)),
                holdState: {},
                holdActionPending: false,
                visitorWarningThreshold: computed(() => (store.config?.visitor_unserved_warning_seconds || 15) * 1000),
                targetAdminId: null,
                quickReplies: [
                    '您好，请问有什么可以帮助您的？',
                    '好的，请稍等，我马上为您处理',
                    '感谢您的咨询，还有其他问题吗？',
                    '抱歉让您久等了',
                    '我已经收到您的信息，稍后会尽快回复您',
                    '请问您还在吗？',
                    '收到，感谢您的反馈',
                    '好的，明白了',
                    '如有其他问题，欢迎随时联系我们'
                ],

                // 方法
                handleSelectVisitor,
                handleSendMessage,
                handleLoadMoreMessages,
                handleConnect,
                handleRelease,
                handleHangup,
            };
        }
    });

    // 注册所有 Element Plus Icons
    const ElIcons = window.ElementPlusIconsVue;
    if (ElIcons) {
        for (const [key, component] of Object.entries(ElIcons)) {
            app.component(key, component);
        }
    }

    app.use(ElementPlus);
    app.mount('#app');

})();
