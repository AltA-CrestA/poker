<?php 
namespace app\modules\socket\services;

use Exception;
use Yii;
use yii\web\{
    NotFoundHttpException,
    ServerErrorHttpException,
    BadRequestHttpException
};
use yii\base\Component;

use app\modules\socket\models\{SocketBuyIn, SocketGame, SocketGameUser};
use app\modules\socket\models\form\{SocketCalltimeForm, SocketGameUserStatusForm};
use app\components\datetime\SmartDateTime;

class GameUserService extends Component
{
    /**
     * Получить заявку на buy-in
     * 
     * @param integer $game_id
     * @param integer $request_id
     * @return SocketBuyIn
     * @throws NotFoundHttpException
     */
    public function getBuyIn(int $game_id, int $request_id): SocketBuyIn
    {
        if ($socketBuyIn = SocketBuyIn::findOne(['id' => $request_id, 'game_id' => $game_id])) return $socketBuyIn;

        throw new NotFoundHttpException("Заявка на buy-in не найдена #$request_id");
    }

    /**
     * Бай-ин
     * 
     * @param SocketBuyIn $socketBuyIn
     * @param SocketGame $socketGame
     * @return SocketBuyIn
     * @throws ServerErrorHttpException
     */
    public function buyIn(SocketBuyIn $socketBuyIn, SocketGame $socketGame): SocketBuyIn
    {
        $transaction = Yii::$app->db->beginTransaction();        
        try {
            if ($socketGame->table->is_buy_in_allowed && !$socketBuyIn->isClubManagement()) {
                $socketBuyIn->save();
            } else {
                $socketBuyIn->saveTransaction();
            }

            $transaction->commit();
            return $socketBuyIn;
        } catch(Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException("Что-то пошло не так");
        }
    }

    /**
     * Calltime
     *
     * @param SocketCalltimeForm $socketCalltimeForm
     * @param SocketGame $socketGame
     * @return SocketGameUser
     * @throws BadRequestHttpException
     * @throws ServerErrorHttpException
     */
    public function calltime(SocketCalltimeForm $socketCalltimeForm, SocketGame $socketGame): SocketGameUser
    {
        $socketGameUser = $socketCalltimeForm->getGameUser();

        if (!$socketGame->table->is_calltime) throw new BadRequestHttpException("Для данной игры отключен calltime");
        if (!$calltimeValue = $socketGame->table->calltime_value) throw new BadRequestHttpException("В настройках игры не указано значение calltime");

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $socketGameUser->calltime = SmartDateTime::now()->add($calltimeValue * 60);
            $socketGameUser->save();
    
            $transaction->commit();
            return $socketGameUser;
        } catch(Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException("Что-то пошло не так");
        }
    }

    /**
     * Сохранить состояние игрока
     *
     * @param SocketGameUserStatusForm $socketGameUserStatusForm
     * @return SocketGameUser
     * @throws ServerErrorHttpException
     */
    public function saveState(SocketGameUserStatusForm $socketGameUserStatusForm): SocketGameUser
    {
        $transaction = Yii::$app->db->beginTransaction();        
        try {
            $socketGameUser = $socketGameUserStatusForm->getGameUser();
            $socketGameUser->game_user_status_id = $socketGameUserStatusForm->game_user_status_id;
            $socketGameUser->save(false);

            $transaction->commit();
            return $socketGameUser;
        } catch(Exception $th) {
            $transaction->rollBack();
            throw new ServerErrorHttpException("Что-то пошло не так. Неизвестная ошибка смены статуса игрока.");
        }
    }
}   