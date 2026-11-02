/**
 * Vue 工具函数库 (Vue 版本专用)
 * 对应原 /js/chat-utils.js
 */
(function(global) {
    'use strict';

    global.VueApp = global.VueApp || {};

    const Tools = {};

    /**
     * 判断字符串是否为 AI 消息 JSON 格式
     * @param {string} value - 要判断的字符串
     * @returns {boolean}
     */
    Tools.isAIMessageJSON = function(value) {
        // 1. 必须是字符串
        if (typeof value !== 'string') return false;

        const trimmed = value.trim();

        // 2. 必须是完整的 JSON 对象格式（快速过滤）
        if (!trimmed.startsWith('{') || !trimmed.endsWith('}')) {
            return false;
        }

        // 3. 必须包含 AI 消息的特征字段（避免无效解析）
        if (!trimmed.includes('"role"') || !trimmed.includes('"content"')) {
            return false;
        }

        // 4. 尝试解析并验证结构
        try {
            const parsed = JSON.parse(trimmed);

            // 5. 严格验证 AI 消息格式
            return (
                parsed &&
                typeof parsed === 'object' &&
                !Array.isArray(parsed) &&
                typeof parsed.role === 'string' &&
                (parsed.role === 'user' || parsed.role === 'assistant') &&
                'content' in parsed  // content 可以是任何类型（字符串、数字等）
            );
        } catch (e) {
            return false;
        }
    };

    /**
     * 解析 AI 消息内容，提取纯文本
     * 如果是 AI JSON 格式，返回 content 字段；否则返回原内容
     * @param {string} content - 消息内容
     * @returns {string} 解析后的纯文本内容
     */
    Tools.parseAIMessageContent = function(content) {
        if (!Tools.isAIMessageJSON(content)) {
            return content;
        }

        try {
            const parsed = JSON.parse(content.trim());
            return parsed.content;
        } catch (e) {
            return content;
        }
    };

    // 格式化时间
    Tools.formatTime = function(timestamp) {
        if (!timestamp) return '';
        return new Date(timestamp * 1000).toLocaleString('zh-CN', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false
        });
    };

    // 标准化日期值
    Tools.normalizeDateValue = function(value) {
        if (!value) return null;
        if (typeof value === 'number') return value > 1e12 ? value : value * 1000;
        if (typeof value === 'string') {
            const trimmed = value.trim();
            const maybeIso = trimmed.replace(' ', 'T');
            const parsedIso = Date.parse(maybeIso);
            if (Number.isFinite(parsedIso)) return parsedIso;
            const withZone = `${maybeIso}+08:00`;
            const parsedZone = Date.parse(withZone);
            if (Number.isFinite(parsedZone)) return parsedZone;
        }
        const parsed = Date.parse(value);
        return Number.isFinite(parsed) ? parsed : null;
    };

    // 格式化停留时长（从连接时间到现在）
    Tools.formatStayDuration = function(rawTime) {
        const connectedMs = Tools.normalizeDateValue(rawTime);
        if (!connectedMs) return '停留0秒';
        const now = Date.now();
        const durationMs = now - connectedMs;
        return Tools._formatDuration(durationMs);
    };

    // 格式化停留时长（从开始时间到结束时间，用于离线访客）
    Tools.formatStayDurationBetween = function(startTime, endTime) {
        const startMs = Tools.normalizeDateValue(startTime);
        const endMs = Tools.normalizeDateValue(endTime);
        if (!startMs || !endMs) return '停留0秒';
        const durationMs = endMs - startMs;
        return Tools._formatDuration(durationMs);
    };

    // 内部方法：格式化时长
    Tools._formatDuration = function(durationMs) {
        if (durationMs < 0) durationMs = 0;
        const seconds = Math.floor(durationMs / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);

        if (hours > 0) return `停留${hours}小时${minutes % 60}分钟`;
        if (minutes > 0) return `停留${minutes}分钟${seconds % 60}秒`;
        return `停留${seconds}秒`;
    };

    // 截断 Token
    Tools.shortenToken = function(token) {
        if (!token) return '';
        const clean = String(token);
        if (clean.length <= 10) return clean;
        // 32位token显示: 前6位...后6位 (共12个字符可见)
        return `${clean.slice(0, 6)}...${clean.slice(-6)}`;
    };

    // URL 参数解析
    Tools.getUrlParams = function() {
        const params = new URLSearchParams(window.location.search);
        return {
            role: (params.get('role') || 'visitor').toLowerCase(),
            token: params.get('token') || '',
            nickname: params.get('nickname') || '',
            adminId: params.get('admin_id') ? Number(params.get('admin_id')) : null,
            defaultAdminId: params.get('default_admin_id') ? Number(params.get('default_admin_id')) : null,
            targetVisitor: params.get('target_visitor') || '',
            targetAdmin: params.get('target_admin_id') || '',
            broadcast: params.get('broadcast')
        };
    };

    // 访客排序逻辑 (核心)
    Tools.sortVisitors = function(items, warningThreshold = 15000) {
        if (!Array.isArray(items)) return [];
        return items.slice().sort((a, b) => {
            const getWeight = (item) => {
                let weight = 0;
                // 1. 在线优先
                if (Number(item.is_online) === 1) weight += 10 ** 14;
                // 2. 超时未接入
                if (item.first_message_at && !item.served_by_admin && Number(item.is_online) === 1) {
                     const firstMsgTime = Tools.normalizeDateValue(item.first_message_at);
                     if (firstMsgTime && (Date.now() - firstMsgTime) >= warningThreshold) {
                         weight += 10 ** 13;
                     }
                }
                // 3. 未读消息
                if (Number(item.unread_count) > 0) weight += 10 ** 12;
                // 4. AI 消息
                if (item.last_message_scene === 'ai_assist') weight += 10 ** 11;
                
                // 5. 基础时间 (最后消息时间 或 连接时间)
                let baseTime = 0;
                const t = item.last_message_at || item.connected_at;
                if (t) baseTime = Tools.normalizeDateValue(t) || 0;
                weight += (baseTime / 1000);
                
                // 6. 首次访问微调
                if ((item.visit_count || 1) === 1) weight += 100;
                
                return weight;
            };
            const wA = getWeight(a), wB = getWeight(b);
            if (wA === wB) return (b.connected_at || 0) - (a.connected_at || 0);
            return wB - wA;
        });
    };

    // 共享 AudioContext（避免每次创建新实例）
    let sharedAudioContext = null;
    let audioContextReady = false;

    // 初始化 AudioContext（需要用户交互后调用）
    Tools.initAudioContext = function() {
        if (sharedAudioContext && audioContextReady) return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            sharedAudioContext = new AudioContext();
            if (sharedAudioContext.state === 'suspended') {
                sharedAudioContext.resume();
            }
            audioContextReady = true;
            console.log('🔊 AudioContext 已激活');
        } catch(e) {
            console.warn('AudioContext init failed', e);
        }
    };

    // 页面首次交互时自动激活音频
    const activateAudio = () => {
        Tools.initAudioContext();
        document.removeEventListener('click', activateAudio);
        document.removeEventListener('keydown', activateAudio);
    };
    document.addEventListener('click', activateAudio, { once: true });
    document.addEventListener('keydown', activateAudio, { once: true });

    // 音频通知 (简化版)
    Tools.playNotificationSound = function() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            // 复用或创建 AudioContext
            if (!sharedAudioContext) {
                sharedAudioContext = new AudioContext();
            }

            // 尝试恢复 suspended 状态
            if (sharedAudioContext.state === 'suspended') {
                sharedAudioContext.resume();
            }

            const ctx = sharedAudioContext;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 800;
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
            osc.start();
            osc.stop(ctx.currentTime + 0.2);
        } catch(e) {
            console.warn('Audio play failed', e);
        }
    };

    // 下线提示音 (低音调,区别于上线音)
    Tools.playOfflineSound = function() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            // 复用或创建 AudioContext
            if (!sharedAudioContext) {
                sharedAudioContext = new AudioContext();
            }

            // 尝试恢复 suspended 状态
            if (sharedAudioContext.state === 'suspended') {
                sharedAudioContext.resume();
            }

            const ctx = sharedAudioContext;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            // 使用低频率 (400Hz) 区别于上线音 (800Hz)
            osc.frequency.value = 400;
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch(e) {
            console.warn('Offline sound play failed', e);
        }
    };

    // 浏览器通知
    Tools.showBrowserNotification = function(title, body, duration = 3000) {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body,
                icon: '/favicon.ico',
                requireInteraction: false  // 允许自动关闭
            });

            // 3秒后自动关闭
            if (duration > 0) {
                setTimeout(() => notification.close(), duration);
            }
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    const notification = new Notification(title, {
                        body,
                        icon: '/favicon.ico',
                        requireInteraction: false
                    });
                    if (duration > 0) {
                        setTimeout(() => notification.close(), duration);
                    }
                }
            });
        }
    };

    // 生成全局唯一 msg_id（前端发送时使用）
    Tools.generateMsgId = function(identity = {}) {
        const ts = Date.now();
        const rand = Math.random().toString(36).slice(2, 8);
        const role = identity.role || 'unknown';
        const tokenPart = (identity.token || '').slice(-6) || 'anon';
        return `${role}-${tokenPart}-${ts}-${rand}`;
    };

    // 生成简单的唯一ID（用于本地消息的稳定渲染 key）
    // 格式: timestamp_randomString (例如: 1701234567890_k3x9j2p5a)
    Tools.generateUniqueId = function() {
        return `${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    };

    global.VueApp.Tools = Tools;

    // ==================== 共享时间源 (SharedClock) ====================
    // 解决每个 VisitorCard 组件都创建独立定时器的性能问题
    // 所有组件共享一个定时器，通过 Vue 的响应式系统更新
    const SharedClock = {
        _now: Vue.ref(Date.now()),
        _timer: null,
        _subscribers: 0,

        /**
         * 获取当前时间戳（响应式）
         * @returns {Ref<number>}
         */
        getNow() {
            return this._now;
        },

        /**
         * 订阅时钟更新（组件 mounted 时调用）
         */
        subscribe() {
            this._subscribers++;
            if (this._subscribers === 1 && !this._timer) {
                this._timer = setInterval(() => {
                    this._now.value = Date.now();
                }, 1000);
            }
        },

        /**
         * 取消订阅（组件 unmounted 时调用）
         */
        unsubscribe() {
            this._subscribers = Math.max(0, this._subscribers - 1);
            if (this._subscribers === 0 && this._timer) {
                clearInterval(this._timer);
                this._timer = null;
            }
        }
    };

    global.VueApp.SharedClock = SharedClock;

})(window);
