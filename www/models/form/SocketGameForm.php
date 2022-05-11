<?php
namespace app\modules\socket\models\form;

use Exception;
use Yii;
use yii\base\Model;
use yii\helpers\{ArrayHelper, Json};

use app\modules\socket\models\{
    SocketGameStateLog,
    SocketGameUser,
    SocketGameRake,
    SocketGameWinner
};

/**
 * Class SocketGameForm
 *
 * @property SocketGameWinner[] $winners
 * @property SocketGameRake[] $rake
 * @property SocketGameUser[] $users
 * @property array $deck
 * @property SocketGameStateLog $state
 */
class SocketGameForm extends Model 
{
    public int $game_id;
    public int $club_id;
    public ?SocketGameStateLog $state = null;
    public ?array $users = []; 
    public ?array $winners = [];
    public ?array $deck = [];
    public ?array $rake = [];

    public function init()
    {
        $this->setState();
        $this->setUsers();
        $this->setWinners();
        $this->setRake();

        $this->deck = Yii::$app->request->post("deck");
    }

    public function rules(): array
    {
        return [
            [['game_id', 'club_id'], 'required'],
            [['game_id', 'club_id'], 'integer'],
            [['state', 'users', 'winners', 'deck'], 'safe'],
            ['state', 'validateState'],
            ['winners', 'validateWinners'],
            ['rake', 'validateRake'],
            ['users', 'validateUsers']
        ];
    }

    public function afterValidate()
    {
        parent::afterValidate();
        if ($errors = $this->getErrors()) $this->logErrors($errors);
    }

    /**
     * Проверка stet
     * 
     * @return void
     */
    public function validateState(): void
    {
        if ($this->state && !$this->state->validate()) {
            $this->addError("state", Json::encode($this->state->errors));
        }
    }

    /**
     * Проверка winners
     * 
     * @return void
     */
    public function validateWinners(): void 
    {
        if ($this->winners && !SocketGameWinner::validateMultiple($this->winners)) {
            $errors = [];

            foreach ($this->winners as $key => $winner) {
                if ($winnerErrors = $winner->errors) $errors[$key] = $winnerErrors;
            }

            if ($errors) $this->addError("winners", Json::encode($errors));
        }
    }

    /**
     * Проверка rake
     * 
     * @return void
     */
    public function validateRake(): void 
    {
        if ($this->rake && !SocketGameRake::validateMultiple($this->rake, ['user_id', 'club_id', 'game_id', 'amount'])) {
            $errors = [];

            foreach ($this->rake as $key => $rake) {
                if ($rakeErrors = $rake->errors) $errors[$key] = $rakeErrors;
            }

            if ($errors) $this->addError("rake", Json::encode($errors));
        }
    }

    /**
     * Проверка участников игры
     * 
     * @return void
     */
    public function validateUsers(): void
    {
        if ($this->users && !SocketGameUser::validateMultiple($this->users, ['game_id', 'user_id'])) {
            $errors = [];

            foreach ($this->users as $key => $user) {
                if ($userErrors = $user->errors) $errors[$key] = $userErrors;
            }

            if ($errors) $this->addError("users", Json::encode($errors));
        }
    }

    /**
     * Установить в свойство состояние игры (SocketGameStateLog)
     * 
     * @return void
     */
    protected function setState(): void
    {
        $this->state = new SocketGameStateLog(['game_id' => $this->game_id]);
        $this->state->load(Yii::$app->request->post("state"), "");
    }

    /**
     * Установить в свойство участников игры
     *
     * @return void
     * @throws Exception
     */
    protected function setUsers(): void
    {
        foreach (Yii::$app->request->post("users") ?? [] as $user) {
            if ($socketGameUser = SocketGameUser::find()->where(['game_id' => $this->game_id, 'user_id' => ArrayHelper::getValue($user, 'id')])->one()) {
                $socketGameUser->load($user, "");
                $this->users[] = $socketGameUser;
            }
        }
    }

    /**
     * Установить в свойство победителй (SocketGameWinner)
     * 
     * @return void
     */
    protected function setWinners(): void
    {
        foreach (Yii::$app->request->post("winners") ?? [] as $winner) {
            $this->winners[] = new SocketGameWinner(['game_id' => $this->game_id]);
        }
        SocketGameWinner::loadMultiple($this->winners, Yii::$app->request->post("winners"), "");
    }

    /**
     * Установить в свойство комиссии игроков
     * 
     * @return void
     */
    protected function setRake(): void
    {
        foreach (Yii::$app->request->post("rake") ?? [] as $rake) {
            $this->rake[] = new SocketGameRake(['game_id' => $this->game_id, 'club_id' => $this->club_id]);
        }
        SocketGameRake::loadMultiple($this->rake, Yii::$app->request->post("rake"), "");
    }

    /**
     * @param array $errors
     * @return void
     */
    public function logErrors(array $errors = []): void
    {
        $errorMsg = "";
        $errorMsg .= "game/update\n";
        $errorMsg .= "Validation errors:\n" . print_r($errors, true);
        $errorMsg .= "\nRequest body:\n" . Json::encode(Yii::$app->request->bodyParams);

        Yii::error(
            $errorMsg,
            'akpoker_telegram_log'
        );
    }
}