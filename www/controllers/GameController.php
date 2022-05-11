<?php
namespace app\modules\socket\controllers;

use app\components\datetime\SmartDateTime;
use Yii;
use Exception;
use Http\Client\Common\Exception\HttpClientNotFoundException;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\{
    AccessControl,
    Cors
};
use yii\rest\Controller;
use yii\web\{BadRequestHttpException,
    HttpException,
    NotFoundHttpException,
    ServerErrorHttpException};
use yii\base\Module;

use app\modules\socket\models\{
    SocketGame,
    SocketGameUser,
    User
};
use app\modules\socket\services\GameService;
use app\modules\socket\models\form\{
    SocketWatchForm,
    SocketLeaveForm
};
use app\modules\socket\models\form\SocketGameForm;
use app\modules\socket\traits\SocketLogTrait;

/**
 * Class GameController
 * @package app\modules\socket\controllers
 */
class GameController extends Controller
{
    use SocketLogTrait;
    public $serializer = [
        'class' => 'app\modules\socket\components\Serializer',
        'collectionEnvelope' => 'items',
        'metaEnvelope' => 'pagination'
    ];

    private GameService $gameService;

    public function __construct($id, Module $module, $config = [], GameService $gameService)
    {
        $this->gameService = $gameService;

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
                        'leave',
                        'watch',
                        'update',
                        'delete'
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
            'check' => ['GET', 'HEAD'],
            'update' => ['PUT', 'POST'],
            'view' => ['GET', 'HEAD'],
            'delete' => ['DELETE'],
            'options' => ['OPTIONS']
        ];
    }

    public function beforeAction($action)
    {
        if (isset($_ENV['SOCKET_TEST']) && $_ENV['SOCKET_TEST']) $this->setSocketLog();
        return parent::beforeAction($action);
    }

    public function actions()
    {
        $actions = parent::actions();
        $actions['options'] = ['class' => 'yii\rest\OptionsAction'];
        return $actions;
    }

    /**
     * Получить игры пользователя
     * 
     * @param integer $user_id
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionIndex(int $user_id): array
    {
        return $this->gameService->getUserGamesList($user_id);
    }

    /**
     * Получить игру по идентификтору
     * 
     * @param integer $id
     * @return SocketGame
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): SocketGame
    {
        return $this->gameService->getGameWith($id);
    }

    /**
     * Удаление игры
     * 
     * @param integer $id
     * @return boolean
     * @throws NotFoundHttpException|ServerErrorHttpException
     */
    public function actionDelete(int $id): bool
    {
        $socketGame = $this->gameService->getGame($id);
        return $this->gameService->remove($socketGame);
    }

    /**
     * Обновление игры
     * 
     * @param integer $id
     * @return SocketGame|SocketGameForm
     * @throws NotFoundHttpException|ServerErrorHttpException
     */
    public function actionUpdate(int $id)
    {
        $socketGame = $this->gameService->getGame($id);
        $socketGameForm = new SocketGameForm(['game_id' => $socketGame->id, 'club_id' => $socketGame->club_id]);
        
        if ($socketGameForm->validate()) {
            return $this->gameService->update($socketGameForm, $socketGame);
        }

        return $socketGameForm;
    }

    /**
     * Покинуть игру
     * 
     * @param integer $id
     * @return SocketGame|SocketLeaveForm
     * @throws NotFoundHttpException|ServerErrorHttpException|BadRequestHttpException|HttpException
     */
    public function actionLeave(int $id)
    {
        $socketGame = $this->gameService->getGame($id);
        $socketLeaveForm = new SocketLeaveForm(['game_id' => $socketGame->id, 'table_id' => $socketGame->table_id]);

        if ($socketLeaveForm->load(Yii::$app->request->post(), "") && $socketLeaveForm->validate()) {
            return $this->gameService->leave($socketLeaveForm, $socketGame);
        }

        return $socketLeaveForm;
    }

    /**
     * Стать наблюдателем (зрителем)
     * 
     * @param integer $id
     * @return SocketGame|SocketWatchForm
     * @throws NotFoundHttpException|ServerErrorHttpException
     */
    public function actionWatch(int $id)
    {
        $socketGame = $this->gameService->getGame($id);
        $socketWatchForm = new SocketWatchForm(['game_id' => $socketGame->id, 'table_id' => $socketGame->table_id]);

        if ($socketWatchForm->load(Yii::$app->request->post(), "") && $socketWatchForm->validate()) {
            return $this->gameService->watch($socketWatchForm, $socketGame);
        }

        return $socketWatchForm;
    }

    /**
     * @param $id
     * @return SocketGameUser
     * @throws BadRequestHttpException|NotFoundHttpException|Exception
     */
    public function actionCalltime($id): SocketGameUser
    {
        $userId = Yii::$app->request->post('user_id');
        if (!$userId) throw new BadRequestHttpException('Bad Request. Empty user id');
        /** @var SocketGame $game */
        $game = SocketGame::findOne($id);
        if (!$game) throw new NotFoundHttpException("Game id#$id not found");
        $calltime = $game->table->is_calltime;
        if (!$calltime) throw new BadRequestHttpException("This game has disabled calltime");
        $calltimeValue = $game->table->calltime_value;
        if (!$calltimeValue) throw new BadRequestHttpException("This game has no calltime value");

        $user = User::findIdentity($userId);
        if (!$user) throw new HttpClientNotFoundException("User id#$userId not found");

        $gameUser = SocketGameUser::findOne(['game_id' => $game->id, 'user_id' => $user->id]);
        if (!$gameUser) throw new NotFoundHttpException( 'Could not find a gameUser');

        $gameUser->calltime = SmartDateTime::now()->add($calltimeValue * 60);
        $gameUser->save();

        return $gameUser;
    }
}
