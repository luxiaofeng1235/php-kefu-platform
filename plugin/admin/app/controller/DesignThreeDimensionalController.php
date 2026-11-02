<?php

namespace plugin\admin\app\controller;

use support\Request;
use support\Response;
use plugin\admin\app\model\DesignThreeDimensional;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 三维场景 
 */
class DesignThreeDimensionalController extends Crud
{
    
    /**
     * @var DesignThreeDimensional
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new DesignThreeDimensional;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('design-three-dimensional/index');
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
        return view('design-three-dimensional/insert');
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
        return view('design-three-dimensional/update');
    }

}
