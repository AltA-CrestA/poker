<?php
namespace app\modules\socket\models\form;

use app\models\LobbyTableType;
use app\modules\socket\models\User;
use yii\base\Model;

class SocketLobbyJoinForm extends Model
{
    public $user_id;
    public $lobby_type_id;
    public $ip;
    public $latitude;
    public $longitude;
    private $_user;
    private $_lobby_type;

    public function rules()
    {
        return [
            [['user_id', 'lobby_type_id'], 'required'],
            [['user_id', 'lobby_type_id'], 'integer'],
            ['ip', 'string'],
            [['longitude', 'latitude'], 'number'],
            ['lobby_type_id', 'validateLobbyType'],
            ['user_id', 'validateUser'],
        ];
    }

    public function validateLobbyType()
    {
        if (!$this->hasErrors()) {
            if (!$this->_lobby_type = LobbyTableType::findOne(['id' => $this->lobby_type_id])) {
                $this->addError('lobby_type_id', 'Указанного вида лобби не существует');
            }
        }
    }

    public function validateUser()
    {
        if (!$this->hasErrors()) {
            if (!$this->_user = User::findOne(['id' => $this->user_id])) {
                $this->addError('user_id', 'Указанного пользователя не существует');
            } elseif ($this->_user->coins < $this->_lobby_type->buy_in_min) {
                $this->addError('user_id', 'Недостаточно баланса для входа за стол. Необходимо: '.$this->_lobby_type->buy_in_min.'. Ваш баланс: '.$this->_user->coins);
            }
        }
    }

    /**
     * Получить пользователя
     *
     * @return User
     */
    public function getUser(): User
    {
        return $this->_user;
    }

    public function getLobbyType(): LobbyTableType
    {
        return $this->_lobby_type;
    }
}