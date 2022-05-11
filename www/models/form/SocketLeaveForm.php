<?php
namespace app\modules\socket\models\form;

use Yii;
use yii\base\Model;

use app\models\User;
use app\modules\socket\models\{
    SocketGameUser,
    SocketViewer
};

class SocketLeaveForm extends Model 
{
    public $user_id;
    public $game_id;
    public $is_bot;
    public $table_id;
    private $_user;
    private $_gameUser;
    private $_viewer;

    public function rules()
    {
        return [
            [['user_id', 'game_id', 'table_id'], 'required'],
            [['user_id', 'game_id', 'table_id'], 'integer'],
            [['is_bot'], 'boolean'],
            ['user_id', 'validateGameUser'],
            ['user_id', 'validateUser']
        ];
    }

    /**
     * Проверка участника игры
     * 
     * @return void
     */
    public function validateGameUser()
    {
        if (!$this->hasErrors()) {
            $this->_gameUser = SocketGameUser::find()
                ->where([
                    'user_id' => $this->user_id,
                    'game_id' => $this->game_id
                ])
                ->withRemoved()
                ->one();
            $this->_viewer = SocketViewer::findOne(['user_id' => $this->user_id, 'table_id' => $this->table_id]);
        
            if (!$this->_gameUser && !$this->_viewer) {
                $this->addError("user_id", "Участник игры или зритель не найден");
            }
        }
    }

    /**
     * Проверка пользователя
     * 
     * @return void
     */
    public function validateUser()
    {
        if (!$this->hasErrors()) {
            $this->_user = User::findOne(['id' => $this->user_id]);

            if (!$this->_user) {
                $this->addError("user_id", "Пользователя не существует");
            }
        }
    }

    /**
     * Получить зрителя
     * 
     * @return SocketViewer
     */
    public function getViewer(): ?SocketViewer
    {
        return $this->_viewer;
    }

    /**
     * Получить игрока
     * 
     * @return SocketGameUser
     */
    public function getGameUser(): ?SocketGameUser
    {
        return $this->_gameUser;
    }

    /**
     * Получить пользователя
     * 
     * @return User
     */
    public function getUser()
    {
        return $this->_user;
    }
}