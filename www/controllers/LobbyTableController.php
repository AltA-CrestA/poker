<?php
namespace app\modules\socket\controllers;

use Exception;
use Yii;
use yii\base\Module;
use app\modules\socket\services\LobbyTableService;

use app\modules\socket\models\{
    SocketGame,
    SocketLobbyTable,
    form\SocketLobbyJoinForm
};

use yii\filters\{
    AccessControl,
    Cors,
    auth\HttpBearerAuth
};

use yii\rest\Controller;
use yii\web\NotFoundHttpException;

class LobbyTableController extends Controller
{
    public $serializer = [
        'class' => 'app\modules\socket\components\Serializer',
        'collectionEnvelope' => 'items',
        'metaEnvelope' => 'pagination'
    ];

    private $lobbyTableService;

    public function __construct($id, Module $module, $config = [], LobbyTableService $lobbyTableService)
    {
        $this->lobbyTableService = $lobbyTableService;

        parent::__construct($id, $module, $config);
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['device'] = [
            'class' => HttpBearerAuth::class,
        ];
        $behaviors['corsFilter'] = [
            'class' => Cors::class,
        ];
        $behaviors['access'] = [
            'class' => AccessControl::class,
            'rules' => [
                [
                    'allow' => true,
                    'actions' => [
                        'index',
                        'view',
                        'join',
                        'delete',
                        'access-join'
                    ],
                    'roles' => ['@']
                ]
            ]
        ];
        return $behaviors;
    }

    protected function verbs()
    {
        return [
            'options' => ['OPTIONS'],
            'join' => ['POST'],
            'view' => ['GET'],
            'index' => ['GET'],
            'delete' => ['delete'],
            'access-join' => ['GET']
        ];
    }

    public function actions()
    {
        $actions = parent::actions();
        $actions['options'] = ['class' => 'yii\rest\OptionsAction'];
        return $actions;
    }

    /**
     * Получить стол
     *
     * @param int $id
     * @return SocketLobbyTable
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): SocketLobbyTable
    {
        return $this->lobbyTableService->getLobbyTable($id);
    }

    /**
     * Присоедениться к игре
     *
     * @return SocketLobbyJoinForm|SocketGame
     * @throws Exception
     */
    public function actionJoin()
    {
        $form = new SocketLobbyJoinForm();

        if ($form->load(Yii::$app->request->post(), "") && $form->validate()) {
            return $this->lobbyTableService->getFreeTable($form);
        }

        return $form;
    }
}