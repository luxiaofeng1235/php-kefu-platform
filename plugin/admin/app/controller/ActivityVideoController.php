<?php

namespace plugin\admin\app\controller;

use plugin\admin\app\model\ActivityVideo;
use support\Db;
use support\exception\BusinessException;
use support\Request;
use support\Response;

/**
 * 单视频配置
 */
class ActivityVideoController extends Crud
{

    /**
     * @var ActivityVideo
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new ActivityVideo;
    }

    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('activity-video/index');
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
            $param = $request->post();
            $info = Db::table('activity_video')->where('type', $param['type'])->first();
            if (!empty($info)) {
                return $this->json(1, '该场景类型已存在，请勿重复添加');
            }
            return parent::insert($request);
        }
        return view('activity-video/insert');
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
            $param = $request->post();
            $id = intval($param['id'] ?? 0);
            //查当前分类是否有存在的分类场景类型的数据
            $info = Db::table('activity_video')
                ->where('type', $param['type'])
                ->whereNot('id', $id)
                ->first();
            if (!empty($info)) {
                return $this->json(1, '该场景类型已存在，请勿重复添加');
            }
            return parent::update($request);
        }
        return view('activity-video/update');
    }

}
