<?php 
namespace app\modules\socket\services;

use app\traits\TransactionTrait;
use Exception;
use Yii;
use yii\web\{
    HttpException,
    NotFoundHttpException,
    ServerErrorHttpException,
    BadRequestHttpException
};
use yii\base\Component;
use yii\helpers\ArrayHelper;

use app\components\datetime\SmartDateTime;
use app\models\{
    GameUserStatus,
    GameUser,
    ClubMember,
    TransactionType,
    User
};
use app\modules\socket\models\{
    SocketGame,
    SocketGameRake,
    SocketGameStateLog,
    SocketGameUserLog,
    SocketGameUser,
    SocketGameWinner
};
use app\modules\socket\models\form\{
    SocketWatchForm,
    SocketLeaveForm,
    SocketGameForm
};

class GameService extends Component
{
    use TransactionTrait;

    /**
     * Получить игру
     * 
     * @param integer $id
     * @return SocketGame
     * @throws NotFoundHttpException
     */
    public function getGame(int $id): SocketGame
    {   
        if ($socketGame = SocketGame::findOne(['id' => $id])) return $socketGame;

        throw new NotFoundHttpException("Игра не найдена #$id");
    }

    /**
     * Получить игру
     * 
     * @param integer $id
     * @param array $with
     * @return SocketGame
     * @throws NotFoundHttpException
     */
    public function getGameWith(int $id, array $with = ['gameWinnersByItsGame', 'gameUsersByItsGame']): SocketGame
    {
        /** @var SocketGame $socketGame */
        if ($socketGame = SocketGame::find()->with($with)->where(['id' => $id])->one()) return $socketGame;

        throw new NotFoundHttpException("Игра не найдена #$id");
    }

    /**
     * Список игр пользователя
     * 
     * @param integer $user_id
     * @return SocketGame[]
     * @throws NotFoundHttpException
     */
    public function getUserGamesList(int $user_id): array
    {
        if (!User::findIdentity($user_id)) throw new NotFoundHttpException("Пользователь не найден #$user_id");

        return SocketGame::find()
                ->with('gameWinnersByItsGame')
                ->joinWith('gameUsersByItsGame gu')
                ->where(['user_id' => $user_id])
                ->all();
    }

    /**
     * Стать наблюдателем (зрителем)
     * 
     * @param SocketWatchForm $socketWatchForm
     * @param SocketGame $socketGame
     * @return SocketGame
     * @throws ServerErrorHttpException
     */
    public function watch(SocketWatchForm $socketWatchForm, SocketGame $socketGame): SocketGame
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $socketGameUser = $socketWatchForm->getGameUser();
            $socketViewer = $socketWatchForm->getViewer();

            $socketViewer->is_removed = false;
            $socketViewer->save();

            $socketGameUser->scenario = $socketGameUser::SCENARIO_LEAVE;
            $socketGameUser->game_user_status_id = GameUserStatus::BECAME_VIEWER;
            $socketGameUser->is_removed = true;
            $socketGameUser->save();

