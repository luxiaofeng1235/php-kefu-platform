<?php
// config/.env.php
// 仅负责加载 .env 到环境变量（确保在配置加载前被引入）

if (defined('WEBMAN_ENV_LOADED')) {
    return [];
}
define('WEBMAN_ENV_LOADED', true);

// 定义 .env 文件路径（项目根目录的 .env）
$envFile = dirname(__DIR__) . '/.env';

// .env 不存在时直接跳过（避免线上仅靠系统环境变量时异常）
if (!is_file($envFile)) {
    return [];
}

if (!function_exists('putenv')) {
    throw new \RuntimeException('PHP禁用了putenv函数，无法注入环境变量');
}

// 读取 .env 文件内容
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
        continue;
    }

    // 分割键值对（只分割第一个等号，避免值里有等号）
    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    if ($key === '') {
        continue;
    }
    $value = trim($value);

    // 去掉值两端的单/双引号
    $value = trim($value, "\"'");

    // 注入到系统环境变量（仅当尚未设置时）
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
    }
}

return [];
