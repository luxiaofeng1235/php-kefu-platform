<?php
/*
 *
 * 缓存处理类
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file Cache.php
 */
// app/Support/Cache.php
namespace support;

class Cache
{
    protected static $redis;

    public static function redis()
    {
        if (!self::$redis) {
            self::$redis = new \Redis();
            $host = getenv('CACHE_REDIS_HOST') ?: '127.0.0.1';
            $port = getenv('CACHE_REDIS_PORT');
            $port = $port !== false && $port !== '' ? (int)$port : 6379;
            self::$redis->connect($host, $port);
            if ($password = getenv('CACHE_REDIS_PASSWORD')) {
                self::$redis->auth($password);
            }
            $dbIndex = getenv('CACHE_REDIS_SELECT');
            $dbIndex = $dbIndex !== false && $dbIndex !== '' ? (int)$dbIndex : 0;
            self::$redis->select($dbIndex);
        }
        return self::$redis;
    }

    /**
    * @note  set设置缓存
    * @param   $key string 缓存的键名
    */
    public static function set(string $key, $value, int $ttl = 3600): bool
    {
        return (bool)self::redis()->set($key, $value, $ttl);
    }

    /**
     * @note  get获取缓存
     * @param   $key string 缓存的键名
     */
    public static function get(string $key)
    {
        return self::redis()->get($key);
    }

    public static function del(string $key): int
    {
        return (int)self::redis()->del($key);
    }

    public static function hSet(string $key, string $field, $value): int
    {
        return (int)self::redis()->hSet($key, $field, $value);
    }

    public static function hGet(string $key, string $field)
    {
        return self::redis()->hGet($key, $field);
    }

    public static function hGetAll(string $key): array
    {
        $result = self::redis()->hGetAll($key);
        return is_array($result) ? $result : [];
    }

    public static function hDel(string $key, string ...$fields): int
    {
        return (int)self::redis()->hDel($key, ...$fields);
    }

    public static function sAdd(string $key, ...$members): int
    {
        return (int)self::redis()->sAdd($key, ...$members);
    }

    public static function sRem(string $key, ...$members): int
    {
        return (int)self::redis()->sRem($key, ...$members);
    }

    public static function sMembers(string $key): array
    {
        $result = self::redis()->sMembers($key);
        return is_array($result) ? $result : [];
    }

    public static function sCard(string $key): int
    {
        return (int)self::redis()->sCard($key);
    }

    public static function exists(string $key): bool
    {
        return self::redis()->exists($key) > 0;
    }

    public static function expire(string $key, int $ttl): bool
    {
        return (bool)self::redis()->expire($key, $ttl);
    }

    public static function pipeline(callable $callback)
    {
        $redis = self::redis()->multi(\Redis::PIPELINE);
        try {
            $callback($redis);
            return $redis->exec();
        } catch (\Throwable $exception) {
            $redis->discard();
            throw $exception;
        }
    }
}
