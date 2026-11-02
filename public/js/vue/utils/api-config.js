/**
 * Vue 应用配置 (Vue 版本专用新版)
 * 对应原 /js/app-config.js
 */
(function (global) {
    'use strict';

    global.VueApp = global.VueApp || {};

    // 判断是否为本地开发环境
    const isLocalDev = window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost';
    const hostname = window.location.hostname;
    const isOnlineEnv = hostname.endsWith('.jsss999.com') && !isLocalDev;
    const isAikf = /^aikf(?:-api)?\./.test(hostname);
    const isProduction = !isLocalDev && (/^9j[-.]?/.test(hostname) || /^aigy\./.test(hostname) || isAikf || isOnlineEnv);

    // 动态获取 API 地址
    let API_BASE;
    if (isLocalDev) {
        API_BASE = 'http://127.0.0.1:8787';
    } else if (/^9j[-.]?/.test(hostname)) {
        API_BASE = 'https://9j-api.jsss999.com';
    } else if (/^aigy\./.test(hostname)) {
        API_BASE = 'https://aigy-api.jsss999.com';
    } else if (isAikf) {
        API_BASE = `${window.location.protocol}//${window.location.hostname}`;
    } else if (isOnlineEnv) {
        API_BASE = 'https://aigy-api.jsss999.com';
    } else {
        API_BASE = `${window.location.protocol}//${window.location.hostname}:8787`;
    }

    // WebSocket 同理
    let WS_BASE;
    if (isLocalDev) {
        WS_BASE = 'ws://127.0.0.1:7272';
    } else if (/^9j[-.]?/.test(hostname)) {
        WS_BASE = 'wss://9j-api.jsss999.com/ws';
    } else if (/^aigy\./.test(hostname)) {
        WS_BASE = 'wss://aigy-api.jsss999.com/ws';
    } else if (isAikf) {
        WS_BASE = `${window.location.protocol === 'https:' ? 'wss' : 'ws'}://${window.location.hostname}/ws`;
    } else if (isOnlineEnv) {
        WS_BASE = 'wss://aigy-api.jsss999.com/ws';
    } else {
        WS_BASE = `ws://${window.location.hostname}:7272`;
    }

    global.VueApp.Config = {
        API_BASE,
        WS_BASE,
        isLocalDev,
        isProduction,
        // 辅助方法：拼接 API 完整路径
        apiUrl: (path) => API_BASE + (path.startsWith('/') ? path : '/' + path)
    };

    if (isLocalDev) {
        console.log('🔧 [Vue] Config loaded:', global.VueApp.Config);
    }

})(window);
