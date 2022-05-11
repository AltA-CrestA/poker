<?php

namespace app\modules\socket\controllers;

use app\modules\socket\models\User;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\rest\Controller;

/**
 * Class UserController
 * @package app\modules\socket\controllers
 */
class UserController extends Controller
{
    public $serializer = [
        'class' => 'app\modules\socket\components\Serializer',
        'collectionEnvelope' => 'items',
        'metaEnvelope' => 'pagination'
    ];

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['device'] = [
            'class' => HttpBearerAuth::class,
        ];
        $behaviors['corsFilter'] = [
            'class' => Cors::className(),
        ];
        return $behaviors;
    }

    protected function verbs()
    {
        return [
            'check' => ['GET', 'HEAD'],
            'view' => ['GET'],
            'options' => ['OPTIONS'],
        ];
    }

    public function actions()
    {
        $actions = parent::actions();
        $actions['options'] = ['class' => 'yii\rest\OptionsAction'];
        return $actions;
    }


    public function actionCheck(){
        return ["user" => Yii::$app->user->identity];
    }

    /**
     * Получить пользователя
     * 
     * @param $id
     * @return \app\models\User|User|\yii\web\IdentityInterface|null
     * @throws \yii\web\NotFoundHttpException
     */

    public function actionView($id) 
    {
        $user = User::findIdentity($id);
        if (!$user) throw new \yii\web\NotFoundHttpException("User id#$id not found");
        return $user;
    }
}
