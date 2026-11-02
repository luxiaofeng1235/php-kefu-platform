<?php

namespace plugin\admin\app\controller;

use support\Request;
use support\Response;
use plugin\admin\app\model\DesignVisual;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 三维可视化 
 */
class DesignVisualController extends Crud
{
    
    /**
     * @var DesignVisual
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new DesignVisual;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('design-visual/index');
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
        return view('design-visual/insert');
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
        return view('design-visual/update');
    }

}
