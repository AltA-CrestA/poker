<?php
namespace app\modules\socket\models\form;

use Yii;
use yii\base\Model;

use app\models\GameUserStatus;
use app\modules\socket\models\{
    SocketGame,
    SocketGameUser
};

class SocketGameUserStatusForm extends Model 
{
    public $game_id;
    public $user_id;
    public $game_user_status_id;
    private $_gameUser;

    public function rules()
    {
        return [
            [['game_id', 'user_id'], 'required'],
            [['game_id', 'user_id', 'game_user_status_id'], 'integer'],
            ['game_user_status_id', 'in', 'range' => [GameUserStatus::OUT_GAME, GameUserStatus::DISCONNECTED]],
            ['game_id', 'exist', 'targetClass' => SocketGame::class, 'targetAttribute' => 'id'],
            ['user_id', 'validateGameUser'],
        ];
    }

    /**
     * Проверка участника игры
     * 
     * @return void
     */

    public function validateGameUser(): void
    {   
        if (!$this->hasErrors()) {
            if (!$this->_gameUser = SocketGameUser::find()->where(['user_id' => $this->user_id, 'game_id' => $this->game_id])->one()) {
                $this->addError("user_id", "Участник игры не найден #$this->user_id");
            }
        }
    }

    /**
     * Получить игрока
     * 
     * @return SocketGameUser
     */

    public function getGameUser()
    {
        return $this->_gameUser;
    }
}