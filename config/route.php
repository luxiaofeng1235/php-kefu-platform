<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use app\api\controller\AccountController;
use app\api\controller\ActivityController;
use app\api\controller\ChatController;
use app\api\controller\ConfigController;
use app\api\controller\ContactController;
use app\api\controller\DesignController;
use app\api\controller\FileController;
use app\api\controller\NewsController;
use app\api\controller\SearchController;
use app\api\controller\SystemController;
use app\api\controller\VisitorController;
use Webman\Route;

// ====== API 接口模块 ======
Route::group('/api', function () {


    // ========== 联系我们模块 ==========
    Route::group('/contact', function () {
        Route::get('/getInfo', [ContactController::class, 'getInfo']);//联系我们
    });

    // ========== 三维设计模块 ==========
    Route::group('/design', function () {
        Route::get('/vr', [DesignController::class, 'index']); // VR 功能列表
        Route::get('/visual', [DesignController::class, 'visual']); // 三维可视化列表
        Route::get('/three-dimensional', [DesignController::class, 'threeDimensional']); // 三维场景列表
        Route::get('/platform', [DesignController::class, 'platform']); // AI 定制化平台列表
        Route::get('/industrial', [DesignController::class, 'industrial']); // AI 工业园区详情
        Route::get('/partner-banner', [DesignController::class, 'partnerBanner']); // 合作伙伴横幅
        Route::get('/search', [DesignController::class, 'search']); // 综合搜索
        Route::get('/website', [DesignController::class, 'website']); // 三维站点列表
        Route::get('/integration', [DesignController::class, 'integration']); // 虚拟一体化
        Route::get('/indexSetting', [DesignController::class, 'indexSetting']);//首页基础设置
        Route::get('/getValueByType', [DesignController::class, 'getValueByType']); // 兼容旧路由
    });

    // ========== 文件上传模块 ==========
    Route::group('/file', function () {
        Route::post('/formimage', [FileController::class, 'formImage']);//上传图片
        Route::post('/formvideo', [FileController::class, 'formVideo']);//上传视频
        Route::post('/formfile', [FileController::class, 'formFile']);//上传文件
        Route::post('/formNormal', [FileController::class, 'formNormal']);//上传普通的站内附件
    });

    // ========== 搜索模块 ==========
    Route::group('/search', function () {
        Route::get('/query', [SearchController::class, 'query']); // Bing 搜索
    });

    // ========== Chat 对话模块stream流输出 ==========
    Route::group('/chat', function () {
        Route::post('/stream', [ChatController::class, 'stream']);
    });

    //通用营销的一些接口
    Route::group('/common', function () {
        Route::get('/indexRouteConfig', [ActivityController::class, 'indexRouteConfig']);//获取活动的配置信息
        Route::get('/singleVideo', [ActivityController::class, 'singleVideo']);//获取单视频
        Route::get('/ai_train_banner', [ConfigController::class, 'aiTrainBanner']); //获取AI培训的banner设置
        Route::get('/getAdsRecord', [ActivityController::class, 'getAdsRecord']);//获取随机的广告
    });

    // ========== 活动资讯 ==========
    Route::group('/news', function () {
        //活动资讯列表
        Route::get('/activityList', [NewsController::class, 'activityList']);//互动列表
        Route::get('/test', [NewsController::class, 'test']);//测试
    });

    // ========== 客服相关 ==========
    Route::group('/account', function () {
        //获取客服列表
        Route::get('/getAccountList', [AccountController::class, 'getAccountList']);
    });


    // ========== 在线访客 ==========
    Route::group('/visitor', function () {
        Route::get('/getVisitorList', [VisitorController::class, 'getVisitorList']);//查询在线用户
        Route::get('/hold/status', [VisitorController::class, 'holdStatus']);//获取客服的挂起状态
        Route::post('/hold', [VisitorController::class, 'hold']);//更新挂起、挂断
        Route::post('/read', [VisitorController::class, 'read']); // 标记访客消息为已读
        Route::get('/config', [VisitorController::class, 'config']);//获取聊天系统配置
        Route::get('/served', [VisitorController::class, 'served']); // 获取客服已接入的访客列表

        /****************获取历史聊天记录*************/
        Route::get('/history', [ChatController::class, 'history']); //获取历史聊天记录
    });

    // ========== 系统相关 ==========
    Route::group('/system', function () {
        Route::get('/asset-version', [SystemController::class, 'assetVersion']); // 获取静态资源版本号
    });
});
