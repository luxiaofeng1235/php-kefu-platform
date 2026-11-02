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
    'event_loop' => '',
    'stop_timeout' => 60, //从默认 5 改为 60 秒（单位：秒）
    'pid_file' => runtime_path() . '/webman.pid',
    'status_file' => runtime_path() . '/webman.status',
    'stdout_file' => runtime_path() . '/logs/stdout.log',
    'log_file' => runtime_path() . '/logs/workerman.log',
    'max_package_size' => 200  * 1024 * 1024 ,// 200MB（单位：字节）

    // 关键：加上这两行
    'max_request_size' => 200 * 1024 * 1024, // 单次 HTTP 请求体最大 200MB
    'upload_max_filesize' => 200 * 1024 * 1024, // 单个上传文件最大 200MB
];
