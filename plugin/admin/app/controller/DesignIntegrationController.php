<?php
/*
 *
 * 虚拟一体化控制器
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file DesignIntegrationController.php
 */

namespace plugin\admin\app\controller;

use app\api\service\SectorService;
use support\Request;
use support\Response;
use plugin\admin\app\model\DesignIntegration;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 虚拟一体化控制器
 */
class DesignIntegrationController extends Crud
{

    /**
     * @var DesignIntegration
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new DesignIntegration;
    }

    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('design-integration/index');
    }

    /**
     * @note 插入数据并清除缓存
     * @param Request $request 请求实例
     * @return Response 统一 JSON 响应
     * @throws BusinessException
     */
    public function insert(Request $request): Response
    {
        if ($request->method() === 'POST') {
            // 调用父类插入逻辑
            $response = parent::insert($request);

            // 插入成功后清除前台接口缓存
            SectorService::clearIntegrationCache();

            return $response;
        }
        return view('design-integration/insert');
    }

    /**
     * @note 更新数据并清除缓存
     * @param Request $request 请求实例
     * @return Response 统一 JSON 响应
     * @throws BusinessException
    */
    public function update(Request $request): Response
    {
        if ($request->method() === 'POST') {
            // 调用父类更新逻辑
            $response = parent::update($request);

            // 更新成功后清除前台接口缓存
            SectorService::clearIntegrationCache();

            return $response;
        }
        return view('design-integration/update');
    }

    /**
     * @note 删除数据并清除缓存
     * @param Request $request 请求实例
     * @return Response 统一 JSON 响应
     * @throws BusinessException
     */
    public function delete(Request $request): Response
    {
        // 调用父类删除逻辑
        $response = parent::delete($request);

        // 删除成功后清除前台接口缓存
        SectorService::clearIntegrationCache();

        return $response;
    }

}
