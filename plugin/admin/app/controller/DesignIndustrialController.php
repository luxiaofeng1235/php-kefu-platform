<?php

namespace plugin\admin\app\controller;

use support\Request;
use support\Response;
use plugin\admin\app\model\DesignIndustrial;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * AI工业园 
 */
class DesignIndustrialController extends Crud
{
    
    /**
     * @var DesignIndustrial
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new DesignIndustrial;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('design-industrial/index');
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
        return view('design-industrial/insert');
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
        return view('design-industrial/update');
    }

}
