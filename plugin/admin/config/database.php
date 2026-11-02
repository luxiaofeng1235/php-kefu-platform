<?php
return  [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => getenv('DB_MYSQL_HOST') ?: '127.0.0.1',
            'port'        => getenv('DB_MYSQL_PORT') ?: '3306',
            'database'    => getenv('DB_MYSQL_DATABASE') ?: '',
            'username'    => getenv('DB_MYSQL_USERNAME') ?: '',
            'password'    => getenv('DB_MYSQL_PASSWORD') ?: '',
            'charset'     => getenv('DB_MYSQL_CHARSET') ?: 'utf8mb4',
            'collation'   => getenv('DB_MYSQL_COLLATION') ?: 'utf8mb4_general_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
        ],
    ],
];
