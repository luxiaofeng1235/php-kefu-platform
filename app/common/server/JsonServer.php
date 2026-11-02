<?php

/**
 * Webman 统一 JSON 返回服务类
 * Class JsonServer
 * @Author FZR
 */


namespace app\common\server;

use support\Response;
use Throwable;


class JsonServer
{
    private static $SUCCESS = 1; // 成功状态码
    private static $ERROR   = 0; // 失败状态码

    /**
     * 统一返回 JSON 格式
     * @param int $code 状态码
     * @param int $show 是否显示
     * @param string $msg 提示信息
     * @param mixed $data 返回数据
     * @param int $httpStatus HTTP 状态码
     * @return Response
     */
    private static function result(int $code, int $show, string $msg = 'OK', $data = [], int $httpStatus = 200): Response
    {
        $result = [
            'code' => $code,
            'show' => $show,
            'msg'  => $msg,
            'data' => $data,
        ];

        return json($result)->withStatus($httpStatus)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * 成功返回
     * @param string $msg 提示信息
     * @param mixed $data 返回数据
     * @param int $code 状态码
     * @param int $show 是否显示
     * @return Response
     */
    public static function success(string $msg = 'OK', $data = [], int $code = 1, int $show = 0): Response
    {
        return self::result($code, $show, $msg, $data);
    }

    /**
     * 错误返回
     * @param string $msg 提示信息
     * @param mixed $data 返回数据
     * @param int $code 状态码
     * @param int $show 是否显示
     * @return Response
     */
    public static function error(string $msg = 'Error', $data = [], int $code = 0, int $show = 1): Response
    {
        return self::result($code, $show, $msg, $data);
    }

    /**
     * 抛出 JSON 异常（中断执行并返回 JSON）
     * @param string $msg 提示信息
     * @param array $data 数据
     * @param int $code 状态码
     * @param int $show 是否显示
     * @throws Throwable
     */
    public static function throw(string $msg = 'Error', array $data = [], int $code = 0, int $show = 1)
    {
        $response = self::result($code, $show, $msg, $data);
        throw new class($response) extends \Exception {
            public $response;
            public function __construct(Response $response)
            {
                $this->response = $response;
                parent::__construct('JsonServerThrow');
            }
        };
    }
}
