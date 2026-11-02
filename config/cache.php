<?php
return [
    // 默认缓存驱动
    'default' => 'redis', // 如果想默认用文件缓存可以改为 'file'

    // 缓存驱动列表
    'stores' => [
        'file' => [
            'type' => 'file',
            'path' => runtime_path() . '/cache', // 文件缓存路径
        ],
        'redis' => [
            'type' => getenv('CACHE_TYPE') ?: 'redis', //缓存类型
            'host' => getenv('CACHE_REDIS_HOST') ?: '127.0.0.1',//对应的地址
            'port' => getenv('CACHE_REDIS_PORT') ?: 6379, //缓存端口
            'auth' => getenv('CACHE_REDIS_PASSWORD') ?: null, //redis密码设置
            'database' => getenv('CACHE_REDIS_SELECT') ?: 0, //默认选择的数据库
            'prefix' => getenv('CACHE_REDIS_PREFIX') ?: 'webman:cache:', //缓存的标记
        ],
    ],
];
