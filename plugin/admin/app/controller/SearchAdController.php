<?php

namespace plugin\admin\app\controller;

use plugin\admin\app\model\Option;
use plugin\admin\app\model\SearchAd;
use support\exception\BusinessException;
use support\Request;
use support\Response;

/**
 * 广告设置
 */
class SearchAdController extends Crud
{

    /**
     * @var SearchAd
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new SearchAd;
    }

    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('search-ad/index');
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
        return view('search-ad/insert');
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
        return view('search-ad/update');
    }

    /**
     * 获取全局配置
     * @param Request $request
     * @return Response
     */
    public function getConfig(Request $request): Response
    {
        $key = 'search_ad_config';
        $value = Option::where('name', $key)->value('value');
        $config = json_decode((string)$value, true) ?? [
//            'search_ad_limit' => 3,
            'random_count' => 1,
            'is_enabled_global' => 1 // 新增：默认启用全局广告列表
        ];
        return json(['code' => 0, 'msg' => 'ok', 'data' => $config]);
    }

    /**
     * 保存全局配置
     * @param Request $request
     * @return Response
     */
    public function saveConfig(Request $request): Response
    {
//        $limit = (int)$request->post('search_ad_limit', 3);
//        if ($limit < 1) {
//            return json(['code' => 1, 'msg' => '搜索展示条数不能小于1']);
//        }
//
        $randomCount = (int)$request->post('random_count', 1);
        if ($randomCount < 1) {
            return json(['code' => 1, 'msg' => '随机条数不能小于1']);
        }

        // 新增：接收 is_enabled_global 参数 (1=启用, 0=禁用)
        $isEnabledGlobal = (int)$request->post('is_enabled_global', 1);

        $key = 'search_ad_config';
        $config = [
            'random_count' => $randomCount,
            'is_enabled_global' => $isEnabledGlobal // 新增：保存 is_enabled_global
        ];

        $option = Option::where('name', $key)->first();
        if ($option) {
            $option->value = json_encode($config);
            $option->save();
        } else {
            $option = new Option();
            $option->name = $key;
            $option->value = json_encode($config);
            $option->save();
        }
        return json(['code' => 0, 'msg' => '保存成功']);
    }

}
