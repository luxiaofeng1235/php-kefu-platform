/**
 * 消息事件处理层 (Handler Layer)
 * 职责：处理消息事件、触发通知、业务副作用
 * 不包含状态管理，只负责事件响应
 */
(function (global) {
    'use strict';

    global.VueApp = global.VueApp || {};
    const { Tools, NotificationHelper } = global.VueApp;

    const Handler = {
        /**
         * 处理访客列表变化，检测上下线并触发通知
         * @param {Array} oldList 旧访客列表
         * @param {Array} newList 新访客列表
         * @param {Object} identity 当前用户身份
         */
        handleVisitorListChange(oldList, newList, identity) {
            // 仅客服端处理
            if (identity?.role !== 'admin') return;
            if (!NotificationHelper) return;

            const oldMap = new Map((oldList || []).map(v => [v.token, v]));
            const newMap = new Map((newList || []).map(v => [v.token, v]));

            // 检测新上线的访客
            newMap.forEach((newVisitor, token) => {
                const oldVisitor = oldMap.get(token);
                const isOnline = Number(newVisitor.is_online) === 1;
                const wasOnline = oldVisitor ? Number(oldVisitor.is_online) === 1 : false;

                // 新上线（之前不存在或之前离线）
                if (isOnline && !wasOnline) {
                    const visitorName = newVisitor.nickname || (Tools ? Tools.shortenToken(token) : token);
                    const isFirstTime = (newVisitor.visit_times || 1) === 1;
                    NotificationHelper.visitorOnline(visitorName, isFirstTime);
                }
            });

            // 检测离线的访客
            oldMap.forEach((oldVisitor, token) => {
                const newVisitor = newMap.get(token);
                const wasOnline = Number(oldVisitor.is_online) === 1;
                const isOnline = newVisitor ? Number(newVisitor.is_online) === 1 : false;

                // 已离线（之前在线，现在离线或不存在）
                if (wasOnline && !isOnline) {
                    const visitorName = oldVisitor.nickname || (Tools ? Tools.shortenToken(token) : token);
                    // 检查是否是当前客服已接入的访客
                    const isServed = Number(oldVisitor.served_by_admin) === Number(identity.admin_id);
                    NotificationHelper.visitorOffline(visitorName, isServed);
                }
            });
        },

        /**
         * 处理访客页面浏览事件
         * @param {Object} data 页面浏览数据
         * @param {Object} identity 当前用户身份
         * @param {Array} visitors 访客列表（用于查找访客名称）
         * @param {Array} servedVisitors 已接入访客列表
         */
        handlePageView(data, identity, visitors, servedVisitors) {
            // 仅客服端处理
            if (identity?.role !== 'admin') return;
            if (!NotificationHelper) return;

            const visitorToken = data.visitor_token || data.token || '';
            const pagePath = data.page_path || data.url || data.path || '';
            const pageTitle = data.page_title || data.title || '';

            if (!visitorToken) return;

            // 获取访客名称
            const visitor = (visitors || []).find(v => v.token === visitorToken)
                || (servedVisitors || []).find(v => v.token === visitorToken);
            const visitorName = visitor?.nickname || (Tools ? Tools.shortenToken(visitorToken) : visitorToken);

            // 触发页面浏览通知
            NotificationHelper.pageView(visitorName, pagePath, pageTitle);
        },

        /**
         * 处理新消息通知（提示音 + 未读数）
         * @param {Object} msg 消息对象
         * @param {string} key 会话 key
         * @param {Object} identity 当前用户身份
         * @param {Object} currentVisitor 当前选中的访客
         * @param {Array} visitors 访客列表
         * @param {Array} servedVisitors 已接入访客列表
         */
        handleMessageNotification(msg, key, identity, currentVisitor, visitors, servedVisitors) {
            // 客服端：只要是访客发来的消息就播放提示音
            if (identity?.role === 'admin') {
                const isFromVisitor = msg.from && msg.from.role === 'visitor';
                if (isFromVisitor) {
                    // 查找访客对象
                    const v = (servedVisitors || []).find(v => v.token === key)
                        || (visitors || []).find(v => v.token === key);

                    // 判断访客是否已被【当前客服】接入
                    const isServedByMe = v && v.served_by_admin &&
                        String(v.served_by_admin) === String(identity.admin_id);

                    // 播放提示音（当前客服已接入的访客不播放，避免干扰正常对话）
                    if (!isServedByMe && Tools && Tools.playNotificationSound) {
                        Tools.playNotificationSound();
                    }

                    // 更新未读数：只有当前客服未接入的访客才统计
                    // 当前客服已接入的访客不统计，避免挂断后刷新页面未读数又出现
                    if (!isServedByMe && (!currentVisitor || currentVisitor.token !== key)) {
                        if (v) {
                            v.unread_count = (v.unread_count || 0) + 1;
                        }
                    }
                }
            }

            // 访客端：收到客服消息播放提示音
            if (identity?.role === 'visitor') {
                const isFromAdmin = msg.from && msg.from.role === 'admin';
                if (isFromAdmin) {
                    if (Tools && Tools.playNotificationSound) {
                        Tools.playNotificationSound();
                    }
                }
            }
        }
    };

    global.VueApp.Handler = Handler;

})(window);
