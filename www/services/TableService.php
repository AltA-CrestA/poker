<?php 
namespace app\modules\socket\services;

use app\models\ClubMember;
use app\models\GameUser;
use app\models\TransactionType;
use app\traits\TransactionTrait;
use Exception;
use Yii;
use yii\web\{HttpException, NotFoundHttpException, ServerErrorHttpException, BadRequestHttpException};
use yii\base\Component;
use yii\helpers\ArrayHelper;

use app\modules\socket\models\{
    SocketGame,
    SocketClubTable,
    SocketViewer,
    SocketGameUser
};
use app\modules\socket\models\form\{
    SocketViewerForm,
    SocketJoinForm
};
use app\models\GameUserStatus;

class TableService extends Component
{
    use TransactionTrait;

    /**
     * Получить стол по идентификатору
     *
     * @param int $id
     * @param bool $withGame Необязательный параметр, искать стол с игрой
     * @return SocketClubTable
     * @throws NotFoundHttpException
     */
    public function getClubTable(int $id, bool $withGame = false): SocketClubTable
    {
        $query = SocketClubTable::find()
            ->where(['id' => $id]);
        if ($withGame) $query->with('game');

        /** @var SocketClubTable $clubTable */
        $clubTable = $query->one();
        if (!$clubTable) throw new NotFoundHttpException("Стол не найден #$id");
        elseif ($withGame && !$clubTable->game) throw new NotFoundHttpException("За столом нет активной игры #$id");

        return $clubTable;
    }

    /**
     * Получить зрителя
     * Метод смотрит, был ли указанный пользователь за данным столом зрителем.
     * Если нет, то создаст новую сущность зритель
     * 
     * @param SocketViewerForm $socketViewerForm
     * @return SocketViewer|null
     */
    public function getViewer(SocketViewerForm $socketViewerForm): SocketViewer
    {
        /** @var SocketViewer $socketViewer */
        $socketViewer = SocketViewer::find()
            ->where([
                'club_id' => $socketViewerForm->club_id,
                'user_id' => $socketViewerForm->user_id,
                'table_id' => $socketViewerForm->table_id
            ])
            ->withRemoved()
            ->one();

        if ($socketViewer) {
            $socketViewer->is_removed = false;
        } else {
            $socketViewer = new SocketViewer($socketViewerForm->getAttributes(null, ['table']));
        }
        return $socketViewer;
    }

    /**
     * Получить игрока по данным модели SocketJoinForm.
     * Данный метод проверяет, существует ли данный игрок и
     * при каких обсоятельствах он покинул данный стол(игру)
     *
     * В противном случае создаём нового игрока
     *
     * @param SocketJoinForm $socketJoinForm
     * @return SocketGameUser
     */
    public function getGameUser(SocketJoinForm $socketJoinForm): SocketGameUser
    {   
        $game_id = $socketJoinForm->table->game->id;

        /** @var SocketGameUser $socketGameUser */
        $socketGameUser = SocketGameUser::find()
            ->where(['game_id' => $game_id, 'user_id' => $socketJoinForm->user_id])
            ->withRemoved()
            ->one();

        if ($socketGameUser) {
            switch ($socketGameUser->game_user_status_id) {
                case GameUserStatus::ABANDONED:
                    if ($socketGameUser->balance_left > 0) {
                        $socketGameUser->scenario = SocketGameUser::SCENARIO_COMEBACK;
                    } else {
                        $socketGameUser->scenario = SocketGameUser::SCENARIO_NEW;
                    }
                    break;
                case GameUserStatus::DISCONNECTED:
                    $socketGameUser->scenario = SocketGameUser::SCENARIO_RECONNECT;
                    break;
                case GameUserStatus::BECAME_VIEWER:
                    if ($socketGameUser->balance_current > 0) {
                        $socketGameUser->scenario = SocketGameUser::SCENARIO_RECONNECT;
                    } else {
                        $socketGameUser->scenario = SocketGameUser::SCENARIO_NEW;
                    }
                    break;
            }

            $socketGameUser->position = $socketJoinForm->position;
            $socketGameUser->ip = $socketJoinForm->ip;
            $socketGameUser->latitude = $socketJoinForm->latitude;
            $socketGameUser->longitude = $socketJoinForm->longitude;
        } else {
            $socketGameUser = new SocketGameUser(ArrayHelper::merge($socketJoinForm->getAttributesForGameUser(), ['game_id' => $game_id]));
            $socketGameUser->scenario = SocketGameUser::SCENARIO_NEW;
        }

        $socketGameUser->game_user_status_id = GameUserStatus::IN_GAME;
        return $socketGameUser;
    }

    /**
     * Зарегистрировать зрителя в очередь игры
     *
     * @param SocketJoinForm $form
     * @return SocketViewer
     * @throws ServerErrorHttpException
     */
    public function registerQueue(SocketJoinForm $form): SocketViewer
    {
        $existingQueue = SocketViewer::find()
            ->where([
                'club_id' => $form->club_id,
                'table_id' => $form->table_id
            ])
            ->max('queue_number');

        $socketViewer = $form->getViewer();
        $socketViewer->queue_number = ++$existingQueue;
        return $this->saveViewer($socketViewer);
    }

