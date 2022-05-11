<?php
namespace app\modules\socket\controllers;

use Yii;
use yii\rest\Controller;
use yii\filters\{
    AccessControl,
    Cors
};
use yii\filters\auth\HttpBearerAuth;
use yii\web\{
    BadRequestHttpException,
    NotFoundHttpException,
    ServerErrorHttpException
};
use yii\base\Module;

use app\models\GameUserStatus;
use app\modules\socket\services\{GameService, GameUserService};
use app\modules\socket\models\{SocketBuyIn, SocketGameUser};
use app\modules\socket\models\form\{SocketCalltimeForm, SocketGameUserStatusForm, SocketDiamondUserForm};

class GameUserController extends Controller 
{
    public $serializer = [
        'class' => 'app\modules\socket\components\Serializer',
        'collectionEnvelope' => 'items',
        'metaEnvelope' => 'pagination'
    ];

    private $gameService;
    private $gameUserService;

    public function __construct($id, Module $module, $config = [], GameService $gameService, GameUserService $gameUserService)
    {
        $this->gameService = $gameService;
        $this->gameUserService = $gameUserService;

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
                        'buy-in',
                        'buy-in-response',
                        'calltime',
                        'set-status-out-game',
                        'set-status-disconnected',
                        'time-bank',
                        'rabbit'
                    ],
                    'roles' => ['@']
                ],
            ]
        ];
        return $behaviors;
    }

    protected function verbs()
    {
        return [
            'buy-in' => ['POST'],
            'buy-in-response' => ['GET'],
            'calltime' => ['PUT'],
            'options' => ['OPTIONS'],
            'set-state-out-game' => ['PUT'],
            'set-state-disconnected' => ['PUT'],
            'time-bank' => ['POST'],
            'rabbit' => ['POST']
        ];
    }

    public function actions()
    {
        $actions = parent::actions();
        $actions['options'] = ['class' => 'yii\rest\OptionsAction'];
        return $actions;
    }

    /**
     * Заявка на buy-in
     * 
     * @param int $id
     * @return SocketBuyIn
     * @throws NotFoundHttpException|ServerErrorHttpException
     */
    public function actionBuyIn(int $id): SocketBuyIn
    {
        $socketGame = $this->gameService->getGame($id);
        $socketBuyIn = new SocketBuyIn(['game_id' => $socketGame->id]);
    
        if ($socketBuyIn->load(Yii::$app->request->post(), "") && $socketBuyIn->validate()) {
            return $this->gameUserService->buyIn($socketBuyIn, $socketGame);
        }

        return $socketBuyIn;
    }

    /**
     * Получить заявку buy-in
     *
     * @param int $id
     * @param integer $request_id
     * @return SocketBuyIn
     * @throws NotFoundHttpException
     */
    public function actionBuyInResponse(int $id, int $request_id): SocketBuyIn
    {
        $socketGame = $this->gameService->getGame($id);
        return $this->gameUserService->getBuyIn($socketGame->id, $request_id);
    }

    /**
     * Получить calltime
     * 
     * @param integer $id
     * @return SocketGameUser|SocketCalltimeForm
     * @throws BadRequestHttpException|ServerErrorHttpException|NotFoundHttpException
     */
    public function actionCalltime(int $id)
    {
        $socketGame = $this->gameService->getGame($id);
        $socketCalltimeForm = new SocketCalltimeForm(['game_id' => $socketGame->id]);

        if ($socketCalltimeForm->load(Yii::$app->request->post(), "") && $socketCalltimeForm->validate()) {
            return $this->gameUserService->calltime($socketCalltimeForm, $socketGame);
        }

        return $socketCalltimeForm;
    }

    /**
     * Изменить статус игрока - OUT_GAME
     * 
     * @param integer $id
     * @return SocketGameUser|SocketGameUserStatusForm
     * @throws NotFoundHttpException|ServerErrorHttpException
     */
    public function actionSetStatusOutGame(int $id)
    {
        $socketGame = $this->gameService->getGame($id);
        $socketGameUserStatusForm = new SocketGameUserStatusForm(['game_id' => $socketGame->id, 'game_user_status_id' => GameUserStatus::OUT_GAME]);

        if ($socketGameUserStatusForm->load(Yii::$app->request->post(), "") && $socketGameUserStatusForm->validate()) {
            return $this->gameUserService->saveState($socketGameUserStatusForm);
        }

        return $socketGameUserStatusForm;
    }

    /**
     * Изменить стутус игрока - DISCONNECTED
     * 
     * @param integer $id
     * @return SocketGameUser|SocketGameUserStatusForm
     * @throws NotFoundHttpException|ServerErrorHttpException
     */
    public function actionSetStatusDisconnected(int $id)
    {
        $socketGame = $this->gameService->getGame($id);
        $socketGameUserStatusForm = new SocketGameUserStatusForm(['game_id' => $socketGame->id, 'game_user_status_id' => GameUserStatus::DISCONNECTED]);

        if ($socketGameUserStatusForm->load(Yii::$app->request->post(), "") && $socketGameUserStatusForm->validate()) {
            return $this->gameUserService->saveState($socketGameUserStatusForm);
        }

        return $socketGameUserStatusForm;
    }

    /**
     * Купить доп. время на ход за кристаллы
     *
     * @param int $id
     * @return SocketDiamondUserForm|SocketGameUser
     * @throws NotFoundHttpException
     */
    public function actionTimeBank(int $id)
    {
        $socketGame = $this->gameService->getGame($id);
        $form = new SocketDiamondUserForm([
            'game_id' => $socketGame->id,
            'user_id' => Yii::$app->request->post('user_id'),
            'scenario' => SocketDiamondUserForm::SCENARIO_TIME_BANK
        ]);

        if ($form->validate()) {
            $gameUser = $form->getGameUser();
            $gameUser->refresh();
            return $gameUser;
        }

        return $form;
    }

    /**
     * Купить докрутку за кристаллы
     *
     * @param int $id
     * @return SocketDiamondUserForm|SocketGameUser
     * @throws NotFoundHttpException
     */
    public function actionRabbit(int $id)
    {
        $socketGame = $this->gameService->getGame($id);
        $form = new SocketDiamondUserForm([
            'game_id' => $socketGame->id,
            'user_id' => Yii::$app->request->post('user_id'),
            'scenario' => SocketDiamondUserForm::SCENARIO_RABBIT
        ]);

        if ($form->validate()) {
            $gameUser = $form->getGameUser();
            $gameUser->refresh();
            return $gameUser;
        }

        return $form;
    }
}