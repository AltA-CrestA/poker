<?php
namespace app\modules\socket\controllers;

use Yii;
use yii\filters\{
    AccessControl,
    Cors
};
use yii\filters\auth\HttpBearerAuth;
use yii\rest\Controller;
use yii\web\{BadRequestHttpException, NotFoundHttpException, ServerErrorHttpException};
use yii\base\Module;

use app\modules\socket\models\{SocketClubTable, SocketGame, SocketGameUser, SocketViewer, User};
use app\modules\socket\models\form\{SocketViewerForm, SocketJoinForm};
use app\modules\socket\services\TableService;

class TableController extends Controller
{
    public $serializer = [
        'class' => 'app\modules\socket\components\Serializer',
        'collectionEnvelope' => 'items',
        'metaEnvelope' => 'pagination'
    ];

    private TableService $tableService;

    public function __construct($id, Module $module, $config = [], TableService $tableService)
    {
        $this->tableService = $tableService;

        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
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
                        'join',
                        'view',
                        'watch',
                        'auto-extend',
                        'register-queue',
                        'remove-queue',
                        'delete',
                        'access-join'
                    ],
                    'roles' => ['@']
                ]
            ]
        ];
        return $behaviors;
    }

    protected function verbs(): array
    {
        return [
            'options' => ['OPTIONS'],
            'join' => ['POST'],
            'watch' => ['POST'],
            'view' => ['GET'],
            'index' => ['GET'],
            'auto-extend' => ['PUT'],
            'delete' => ['DELETE'],
            'register-queue' => ['POST'],
            'remove-queue' => ['PUT'],
            'access-join' => ['GET']
        ];
    }

    public function actions(): array
    {
        $actions = parent::actions();
        $actions['options'] = ['class' => 'yii\rest\OptionsAction'];
        return $actions;
    }

    /**
     * @param $id - user_id
     * @return SocketClubTable[]
     * @throws NotFoundHttpException
     */
    public function actionIndex(int $id): array
    {
        $user = User::findIdentity($id);
        if (!$user) {
            throw new NotFoundHttpException("User id #$id not found");
        }

        return SocketClubTable::find()
            ->innerJoin('club c', 'club_table.club_id = c.id')
            ->innerJoin('club_member cm', 'cm.club_id = c.id')
            ->where(['cm.user_id' => $user->id])
            ->all();
    }

    /**
     * ???????????????? ????????
     * 
     * @param int $id
     * @return SocketClubTable
     * @throws NotFoundHttpException
     */
    public function actionView(int $id): SocketClubTable
    {
        return $this->tableService->getClubTable($id);
    }

    /**
     * ???????????????????????????? ?? ?????????? ????????????????
     * 
     * @param int $id - ?????????????????????????? ??????????
     * @return SocketGame|SocketViewerForm
     * @throws ServerErrorHttpException|NotFoundHttpException
     */
    public function actionWatch(int $id)
    {
        $socketClubTable = $this->tableService->getClubTable($id);
        $form = new SocketViewerForm(['table' => $socketClubTable]);

        if ($form->load(Yii::$app->request->post(), "") && $form->validate()) {
            $socketViewer = $this->tableService->getViewer($form);
            return $this->tableService->watch($socketViewer);
        }

        return $form;
    }

    /**
     * ???????????????? ???? ?????????????????????? ?????????????????????? ????????????????????????
     * ?????????????? ???? ???? ?? ???????????? ?????????? ???? ???????????? ????????????
     *
     * @param int $id
     * @param int $userId
     * @return SocketGameUser|null
     * @throws NotFoundHttpException
     */
    public function actionAccessJoin(int $id, int $userId): ?SocketGameUser
    {
        $this->tableService->getClubTable($id);
        $socketGameUser = SocketGameUser::findOne(['user_id' => $userId]);

        return $socketGameUser ?? null;
    }

    /**
     * ???????????????????????????? ?? ????????
     * 
     * @param int $id - ?????????????????????????? ??????????
     * @return SocketGame|SocketGameUser|SocketJoinForm
     * @throws ServerErrorHttpException|NotFoundHttpException
     */
    public function actionJoin(int $id)
    {
        $table = $this->tableService->getClubTable($id, true);
        $form = new SocketJoinForm([
            'table' => $table,
            'send_push' => Yii::$app->request->post('send_push', false)
        ]);

        if ($form->load(Yii::$app->request->post(), "") && $form->validate()) {
            $socketGameUser = $this->tableService->getGameUser($form);

            if (!$socketGameUser->validate()) return $socketGameUser; // ?????????????????????? ?????????????? ??????????????????, ?????????? ???????????????????????????? ?? ????????

            return $this->tableService->join($socketGameUser);
        }

        return $form;
    }

    /**
     * ?????????????????????????? ??????????
     *
     * @param integer $id
     * @return SocketClubTable
     * @throws NotFoundHttpException|ServerErrorHttpException|BadRequestHttpException
     */
    public function actionAutoExtend(int $id): SocketClubTable
    {
        $socketClubTable = $this->tableService->getClubTable($id);
        return $this->tableService->autoExtend($socketClubTable);
    }

    /**
     * ???????????? ?? ?????????????? (?? ???????????????? ??????????????)
     *
     * @param int $id
     * @return SocketJoinForm|SocketGameUser|SocketViewer
     * @throws ServerErrorHttpException|NotFoundHttpException
     */
    public function actionRegisterQueue(int $id)
    {
        $table = $this->tableService->getClubTable($id, true);
        $form = new SocketJoinForm([
            'table' => $table,
            'scenario' => SocketJoinForm::SCENARIO_QUEUE
        ]);

        if ($form->load(Yii::$app->request->post(), "") && $form->validate()) {
            $socketGameUser = $this->tableService->getGameUser($form);

            if (!$socketGameUser->validate()) return $socketGameUser; // ?????????????????????? ?????????????? ??????????????????, ?????????? ???????????? ?? ??????????????

            return $this->tableService->registerQueue($form);
        }

        return $form;
    }

    /**
     * ?????????? ???? ?????????????? (?? ???????????????? ??????????????)
     *
     * @param int $id
     * @return SocketJoinForm|SocketViewer
     * @throws ServerErrorHttpException|NotFoundHttpException
     */
    public function actionRemoveQueue(int $id)
    {
        $table = $this->tableService->getClubTable($id);
        $form = new SocketJoinForm([
            'table' => $table,
            'scenario' => SocketJoinForm::SCENARIO_QUEUE
        ]);

        if ($form->load(Yii::$app->request->post(), "") && $form->validate()) {
            return $this->tableService->removeQueue($form);
        }

        return $form;
    }

    /**
     * ???????????????? ??????????
     *
     * @param integer $id
     * @return boolean
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     */
    public function actionDelete(int $id): bool
    {
        $socketClubTable = $this->tableService->getClubTable($id, true);
        return $this->tableService->delete($socketClubTable);
    }
}
