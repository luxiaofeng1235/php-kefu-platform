<?php

namespace plugin\admin\app\controller;

use support\Request;
use support\Response;
use plugin\admin\app\model\Article;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 菜单列表 
 */
class ArticleController extends Crud
{
    
    /**
     * @var Article
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new Article;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('article/index');
    }

    /**
     * 自定义字段查询条件 - 为标题字段实现模糊搜索
     * @param mixed $model 当前查询构造器
     * @param string $column 字段名
     * @param mixed $value 字段值
     * @return mixed 返回修改后的查询构造器
     */
    protected function customizeFieldCondition($model, string $column, $value)
    {
        // 标题字段使用模糊搜索
        if ($column === 'title' && !empty($value) && is_string($value)) {
            return $model->where($column, 'like', "%$value%");
        }

        // 其他字段保持原有逻辑，确保100%兼容
        return parent::customizeFieldCondition($model, $column, $value);
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
        return view('article/insert');
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
        return view('article/update');
    }

}
