/**
 * API 接口层 (API Layer)
 * 负责所有的 HTTP 请求封装
 */
(function (global) {
    'use strict';

    global.VueApp = global.VueApp || {};
    const {Config} = global.VueApp;
    //定义主应用层的API的调用请求
    const Api = {
        async getConfig() {
            try {
                const res = await fetch(Config.apiUrl('api/visitor/config'));
                const json = await res.json();
                return json.code === 1 ? json.data : null;
            } catch (e) {
                console.error('❌ [API] Load config failed:', e);
                return null;
            }
        },

        async clearUnread(visitorToken, adminId) {
            if (!visitorToken || !adminId) return;
            try {
                await fetch(Config.apiUrl('api/visitor/read'), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `visitor_token=${encodeURIComponent(visitorToken)}&admin_id=${adminId}`
                });
            } catch (e) {
                console.warn('[API] Clear unread failed:', e);
            }
        },

        async fetchHistory(identity, visitorToken = null) {
            const urlParams = new URLSearchParams();
            urlParams.append('limit', 20);

            if (identity.role === 'visitor') {
                urlParams.append('role', 'visitor');
                urlParams.append('token', identity.token);
            } else if (identity.role === 'admin') {
                if (!visitorToken) return null;
                urlParams.append('role', 'admin');
                urlParams.append('visitor_token', visitorToken);
            } else {
                return null;
            }

            try {
                const apiUrl = Config.apiUrl('api/visitor/history');
                const res = await fetch(`${apiUrl}?${urlParams.toString()}`);
                const json = await res.json();

                if (json.code === 1 && json.data && Array.isArray(json.data.list)) {
                    return json.data.list;
                }
            } catch (e) {
                console.warn('[API] Fetch history failed:', e);
            }
            return [];
        }
    };

    global.VueApp.Api = Api;

})(window);