            $transaction->commit();
            return $socketGame;
        } catch(Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException($th->getMessage());
        }
    }

    /**
     * Покинуть игру
     * 
     * @param SocketLeaveForm $socketLeaveForm
     * @param SocketGame $socketGame
     * @return SocketGame
     * @throws ServerErrorHttpException|BadRequestHttpException|HttpException
     */
    public function leave(SocketLeaveForm $socketLeaveForm, SocketGame $socketGame): SocketGame
    {
        $socketGameUser = $socketLeaveForm->getGameUser();
        $socketViewer = $socketLeaveForm->getViewer();

        if ($socketGameUser && $socketGameUser->game_user_status_id == GameUserStatus::ABANDONED && $socketViewer->is_removed) {
            throw new BadRequestHttpException("Пользователь уже покинул игру");
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ($socketGameUser) {
                $socketGameUser->scenario = $socketGameUser::SCENARIO_LEAVE;
                $socketGameUser->game_user_status_id = GameUserStatus::ABANDONED;
                $socketGameUser->remove();

                $this->addTransaction(
                    TransactionType::CLUB_MEMBER_BALANCE,
                    $socketGameUser->balance_current,
                    $socketGameUser->user_id,
                    GameUser::class,
                    ClubMember::class,
                    $socketGameUser->id,
                    $socketGameUser->clubMember->id
                );
            }
            
            if ($socketViewer) $socketViewer->remove();

            $transaction->commit();
            return $socketGame;
        } catch(Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException($th->getMessage());
        }
    }

    /**
     * Удалить игру
     * 
     * @param SocketGame $socketGame
     * @return boolean
     * @throws ServerErrorHttpException
     */
    public function remove(SocketGame $socketGame): bool
    {   
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $socketGame->remove();
            $transaction->commit();

            return true;
        } catch(Exception $th) {
            $transaction->rollBack();
            Yii::error('Ошибка удаления игры: ' . $th->getMessage(), 'akpoker_telegram_log');
            throw new ServerErrorHttpException("Что-то пошло не так");
        }
    }

    /**
     * Обновление игры
     * 
     * @param SocketGameForm $socketGameForm
     * @param SocketGame $socketGame
     * @return SocketGame
     * @throws ServerErrorHttpException
     */
    public function update(SocketGameForm $socketGameForm, SocketGame $socketGame): SocketGame
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            /**
             * @var SocketGameStateLog $socketGameStateLog
             * @var SocketGame $socketGame
             */
            list($socketGameStateLog, $socketGame) = $this->saveState($socketGameForm->state, $socketGame);
            $this->saveUsers($socketGameStateLog, $socketGameForm->users);
            if ($socketGameStateLog->id && $socketGameStateLog->state_type == SocketGameStateLog::GAME_STATE_END) {
                $this->saveWinners($socketGameForm->winners);
                $this->saveRake($socketGameStateLog, $socketGameForm->rake);
            }
            
            $socketGame->deck = $socketGameForm->deck;
            $socketGame->updated_at = SmartDateTime::now();
            $socketGame->save();

            $transaction->commit();
            return $socketGame;
        } catch(Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException("Что-то пошло не так: " . $th->getMessage());
        }
    }

    /**
     * Сохранение состояния игры
     *
     * @param SocketGameStateLog $socketGameStateLog
     * @param SocketGame $socketGame
     * @return array[SocketGameStateLog,SocketGame]
     * @throws Exception
     */
    private function saveState(SocketGameStateLog $socketGameStateLog, SocketGame $socketGame): array
    {
        if (!SocketGameStateLog::find()->where([
            'game_id' => $socketGame->id, 
            'game_cycle_number' => $socketGameStateLog->game_cycle_number,
            'step_number' => $socketGameStateLog->step_number
        ])->exists()) {
            $socketGameStateLog->user_last_turn_time = SmartDateTime::create($socketGameStateLog->user_last_turn_time, SmartDateTime::FORMAT_ISO, null, true);
            $socketGameStateLog->save();

            if ($socketGameStateLog->state_type == SocketGameStateLog::GAME_STATE_NEW) {
                $socketGame->status = SocketGame::GAME_STATUS_NEW;
            } elseif ($socketGameStateLog->state_type == SocketGameStateLog::GAME_STATE_END) {
                $socketGame->status = SocketGame::GAME_STATUS_STOP;
            } else {
                $socketGame->status = SocketGame::GAME_STATUS_RUN;
            }

            if ($socketGameStateLog->is_game_started) $socketGame->setTableActiveTo();
        }
        
        return [
            $socketGameStateLog,
            $socketGame
        ];
    }

    /**
     * Сохраниение обнволенной информации о пользователях
     *
     * @param SocketGameUser[] $socketGameUsers
     * @param SocketGameStateLog $socketGameStateLog
     * @return void
     * @throws Exception
     */
    private function saveUsers(SocketGameStateLog $socketGameStateLog, array $socketGameUsers = []): void
    {
        foreach ($socketGameUsers as $key => $socketGameUser) {
            if ($socketGameUser->save(false)) {
                foreach ($socketGameUser->action_logs as $actionLog) {
                    if (!SocketGameUserLog::find()->where([
                        'game_user_id' => $socketGameUser->id,
                        'game_cycle_number' => ArrayHelper::getValue($actionLog, 'game_cycle_number'),
                        'step_number' => ArrayHelper::getValue($actionLog, 'step_number')
                    ])->exists()) {
                        $socketGameUserLog = new SocketGameUserLog([
                            'game_user_id' => $socketGameUser->id,
                            'position' => $socketGameUser->position,
                            'position_name' => $socketGameUser->position_name,
                            'balance_current' => $socketGameUser->balance_current,
                            'is_fold' => $socketGameUser->is_fold,
                            'is_show_cards' => $socketGameUser->is_show_cards,
                            'cards_title' => $socketGameUser->cards_title,
                            'bet' => $socketGameUser->bet
                        ]);

                        if ($socketGameStateLog->id && $socketGameStateLog->user_id == $socketGameUser->user_id) {
                            $socketGameUserLog->game_state_log_id = $socketGameStateLog->id;
                        }

                        if ($socketGameUserLog->load($actionLog, "") && $socketGameUserLog->validate()) {
                            $socketGameUserLog->save(false);
                        }
                    }
                }

                if ($socketGameStateLog->state_type == SocketGameStateLog::GAME_STATE_END) {
                    $this->createActionLogs($socketGameStateLog, $socketGameUser, $key);
                }
            }
        }
    }

    /**
     * Сохранение победителей
     * 
     * @param SocketGameWinner[] $socketGameWinners
     * @return void
     */
    private function saveWinners(array $socketGameWinners = []): void
    {
        foreach ($socketGameWinners as $socketGameWinner) {
            $socketGameWinner->save();
        }
    }

    /**
     * Сохранение комисссии (rake)
     *
     * @param SocketGameRake[] $socketGameRakes
     * @param SocketGameStateLog $socketGameStateLog
     * @return void
     */
    private function saveRake(SocketGameStateLog $socketGameStateLog, array $socketGameRakes = []): void
    {
        if ($socketGameStateLog->id) {
            foreach ($socketGameRakes as $socketGameRake) {
                $socketGameRake->game_state_log_id = $socketGameStateLog->id;
                $socketGameRake->save();
            }
        }
    }

    /**
     * TODO Временное рещение для создание gameUserLog на стадии GAME_STATE_END
     * 
     * @param SocketGameStateLog $socketGameStateLog
     * @param SocketGameUser $socketGameUser
     * @param int $key
     * @return void
     */
    private function createActionLogs(SocketGameStateLog $socketGameStateLog, SocketGameUser $socketGameUser, int $key): void
    {
        if ($key > 0) {
            $anotherModel = new SocketGameStateLog();
            $anotherModel->setAttributes($socketGameStateLog->attributes);
            $anotherModel->save();
            $socketGameStateLog = $anotherModel;
        }

        $socketGameUserLog = new SocketGameUserLog([
            'game_user_id' => $socketGameUser->id,
            'position' => $socketGameUser->position,
            'position_name' => $socketGameUser->position_name,
            'balance_current' => $socketGameUser->balance_current,
            'is_fold' => $socketGameUser->is_fold,
            'is_show_cards' => $socketGameUser->is_show_cards,
            'cards_title' => $socketGameUser->cards_title,
            'bet' => $socketGameUser->bet,
            'amount' => 0,
            'step_number' => $socketGameStateLog->step_number + $key,
            'game_cycle_number' => $socketGameStateLog->game_cycle_number,
            'bet_cycle_number' => $socketGameStateLog->bet_cycle_number,
            'name' => 'END',
            'title' => 'end',
            'game_state_log_id' => $socketGameStateLog->id
        ]);
        
        if ($socketGameUserLog->validate()) $socketGameUserLog->save();
    }
}   