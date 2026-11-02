/**
 * WebSocket 通信层 (Socket Layer)
 * 负责连接管理、心跳、断线重连
 * 不包含业务逻辑，只抛出消息事件
 */
(function (global) {
    'use strict';

    global.VueApp = global.VueApp || {};
    const { Config } = global.VueApp;

    class SocketClient {
        constructor() {
            this.ws = null;
            this.heartbeatTimer = null;
            this.reconnectTimer = null;
            this.reconnectAttempts = 0;
            this.config = null;
            this.listeners = {
                message: [],
                open: [],
                close: [],
                error: [],
                reconnecting: [],    // 新增：重连中事件
                reconnectFailed: []  // 新增：重连失败事件
            };
        }

        on(event, callback) {
            if (this.listeners[event]) {
                this.listeners[event].push(callback);
            }
        }

        /**
         * 移除事件监听器
         * @param {string} event 事件名
         * @param {Function} callback 回调函数
         */
        off(event, callback) {
            if (this.listeners[event]) {
                const idx = this.listeners[event].indexOf(callback);
                if (idx > -1) {
                    this.listeners[event].splice(idx, 1);
                }
            }
        }

        /**
         * 一次性监听（收到后自动移除）
         * @param {string} event 事件名
         * @param {Function} callback 回调函数
         */
        once(event, callback) {
            const wrapper = (data) => {
                this.off(event, wrapper);
                callback(data);
            };
            this.on(event, wrapper);
        }

        emit(event, data) {
            if (this.listeners[event]) {
                this.listeners[event].forEach(cb => cb(data));
            }
        }

        connect(identity, config = null) {
            if (config) this.config = config;
            if (this.ws && this.ws.readyState === WebSocket.OPEN) return;

            let endpoint = Config.WS_BASE;
            
            if (identity && identity.token) {
                const separator = endpoint.includes('?') ? '&' : '?';
                const params = new URLSearchParams();
                params.append('token', identity.token);
                params.append('role', identity.role || 'visitor');
                if (identity.admin_id) {
                    params.append('admin_id', identity.admin_id);
                }
                endpoint = `${endpoint}${separator}${params.toString()}`;
            }

            console.log('🔌 [Socket] Connecting to:', endpoint);
            this.ws = new WebSocket(endpoint);

            this.ws.onopen = () => {
                console.log('✅ [Socket] Connected');
                this.reconnectAttempts = 0;
                this.startHeartbeat();
                this.emit('open');
            };

            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.emit('message', data);
                } catch (e) {
                    console.error('Parse msg error:', e);
                }
            };

            this.ws.onclose = () => {
                this.stopHeartbeat();
                this.attemptReconnect(identity);
                this.emit('close');
            };

            this.ws.onerror = (e) => {
                console.error('[Socket] Error:', e);
                this.emit('error', e);
            };
        }

        send(payload) {
            if (!this.ws || this.ws.readyState !== WebSocket.OPEN) return false;
            this.ws.send(JSON.stringify(payload));
            return true;
        }

        startHeartbeat() {
            this.stopHeartbeat();
            // 优先使用配置的心跳间隔，默认 20 秒
            const seconds = (this.config && this.config.websocket_heartbeat_seconds) 
                ? parseInt(this.config.websocket_heartbeat_seconds) 
                : 20;
            const interval = (seconds > 0 ? seconds : 20) * 1000;

            this.heartbeatTimer = setInterval(() => {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    this.ws.send('{"type":"ping"}');
                }
            }, interval);
        }

        stopHeartbeat() {
            if (this.heartbeatTimer) clearInterval(this.heartbeatTimer);
        }

        attemptReconnect(identity) {
            const MAX_RECONNECT_ATTEMPTS = 10;

            if (this.reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) {
                // 达到最大重连次数，触发失败事件
                this.emit('reconnectFailed', {
                    attempts: this.reconnectAttempts,
                    maxAttempts: MAX_RECONNECT_ATTEMPTS
                });
                return;
            }

            this.reconnectAttempts++;
            const delay = Math.min(3000 * this.reconnectAttempts, 30000);

            // 触发重连中事件，通知 UI 更新
            this.emit('reconnecting', {
                attempt: this.reconnectAttempts,
                maxAttempts: MAX_RECONNECT_ATTEMPTS,
                delay: delay
            });

            this.reconnectTimer = setTimeout(() => this.connect(identity), delay);
        }
        
        close() {
            if (this.ws) this.ws.close();
        }
    }

    global.VueApp.Socket = new SocketClient();

})(window);