    /**
     * Транзакция БД на сохранения данных зрителя
     *
     * @param SocketViewer $socketViewer
     * @return SocketViewer
     * @throws ServerErrorHttpException
     */
    private function saveViewer(SocketViewer $socketViewer): SocketViewer
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $socketViewer->save();
            $transaction->commit();
            return $socketViewer;
        } catch (Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException($th->getMessage());
        }
    }

    /**
     * Удалить зрителя из очереди
     *
     * @param SocketJoinForm $form
     * @return SocketViewer
     * @throws ServerErrorHttpException
     */
    public function removeQueue(SocketJoinForm $form): SocketViewer
    {
        $socketViewer = $form->getViewer();
        $socketViewer->queue_number = null;

        return $this->saveViewer($socketViewer);
    }

    /**
     * Создать зрителя
     * Если игры за указанным столом нет,
     * то первый зритель создаст её при подключении
     *
     * @param SocketViewer $socketViewer
     * @return SocketGame
     * @throws ServerErrorHttpException
     */
    public function watch(SocketViewer $socketViewer): SocketGame
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $socketViewer->save();
            $socketGame = $socketViewer->game;
            if (!$socketGame) {
                $socketGame = $socketViewer->clubTable->createGame();
            }
            $transaction->commit();

            return $socketGame;
        } catch (Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException($th->getMessage());
        }
    }

    /**
     * Присоедениться к игре (сесть за стол)
     *
     * @param SocketGameUser $socketGameUser
     * @return SocketGame
     * @throws ServerErrorHttpException
     */
    public function join(SocketGameUser $socketGameUser): SocketGame
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $socketGameUser->is_removed = false;
            $socketGameUser->save();

            if ($socketGameUser->scenario === $socketGameUser::SCENARIO_NEW || $socketGameUser->scenario === $socketGameUser::SCENARIO_COMEBACK) {
                $this->addTransaction(
                    TransactionType::GAMEUSER_BALANCE,
                    $socketGameUser->requiredBalance,
                    $socketGameUser->user_id,
                    ClubMember::class,
                    GameUser::class,
                    $socketGameUser->clubMember->id,
                    $socketGameUser->id
                );
            }
            $socketGameUser->viewer->remove();

            $transaction->commit();
            return $socketGameUser->game;
        } catch (Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException($th->getMessage());
        }
    }

    /**
     * Автопродление стола
     *
     * @param SocketClubTable $socketClubTable
     * @return SocketClubTable
     * @throws BadRequestHttpException|ServerErrorHttpException
     */
    public function autoExtend(SocketClubTable $socketClubTable): SocketClubTable
    {
        if (!$socketClubTable->is_auto_renew)
            throw new BadRequestHttpException("У данного стола #$socketClubTable->id отключено автопродление.");

        if ($socketClubTable->auto_renew_current_count >= $socketClubTable->auto_renew_max_count)
            throw new BadRequestHttpException("Превышен лимит доступных автопродлений указанных в настройках стола.");

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $socketClubTable->autoExtend();
            $socketClubTable->save();

            $transaction->commit();
            return $socketClubTable;
        } catch (Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException($th->getMessage());
        }
    }

    /**
     * Удаление стола со всеми игроками и зрителями.
     * Если была настройка с автооткрытием, то создаст стол с идентичными настройками
     *
     * @param SocketClubTable $socketClubTable
     * @return boolean
     * @throws ServerErrorHttpException
     */
    public function delete(SocketClubTable $socketClubTable): bool
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $socketGame = $socketClubTable->game;
            $gameUsers = $socketGame->getGameUsersByItsGame()
                ->andWhere(['!=', 'game_user_status_id', GameUserStatus::ABANDONED])
                ->withRemoved()
                ->all();
            foreach ($gameUsers ?? [] as $gameUser) {
                $this->removeGameUser($gameUser);
            }

            foreach ($socketGame->gameViewersByItsGame ?? [] as $viewer) {
                $viewer->remove();
            }
            $socketGame->remove();

            if ($socketClubTable->is_auto_recreate) {
                $newTable = new SocketClubTable(
                    $socketClubTable->getAttributes(null, ['id', 'created_at', 'updated_at', 'active_to', 'started_at', 'current_users_count'])
                );
                $newTable->save();
            }

            $socketClubTable->remove();

            $transaction->commit();
            return true;
        } catch (Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException($th->getMessage());
        }
    }

    /**
     * Удалить всех игроков которые не покинули игру за столом
     *
     * @param SocketGameUser $gameUser
     * @throws HttpException
     */
    private function removeGameUser(SocketGameUser $gameUser)
    {
        $gameUser->scenario = $gameUser::SCENARIO_LEAVE;
        $gameUser->game_user_status_id = GameUserStatus::ABANDONED;
        $gameUser->remove();

        $this->addTransaction(
            TransactionType::CLUB_MEMBER_BALANCE,
            $gameUser->balance_current,
            $gameUser->user_id,
            GameUser::class,
            ClubMember::class,
            $gameUser->id,
            $gameUser->clubMember->id
        );
    }
}