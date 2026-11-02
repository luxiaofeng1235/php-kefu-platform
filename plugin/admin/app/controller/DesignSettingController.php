<?php

namespace plugin\admin\app\controller;

use support\Request;
use support\Response;
use plugin\admin\app\model\DesignSetting;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 首页配置 
 */
class DesignSettingController extends Crud
{
    
    /**
     * @var DesignSetting
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new DesignSetting;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('design-setting/index');
    }

    /**
     * 获取当前配置
     * @return Response
     */
    public function info(): Response
    {
        $record = $this->model
            ->orderBy($this->model->getKeyName())
            ->first();
        return $this->success('ok', $record ? $record->toArray() : []);
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
        return view('design-setting/insert');
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
        return view('design-setting/update');
    }

}
