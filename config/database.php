<?php
return  [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => getenv('DB_TYPE') ?: 'mysql', //数据库类型
            'host'        => getenv('DB_MYSQL_HOST') ?: '127.0.0.1',//主机
            'port'        => getenv('DB_MYSQL_PORT') ?: '3306', //端口
            'database'    => getenv('DB_MYSQL_DATABASE') ?: '',//数据库名称
            'username'    => getenv('DB_MYSQL_USERNAME') ?: '',//用户名
            'password'    => getenv('DB_MYSQL_PASSWORD') ?: '',//密码
            'charset'     => getenv('DB_MYSQL_CHARSET') ?: 'utf8mb4', //字符集
            'collation'   => getenv('DB_MYSQL_COLLATION') ?: 'utf8mb4_general_ci',//collection设置
            'prefix'      => getenv('DB_MYSQL_PREFIX') ?: 'wa_',//表前缀
            'strict'      => true,
            'engine'      => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 1,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];
