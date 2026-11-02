/**
 * 本地存储管理 (Storage Layer)
 * 负责 LocalStorage 的读写、数据压缩/解压
 */
(function (global) {
    'use strict';

    global.VueApp = global.VueApp || {};

    const STORAGE_CONFIG = {
        MAX_VISITORS: 100,
        SAVE_DEBOUNCE_MS: 2000,
        AUTO_CLEANUP_DAYS: 30
    };

    let saveTimer = null;

    // ==================== 数据压缩/解压工具 (Codec) ====================
    const Codec = {
        compress(msg) {
            if (!msg) return null;
            const min = {
                c: msg.content || '',
                t: msg.timestamp || msg.time || Date.now() / 1000
            };
            if (msg.is_me || msg.is_self) min.s = 1;
            if (msg.msg_id) min.i = msg.msg_id;
            const type = (msg.type || 'chat').toLowerCase();
            if (type !== 'chat' && type !== 'say_to_one') min.ty = type;
            if (msg.is_offline) min.o = 1;
            if (msg.offline) min.ol = 1;
            if (msg.broadcast) min.bc = 1;
            if (msg.scene === 'ai_assist') min.ai = 1;
            return min;
        },

        decompress(min, token) {
            if (!min) return null;
            if (min.content !== undefined && min.c === undefined) return min;

            const msg = {
                content: min.c || '',
                timestamp: min.t || 0,
                type: min.ty || 'chat',
                msg_id: min.i,
                is_me: !!min.s,
                is_self: !!min.s,
                is_offline: !!min.o,
                offline: !!min.ol,
                broadcast: !!min.bc,
                scene: min.ai ? 'ai_assist' : undefined
            };

            if (msg.is_me) {
                msg.from = { role: 'me' };
            } else {
                msg.from = { role: 'other', token: token };
            }
            
            // 补充上下文 Token (用于渲染缺失的 from/to)
            msg.visitor_token = token;

            return msg;
        }
    };

    const Storage = {
        /**
         * 加载聊天记录
         */
        loadChat() {
            try {
                const startTime = performance.now();
                const saved = localStorage.getItem('chat_conversations');
                if (!saved) return {};

                const parsed = JSON.parse(saved);
                const decompressed = {};
                let messageCount = 0;

                Object.keys(parsed).forEach(token => {
                    const list = parsed[token];
                    if (Array.isArray(list)) {
                        decompressed[token] = list.map(m => Codec.decompress(m, token)).filter(Boolean);
                        messageCount += decompressed[token].length;
                    }
                });

                console.log(`✅ [Storage] 加载完毕: ${Object.keys(decompressed).length} 会话, ${messageCount} 消息`);
                return decompressed;
            } catch (e) {
                console.warn('⚠️ [Storage] 加载失败:', e);
                return {};
            }
        },

        /**
         * 保存聊天记录 (防抖 + 压缩)
         */
        saveChat(conversations, immediate = false) {
            const doSave = () => {
                try {
                    const now = Date.now();
                    const cleanupThreshold = now - (STORAGE_CONFIG.AUTO_CLEANUP_DAYS * 24 * 60 * 60 * 1000);
                    let cleaned = {};
                    const tokens = Object.keys(conversations);

                    tokens.forEach(token => {
                        const messages = conversations[token];
                        if (!Array.isArray(messages) || messages.length === 0) return;
                        
                        // 实际清理策略：
                        // 1. 保留最近 20 条 (保底，防止时间戳错误导致清空)
                        // 2. 其他消息必须在 30 天内
                        const len = messages.length;
                        cleaned[token] = messages.filter((m, index) => {
                            // 无论时间多久，始终保留最后 20 条
                            if (index >= len - 20) return true;
                            // 检查时间戳 (兼容秒级和毫秒级，简单判断: < 100亿为秒)
                            let ts = m.timestamp || m.time || 0;
                            if (ts < 10000000000) ts *= 1000; 
                            return ts > cleanupThreshold;
                        }).map(m => Codec.compress(m));
                    });

                    // 限制数量逻辑可在此处保留，略简写

                    const jsonStr = JSON.stringify(cleaned);
                    localStorage.setItem('chat_conversations', jsonStr);
                } catch (e) {
                    console.warn('⚠️ [Storage] 保存失败:', e);
                }
            };

            if (saveTimer) clearTimeout(saveTimer);
            if (immediate) {
                doSave();
            } else {
                saveTimer = setTimeout(doSave, STORAGE_CONFIG.SAVE_DEBOUNCE_MS);
            }
        },

        loadOfflineMessages() {
            try {
                const saved = localStorage.getItem('chat_offline_messages');
                return saved ? JSON.parse(saved) : [];
            } catch (e) { return []; }
        },

        saveOfflineMessages(messages) {
            try {
                localStorage.setItem('chat_offline_messages', JSON.stringify(messages));
            } catch (e) {}
        }
    };

    global.VueApp.Storage = Storage;

})(window);
