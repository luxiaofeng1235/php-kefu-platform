<?php
/*
 * AI工业通用的业务数据配置
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file business.php
 */
return [
    //首页不同板块的列表配置
    'channel_list' => [
        ['name' => 'VR场景', 'value' => 'design-vr', 'path' => '/VR'],
        ['name' => '三维场景', 'value' => 'design-three-dimensional', 'path' => '/threeD'],
        ['name' => '三维定制-网站', 'value' => 'design-web', 'path' => '/pcWeb'],
        ['name' => '三维定制-小程序', 'value' => 'design-uniapp', 'path' => '/uniapp'],
        ['name' => '三维定制-app', 'value' => 'design-app', 'path' => '/app'],
        ['name' => '三维可视化', 'value' => 'design-visual', 'path' => '/visualization'],
        ['name' => '虚拟一体化', 'value' => 'design-integration', 'path' => '/virtualIntegration'],
        ['name' => 'AI平台', 'value' => 'design-platform', 'path' => '/aiCustom'],
        ['name' => 'AI工业园区', 'value' => 'design-industrial', 'path' => '/aiPark'],
        ['name' => '联系我们', 'value' => 'design-contact', 'path' => '/connectUs'],
        ['name' => '合伙人海报', 'value' => 'design-partner-banner', 'path' => '/cooperation'],
    ],
    //配置具体的名称
    'name' => 'AI 工业',

    // 客服聊天系统配置
    'chat' => [
        // 访客未接入警告时间（秒，用于访客列表红色警告提示）
        'visitor_unserved_warning_seconds' => 15,

        // 前端检测间隔（秒，每隔N秒检查一次未接入访客是否超时）
        'frontend_check_interval' => 3,

        // WebSocket 心跳间隔（秒，必须小于服务端 pingInterval 25秒）
        'websocket_heartbeat_seconds' => 20,
    ],

];
