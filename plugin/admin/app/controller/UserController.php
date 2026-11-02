<?php

namespace plugin\admin\app\controller;

use app\common\server\JsonServer;
use support\Request;
use support\Response;
use plugin\admin\app\model\User;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 测试123 
 */
class UserController extends Crud
{
    /**
     * 不需要自动鉴权的方法
     * @var array
     */
    protected $noNeedLogin = ['profile'];

    /**
     * @var array
     */
    protected $noNeedAuth = ['profile'];

    /**
     * @var User
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new User;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('user/index');
    }

    /**
     * 插入
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function insert(Request $request): Response
    {
        if ($request->method() === 'POST') {
            return parent::insert($request);
        }
        return view('user/insert');
    }

    /**
     * 更新
     * @param Request $request
     * @return Response
     * @throws BusinessException
    */
    public function update(Request $request): Response
    {
        if ($request->method() === 'POST') {
            return parent::update($request);
        }
        return view('user/update');
    }

    /**
     * @note 获取当前登录管理员信息
     * @param Request $request 当前请求对象
     * @return Response 返回包含管理员标识的 JSON 数据
     */
    public function profile(Request $request): Response
    {
        // 从会话中获取管理员信息，用于后续业务场景识别身份
        $admin = admin();
        if (!$admin) {
            //如果获取不到，就说明是没有登录，就提示先去登录
            return $this->json(401, '请先登录', []);
        }
        $admin_id = (int)($admin['id'] ?? 0);
        //生成token信息校验
        $jwt_token = buildAdminWebsocketToken($admin_id , null   , 32);
        $data = [
            'id'       => $admin_id,
            'username' => (string)($admin['username'] ?? ''),
            'nickname' => (string)($admin['nickname'] ?? ''),
            'roles'    => $admin['roles'] ?? [],
            'token'    => $request->sessionId(), //获取当前的登录的cookie
            'jwt_token' => $jwt_token,
        ];
        return $this->json(0, 'ok', $data);
    }

}
