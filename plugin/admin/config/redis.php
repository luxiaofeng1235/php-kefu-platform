<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    'default' => [
        'host' => getenv('CACHE_REDIS_HOST'), //链接的主机
        'password' => getenv('CACHE_REDIS_PASSWORD'),//链接的密码
        'port' => getenv('CACHE_REDIS_PORT'),//链接的端口
        'database' => getenv('CACHE_REDIS_SELECT'),//默认选择的库
    ],
];
