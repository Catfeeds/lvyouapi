<?php
/**
 * Created by PhpStorm.
 * User: 张鹏飞
 * Date: 2018/1/5
 * Time: 17:44
 */

namespace app\modules\v1\controllers;

use app\modules\components\helpers\MyImg;
use app\modules\v1\models\Comment;
use app\modules\v1\models\Member;
use app\modules\v1\models\MemberOrder;
use yii\web\UploadedFile;

class CommentController extends DefaultController
{
    public $typeIdArr;
    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        $this->typeIdArr  =       \Yii::$app->params['typeid'];
    }

    // 评论列表
    public function actionCommentList()
    {
        $request    =       \Yii::$app->request;
        $articleid  =       $request->get('id',0);
        $typeid     =       $request->get('typeid');
        $page       =       $request->get('page',1);
        $level      =       $request->get('level',0);
        if(!in_array($typeid,array_values($this->typeIdArr)))
            return ['code'=>403,'msg'=>'参数错误','data'=>''];

        $comment    =       Comment::getCommentByPageLevel($typeid,$articleid,$page,$level);
        if(empty($comment))
            return ['code'=>404,'data'=>[],'msg'=>'未找到数据'];

        $app_url    =   \Yii::$app->params['app_url'];
        foreach ($comment['commentlist'] as $k=>$v){

            // 处理图片
            $piclist = explode(',',$v['piclist']);

            foreach ($piclist as $pk=>$pv)
            {
                if(!empty($pv))
                    $piclist[$pk] = $app_url . $pv;
            }
            $comment['commentlist'][$k]['piclist'] = $piclist;

            // 处理用户头像
            if($v['headpic'])
                $comment['commentlist'][$k]['headpic'] = $app_url . $v['headpic'];

            // 处理虚拟用户头像
            if($v['vr_headpic'])
                $comment['commentlist'][$k]['headpic'] = $app_url . $v['vr_headpic'];

            // 处理用户昵称 - 若vr_headpic存在则为昵称
            $v['vr_nickname']? $comment['commentlist'][$k]['nickname'] =  $v['vr_nickname'] : $comment['commentlist'][$k]['nickname'] =  $v['nickname'];
            if(!$v['nickname'] && !$v['vr_nickname']) $comment['commentlist'][$k]['nickname'] = '匿名用户';

            // 处理dockid 也就是用户是否为回复
            unset($comment['commentlist'][$k]['vr_headpic']);
            unset($comment['commentlist'][$k]['vr_nickname']);
        }

        // 对null值进行处理 - 前段要求   `-`
        foreach ($comment['commentlist'] as $k=>$v)
        {
            foreach ($v as $vk=>$vv)
            {
                if(is_null($v[$vk])) $comment['commentlist'][$k][$vk] = '';
            }
        }
        return ['code'=>200,'data'=>$comment,'msg'=>'ok'];

    }

    // 评论头部
    public function actionCommentHead()
    {
        $commentObj             =       new Comment();
        $request                =       \Yii::$app->request;
        $typeid                 =       $request->get('typeid');
        $indexid                =       $request->get('id');
        $commentArr             =       $commentObj->getCommentStarCount($typeid,$indexid);
        $commentCount           =       $commentObj->getLevelComment($commentArr);
        $commentCount['count']  =       (int)$commentObj->getCommentCountByTypeId($typeid,$indexid);
        $commentCount['imgcount']=      (int)$commentObj->getCommentHasImg($typeid,$indexid);
        return ['code'=>200,'data'=> $commentCount,'msg'=>'ok'];
    }

    // 添加评论
    public function actionAdd()
    {
        if(!$this->logsign) return ['code'=>401,'msg'=>'用户未登录','data'=>null];
        $request        =       \Yii::$app->request;
        $content        =       $request->post('content');
        $typeid         =       $request->post('typeid',0);
        $articleid      =       $request->post('id');
        $memberid       =       $this->mid;
        $is_anonymous   =       $request->post('is_anonymous',0);
        $dockid         =       $request->post('replyid',0);

        if(!in_array($typeid,array_values($this->typeIdArr))
            || empty($content) || empty($articleid))
            return ['code'=>403,'msg'=>'参数错误','data'=>''];

        $data           =       [
            'content'   =>       strip_tags($content),
            'typeid'    =>       (int)$typeid,
            'articleid' =>       (int)$articleid,
            'memberid'  =>       (int)$memberid,
            'dockid'    =>       (int)$dockid,
            'addtime'   =>       time(),
        ];
        // 若是匿名登录 , 则默认昵称为昵称用户
        if($is_anonymous) $data['vr_nickname'] = '匿名用户';

        $re =  \Yii::$app->db->createCommand()->insert(Comment::tableName(),$data)->execute();

        if($re)
            return ['code'=>200,'msg'=>'ok','data'=>''];
        else
            return ['code'=>400,'msg'=>'失败','data'=>''];

    }

    // 处理评论用户和虚拟用户的信息
    public function actionGetMember(array $commentArr,$app_url)
    {
        // 对拿到的信息进行虚拟用户和真是用户信息混合
        foreach ($commentArr as $k=>$v)
        {
            if(!$v['vr_nickname'])
            {
                $commentLister[$k] = [
                    'content'     =>      $v['content'],
                    'addtime'   =>      $v['addtime'],
                    'piclist'   =>      $v['piclist'],
                    'star'      =>      $v['star'],
                    'nickname'  =>      $v['nickname'],
                    'rank'      =>      $v['rank'],

                ];
            }else{
                $commentLister[$k] = [
                    'content'     =>      $v['content'],
                    'addtime'   =>      $v['addtime'],
                    'star'      =>      $v['star'],
                    'nickname'  =>      $v['vr_nickname'],
                    'rank'      =>      $v['vr_grade'],
                ];
            }
            $piclist            =       explode(',',$v['piclist']);
            if (!empty($piclist))
            {
                foreach ($piclist as $k1=>$v1)
                {
                    if(empty($v1)) break;
                    $piclist[$k1]    =   $app_url    .   $v1;
                }
            }
            $commentLister[$k]['piclist']   =   $piclist;
        }
        return $commentLister;
    }

    // 添加点评
    public function actionOrderCommentAdd()
    {
        // 验证数据的合法性
        $request = \Yii::$app->request;
        $id = $request->post('id');
        $content = strip_tags(htmlentities($request->post('content')));
        $level = $request->post('level',5);
        $piclist = $request->post('piclist');
        $mid = $this->mid;
        if (!$mid)
            return ['code' => 401, 'data' => '', 'msg' => '用户未登录'];

        if (!$id)
            return ['code'=>4030,'msg'=>'订单id未填写','data'=>''];

        if(mb_strlen($content) < 5)
            return ['code'=>4031,'msg'=>'请输入内容不小于5个字','data'=>''];


        // 验证用户id和订单id
        $orderObj = new MemberOrder();
        $order = $orderObj->getDetail($id);
        if ($order['memberid'] != $mid)
            return ['code' => 403, 'data' => '', 'msg' => '非法数据'];

        // 验证订单状态
        if ($order['status'] != 5)
            return ['code' => 405, 'data' => '', 'msg' => '该订单尚未完成'];
        if ($order['ispinglun'] == 1)
            return ['code' => 406, 'data' => '', 'msg' => '该订单已经评论过'];

        // 处理图片
        $pic = [];
        if(!empty($piclist)){
            $piclist = explode('-',$piclist);
            $myimgObj = new MyImg();
            $api_url = \Yii::$app->params['api_url'];
            $path = "./img/lvyou/commentimg/" ;

            foreach ($piclist as $k=>$v)
            {
                $img = $myimgObj->uploadImgBy64($v,$path,$api_url);
                if($img)
                    $pic[$k] = $img;
            }
        }
        $pic = join(',',$pic);

        $data = [
            'typeid' => $order['typeid'],
            'orderid' => $id,
            'articleid' => $order['productautoid'],
            'memberid' => $mid,
            'pid' => 0,
            'content' => $content,
            'dockid' => 0,
            'score1' => 0,
            'isshow' => 1,
            'addtime' => time(),
            'level' => $level,
            'piclist' => $pic,

        ];
        // 生成数据
        $re = \Yii::$app->db->createCommand()->insert(Comment::tableName(),$data)->execute();
        if($re){
            $memberOrder = MemberOrder::findOne($id);
            $memberOrder->ispinlun = 1;
            $memberOrder->save();
            return ['code'=>200,'data'=>'','msg'=>'评论成功'];
        }
        else
            return ['code'=>4032,'data'=>'','msg'=>'评论失败'];
    }


}