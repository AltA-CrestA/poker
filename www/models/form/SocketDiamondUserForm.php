<?php
namespace app\modules\socket\models\form;

use app\models\{
    Game,
    TransactionType,
    User
};

use app\modules\socket\models\SocketGameUser;
use app\traits\TransactionTrait;
use yii\base\Model;

class SocketDiamondUserForm extends Model
{
    use TransactionTrait;

    private const TIME_BANK_COST = 10;
    private const RABBIT_COST = 10;
    public const SCENARIO_TIME_BANK = 'time_bank';
    public const SCENARIO_RABBIT = 'rabbit';

    public $game_id;
    public $user_id;
    private int $type;
    private int $cost;
    private ?SocketGameUser $_gameUser;

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_TIME_BANK] = ['game_id', 'user_id'];
        $scenarios[self::SCENARIO_RABBIT] = ['game_id', 'user_id'];

        return $scenarios;
    }

    // TODO: Когда реализуют VIP-статус, делать проверку при SCENARIO_RABBIT
    public function rules()
    {
        return [
            [['game_id', 'user_id'], 'required'],
            [['game_id', 'user_id'], 'integer'],
            ['user_id', 'validateGameUser'],
        ];
    }

    public function beforeValidate(): bool
    {
        if ($this->scenario == self::SCENARIO_TIME_BANK) {
            $this->type = TransactionType::TIMEBANK_BY_DIAMONDS;
            $this->cost = self::TIME_BANK_COST;
        } elseif ($this->scenario == self::SCENARIO_RABBIT) {
            $this->type = TransactionType::RABBIT_BY_DIAMONDS;
            $this->cost = self::RABBIT_COST;
        }

        return true;
    }

    /**
     * Проверка участника игры
     *
     * @return void
     */
    public function validateGameUser(): void
    {
        if (!$this->hasErrors()) {
            if (!$this->_gameUser = SocketGameUser::find()->with('user')->where(['user_id' => $this->user_id, 'game_id' => $this->game_id])->one()) {
                $this->addError("user_id", "Участник игры не найден #$this->user_id");
            } elseif ($this->_gameUser->user->crystals < $this->cost) {
                $this->addError("user_id", "Недостаточно кристалов. Необходимо: ".$this->cost.". Ваш баланс кристалов: ".$this->_gameUser->user->crystals);
            }
        }
    }

    public function afterValidate()
    {
        parent::afterValidate();
        if (!$this->hasErrors()) {
            $this->addTransaction(
                $this->type,
                $this->cost,
                $this->user_id,
                User::class,
                Game::class,
                $this->user_id,
                $this->game_id
            );
        }
    }

    /**
     * Получить данные игрока
     *
     * @return SocketGameUser
     */
    public function getGameUser(): SocketGameUser
    {
        return $this->_gameUser;
    }
}