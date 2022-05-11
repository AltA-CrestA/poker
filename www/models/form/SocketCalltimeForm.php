<?php
namespace app\modules\socket\models\form;

use Yii;
use yii\base\Model;

use app\models\User;
use app\modules\socket\models\{
    SocketGame,
    SocketGameUser
};

class SocketCalltimeForm extends Model 
{
    public $user_id;
    public $game_id;
    private ?SocketGameUser $_gameUser = null;

    public function rules()
    {
        return [
            [['user_id', 'game_id'], 'required'],
            [['user_id', 'game_id'], 'integer'],
            ['game_id', 'exist', 'targetClass' => SocketGame::class, 'targetAttribute' => 'id'],
            ['user_id', 'validateGameUser'],
        ];
    }

    /**
     * Проверка участника игры
     * 
     * @return void
     */

    public function validateGameUser()
    {
        if (!$this->_gameUser = SocketGameUser::find()->where(['user_id' => $this->user_id, 'game_id' => $this->game_id])->one()) {
            $this->addError("user_id", "Участник игры не найден #$this->user_id");
        }
    }

    /**
     * Получить участника игры
     * 
     * @return SocketGameUser
     */

    public function getGameUser()
    {
        return $this->_gameUser;
    }
}