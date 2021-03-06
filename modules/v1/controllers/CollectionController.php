<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/5
 * Time: 18:05
 */

namespace app\modules\v1\controllers;

use app\modules\v1\models\Collection;
use yii\web\Response;


class CollectionController extends DefaultController
{

    public $modelClass = '' ;

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator']['formats'] = '';
        $behaviors['contentNegotiator']['formats']['application/json'] = Response::FORMAT_JSON;
        return $behaviors;

    }

    // 添加到收藏 - 并增加到redis
    public function actionAddCollection($typeid,$indexid,$mid)
    {
        $collection = new Collection();
        $collection->typeid = $typeid;
        $collection->indexid = $indexid;
        $collection->memberid = $mid;
        $collection->cotime = date('Y-m-d H:i:s',time()) ;
        //$key = 'collect:type:'.$typeid. ':indexid:'.$indexid.':member:'.$memberid ;
        $key = 'collect:'.$typeid. ':'.$indexid.':'.$mid ;
        // 判断是否已经收藏
        $redis = \Yii::$app->redis;
        if($redis->get($key)){
            return false;
        }elseif (Collection::isCollection($typeid,$indexid,$mid)){
            $redis->set($key ,'1');         // 更新redis 数据
            return false ;
        }
        // 若未收藏,则进行收藏
        $redis->set($key ,'1');
        return $collection->save();

    }

    // 添加到收藏 - 并增加到redis - 通用
    public function actionAddCollectionTong()
    {
        if(!$this->logsign)
            return ['code'=>401,'msg'=>'用户未登录','data'=>''];

        $request = \Yii::$app->request;
        $typeid = $request->get('typeid');
        $indexid = $request->get('id');
        $mid = $this->mid;
        $collection = new Collection();
        $collection->typeid = $typeid;
        $collection->indexid = $indexid;
        $collection->cotime = date('Y-m-d H:i:s',time()) ;
        $key = 'collect:'.$typeid. ':'.$indexid.':'.$mid ;
        // 判断是否已经收藏
        $redis = \Yii::$app->redis;
        if($redis->get($key))
            return ['code'=>402,'msg'=>'已经收藏过','data'=>''];
        elseif (Collection::isCollection($typeid,$indexid,$mid)){
            $redis->set($key ,'1');         // 更新redis 数据
            return ['code'=>402,'msg'=>'已经收藏过','data'=>''];
        }

        // 若未收藏,则进行收藏
        $redis->set($key ,'1');
        $collection->memberid = $this->mid;
        $collection->save();
        return ['code'=>200,'msg'=>'已经收藏过','data'=>''];

    }

    // 取消收藏 - 数据库和redis 缓存清除 - 通用
    public function actionDelCollectionTong()
    {
        if(!$this->logsign)
            return ['code'=>401,'msg'=>'用户未登录','data'=>''];

        $request = \Yii::$app->request;
        $typeid = $request->get('typeid');
        $indexid = $request->get('id');
        $redis = \Yii::$app->redis;

        $mid = $this->mid;
        $key = 'collect:'.$typeid. ':'.$indexid.':'.$mid ;
        $collectionObj = new Collection();

        if(! $redis->get($key) || !$collectionObj->isCollection($typeid,$indexid,$mid) )
            return ['code'=>402,'msg'=>'用户未收藏该商品','data'=>''];

        $redis->del($key);
        $collectionObj->delCollection($typeid,$indexid,$mid) ;
        return ['code'=>200,'data'=>'','msg'=>'取消成功'];
    }

    // 取消收藏 - 数据库和redis 缓存清除
    public function actionDelCollection($typeid,$indexid,$mid)
    {
        $redis = \Yii::$app->redis;

        $collectionObj = new Collection();
        $key = 'collect:'.$typeid. ':'.$indexid.':'.$mid ;
        if(! $redis->get($key) || !$collectionObj->isCollection($typeid,$indexid,$mid) ){
            return false ;  // 用户未收藏该商品
        }
        $redis->del($key);
        return $collectionObj->delCollection($typeid,$indexid,$mid) ;
    }
    // 批量删除收藏 - 根据收藏id删除
    public function actionDelCollectionByids(array $ids)
    {
        // 删除缓存数据
        // 先获取基本信息 , 便于清除缓存
        $collection =   Collection::getCollectionByids($ids);
        foreach ($collection as $k=>$v)
        {
            $redis = \Yii::$app->redis;
            $key = 'collect:'.$v['typeid']. ':'.$v['indexid'].':'.$v['mid'] ;
            $redis->del($key);
        }
        // 删除数据表中记录
       return Collection::deleteAll(['id'=>$ids]);
    }

}