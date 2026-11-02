<?php

namespace plugin\admin\app\controller;

use plugin\admin\app\model\Customer;
use support\exception\BusinessException;
use support\Request;
use support\Response;

/**
 * 在线客服
 */
class CustomerController extends Crud
{

    /**
     * @var Customer
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new Customer;
    }

    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('customer/index');
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
        return view('customer/insert');
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
        return view('customer/update');
    }

    /**
     * 进入客服聊天界面（安全跳转）
     * @param Request $request
     * @return Response
     */
    public function chat(Request $request): Response
    {
        // 1. 获取当前登录的管理员信息（自动继承 Base 的权限验证）
        $admin = admin();

        if (!$admin) {
            return redirect('/app/admin/account/login');
        }

        // 2. 检查是否是客服角色
        if ((int)($admin['is_kefu'] ?? 0) !== 1) {
            return json([
                'code' => 1,
                'msg' => '您不是客服，无法进入客服系统',
            ]);
        }

        // 3. 生成安全的 WebSocket token
        $adminId = (int)$admin['id'];
        $token = buildAdminWebsocketToken($adminId, null, 32);

        if (!$token) {
            return json([
                'code' => 1,
                'msg' => 'Token 生成失败',
            ]);
        }

        // 4. 构建客服聊天界面 URL（使用新版 Vue 界面）
        $chatUrl = sprintf(
            '/chat-vue.html?role=admin&admin_id=%d&token=%s',
            $adminId,
            urlencode($token)
        );

        // 5. 跳转到客服界面
        return redirect($chatUrl);
    }

}
