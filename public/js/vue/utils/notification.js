/**
 * 通用通知工具类 (精简版)
 * 只保留访客下线、上线、页面浏览三个通知
 *
 * 使用方式:
 * NotificationHelper.visitorOffline('test_001', false);
 * NotificationHelper.visitorOnline('test_001', true);
 * NotificationHelper.pageView('test_001', '/home', '首页');
 */
(function(global) {
    'use strict';

    global.VueApp = global.VueApp || {};

    const { Tools } = global.VueApp;

    /**
     * 通知助手类
     */
    class NotificationHelper {
        /**
         * 显示通知的核心方法
         * @param {Object} options - 通知配置
         */
        static show(options) {
            const {
                title,
                message,
                type = 'info',
                duration = 4000,
                position = 'bottom-right',
                playSound = true,
                soundType = 'normal',
                showConsole = true,
                consoleMessage = null
            } = options;

            // 1. 显示 ElementPlus Notification
            if (window.ElementPlus && window.ElementPlus.ElNotification) {
                window.ElementPlus.ElNotification({
                    title,
                    message,
                    type,
                    position,
                    duration,
                    showClose: true,
                    dangerouslyUseHTMLString: false
                });
            } else {
                console.warn('ElementPlus.ElNotification 不可用，降级到 Console 输出');
            }

            // 2. 播放提示音
            if (playSound && Tools) {
                switch (soundType) {
                    case 'online':
                        Tools.playNotificationSound && Tools.playNotificationSound();
                        break;
                    case 'offline':
                        Tools.playOfflineSound && Tools.playOfflineSound();
                        break;
                    default:
                        Tools.playNotificationSound && Tools.playNotificationSound();
                        break;
                }
            }

            // 3. Console 日志
            if (showConsole) {
                const logMessage = consoleMessage || `${title}: ${message}`;

                switch (type) {
                    case 'success':
                        console.log(`✅ ${logMessage}`);
                        break;
                    case 'warning':
                        console.warn(`⚠️ ${logMessage}`);
                        break;
                    case 'error':
                        console.error(`❌ ${logMessage}`);
                        break;
                    case 'info':
                    default:
                        console.log(`ℹ️ ${logMessage}`);
                        break;
                }
            }
        }

        /**
         * 访客下线通知
         * @param {string} visitorName - 访客名称或 token
         * @param {boolean} isServed - 是否已接入访客
         */
        static visitorOffline(visitorName, isServed = false) {
            const now = new Date().toLocaleTimeString('zh-CN', { hour12: false });

            this.show({
                title: isServed ? '⚠️ 已接入访客离线' : '🔴 访客离线',
                message: `${visitorName} 于 ${now} 离线`,
                type: isServed ? 'warning' : 'info',
                duration: isServed ? 0 : 4000, // 已接入访客不自动关闭
                soundType: 'offline',
                consoleMessage: `🔴 访客离线: ${visitorName}`
            });
        }

        /**
         * 访客上线通知
         * @param {string} visitorName - 访客名称或 token
         * @param {boolean} isFirstTime - 是否首次上线
         */
        static visitorOnline(visitorName, isFirstTime = false) {
            const now = new Date().toLocaleTimeString('zh-CN', { hour12: false });

            this.show({
                title: isFirstTime ? '🟢 新访客上线' : '🟢 访客重新上线',
                message: `${visitorName} 于 ${now} 上线`,
                type: 'success',
                duration: 3000,
                soundType: 'online',
                consoleMessage: `🟢 访客上线: ${visitorName}`
            });
        }

        /**
         * 访客页面浏览通知
         * @param {string} visitorName - 访客名称或 token
         * @param {string} pagePath - 页面路径
         * @param {string} pageTitle - 页面标题
         */
        static pageView(visitorName, pagePath, pageTitle) {
            // 构建消息内容
            let message = '';
            if (pageTitle && pagePath && pagePath !== '/') {
                message = `📄 ${pageTitle}（${pagePath}）`;
            } else if (pageTitle) {
                message = `📄 ${pageTitle}`;
            } else if (pagePath && pagePath !== '/') {
                message = `📄 ${pagePath}`;
            } else {
                return; // 无效的页面信息，不显示通知
            }

            this.show({
                title: `${visitorName} 正在浏览`,
                message: message,
                type: 'info',
                duration: 3000,
                playSound: false, // 页面浏览不播放声音
                consoleMessage: `📄 访客页面浏览: ${visitorName} → ${message}`
            });
        }
    }

    // 导出到全局
    global.VueApp.NotificationHelper = NotificationHelper;

    // 调试用：暴露到 window
    if (global.VueApp.Config && global.VueApp.Config.isLocalDev) {
        global.NotificationHelper = NotificationHelper;
        console.log('🔔 NotificationHelper 已加载 (精简版)');
    }

})(window);
