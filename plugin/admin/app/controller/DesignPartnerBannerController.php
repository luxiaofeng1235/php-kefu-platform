<?php

namespace plugin\admin\app\controller;

use support\Request;
use support\Response;
use plugin\admin\app\model\DesignPartnerBanner;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 合伙人海报 
 */
class DesignPartnerBannerController extends Crud
{
    
    /**
     * @var DesignPartnerBanner
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new DesignPartnerBanner;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('design-partner-banner/index');
    }

    /**
     * 获取单个横幅信息
     * @param Request $request
     * @return Response
     */
    public function info(Request $request): Response
    {
        $banner = $this->model->orderBy('updated_at', 'desc')->first();
        if ($banner) {
            return json(['code' => 0, 'msg' => 'ok', 'data' => $banner]);
        } else {
            return json(['code' => 0, 'msg' => 'ok', 'data' => (object)[]]); // 返回空对象或根据前端需求返回null
        }
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
            $data = $request->post();
            if (isset($data['id'])) {
                unset($data['id']); // 新增时移除id字段
                $request->post($data); // 更新请求数据
            }
            return parent::insert($request);
        }
        return view('design-partner-banner/insert');
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
        return view('design-partner-banner/update');
    }

}
