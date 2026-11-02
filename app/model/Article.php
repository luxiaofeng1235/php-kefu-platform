<?php

namespace app\model;

use support\Model;

class Article extends Model
{

    protected $table = 'article';
    
    protected $primaryKey = 'id';

    public $timestamps = false;

    /**
     * @note 获取文章详情
     * @param int $id 文章第
     * @date 2025/10/16
     * @return Article|array|bool
     */
    public static function getArticleInfo(int $id){
        if(!$id){
            return false;
        }
        //查询单条文章记录
        $detailInfo = Article::where('status', 1)
            ->where('id', '=', $id)
            ->first();
        if(!$detailInfo || empty($detailInfo)){
           return false;
        }
        return $detailInfo;
    }
}