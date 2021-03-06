<?php
/**
 * Created by PhpStorm.
 * User: 张鹏飞
 * Date: 2018/1/20
 * Time: 11:29
 */

namespace app\modules\v1\controllers;


use app\modules\components\rongyun\RongCloud;

class RongyunController extends DefaultController
{
    public $ryappkey;
    public $ryappsecret;
    public $default_headpic;
    public $RongCloud;

    public function beforeAction($action)
    {
        $this->ryappkey = \Yii::$app->params['rongyun']['ry_appkey'];
        $this->ryappsecret = \Yii::$app->params['rongyun']['ry_appsecret'];
        $this->default_headpic = \Yii::$app->params['rongyun']['ry_default_headpic'];
        $this->RongCloud = new RongCloud($this->ryappkey,$this->ryappsecret,'1');
        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    // 获取 Token 方法 - 创建用户
    public function actionGettoken($userid='',$name='',$headpic = '')
    {
        if(empty($headpic))
            $headpic = \Yii::$app->params['api_url'] . $this->default_headpic;

        $result = $this->RongCloud->user()->getToken($userid, $name, $headpic);
        return  $result;
    }

    // 刷新用户信息 - 修改用户信息
    public function actionFlushUser($userid,$name,$headpic='')
    {
        if(empty($headpic))
            $headpic = \Yii::$app->params['api_url'] . $this->default_headpic;
        $result = $this->RongCloud->user()->refresh($userid, $name, $headpic);
        return $result;
    }

}