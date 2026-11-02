<?php

$env = static function (string $key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    if (is_string($value)) {
        $value = trim($value, " \t\n\r\0\x0B\"'");
    }
    return $value === '' ? $default : $value;
};

return [
    'default' => $env('STORAGE_DRIVER', 'local'),
    'engine' => [
        'local' => [
            'root' => public_path(),
            'url' => $env('LOCAL_STORAGE_URL', '/uploads'),
        ],
        'aliyun' => [
            'access_key_id' => $env('OSS_ACCESS_KEY_ID', ''),
            'access_key_secret' => $env('OSS_ACCESS_KEY_SECRET', ''),
            'bucket' => $env('OSS_BUCKET', ''),
            'endpoint' => $env('OSS_ENDPOINT', ''),
            'domain' => $env('OSS_VIEW_DOMAIN', ''),
            'role_arn' => $env('OSS_ROLEARN', ''),
            'policy_file' => $env('OSS_POLICYFILE', ''),
            'token_ttl' => (int)$env('OSS_TOKENEXPIRETIME', 900),
            'sign_ttl' => (int)$env('OSS_SIGN_TTL', 3600),
        ],
    ],
];
