<?php
/**
 * Created by PhpStorm.
 * User: fateieder
 * Date: 09.08.19
 * Time: 12:36
 */

namespace app\modules\socket\controllers;


use app\modules\api\filters\auth\HttpDeviceAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\web\Response;

class NotifyController extends Controller
{
    public $serializer = [
        'class' => 'app\modules\api\components\Serializer',
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
        $behaviors['contentNegotiator'] = [
        'class' => ContentNegotiator::className(),
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
                'application/xml' => Response::FORMAT_XML,
            ],
        ];
        return $behaviors;
    }

    protected function verbs()
    {
        return [
            'view' => ['GET', 'HEAD', 'OPTIONS'],
            'create' => ['POST'],
            'test' => ['POST'],
            'options' => ['OPTIONS'],
        ];
    }

    public function actions()
    {
        $actions = parent::actions();
        $actions['options'] = ['class' => 'yii\rest\OptionsAction'];
        return $actions;
    }
}
