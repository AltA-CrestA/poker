<?php
namespace app\modules\socket\models;

use Yii;
use yii\helpers\ArrayHelper;

use app\modules\oldadmin\helpers\ARHelper;
use app\services\notification\transport\FireBasePushTransport;
use app\models\{
    Game,
    GameUser,
    GameUserBuyIn,
    TransactionType,
    ClubMember
};

/**
 * Class SocketBuyIn
 *
 * @package app\modules\socket\models
 */
class SocketBuyIn extends GameUserBuyIn
{
    use \app\traits\TransactionTrait;

    public function fields()
    {
        $removeFields = [
            'id',
            'game_id',
            'user_id',
            'amount',
            'initiator',
            'status',
        ];

        $addFields = [
            'request_id' => fn (SocketBuyIn $socketBuyIn) => $socketBuyIn->id ?? null,
            'game_user' => fn (SocketBuyIn $socketBuyIn) =>
            (
                !$socketBuyIn->game->table->is_buy_in_allowed
                ||
                $socketBuyIn->isClubManagement()
            ) ? $socketBuyIn->gameUser : null
        ];

        return ARHelper::overrideFields(parent::fields(), $addFields, $removeFields);
    }

    public function rules(): array
    {
        return ArrayHelper::merge(parent::rules(), [
            [['user_id', 'game_id', 'amount'], 'required'],
            [['user_id', 'game_id', 'amount'], 'integer'],
            ['game_id', 'exist', 'targetClass' => Game::class,
                'targetAttribute' => 'id',
                'message' => 'Указанной игры не существует',
            ],
            ['user_id', 'exist', 'targetClass' => User::class,
                'targetAttribute' => 'id',
                'message' => 'Указанного пользователя не существует',
            ],
            ['user_id', 'exist', 'targetClass' => GameUser::class,
                'targetAttribute' => ['user_id' => 'user_id', 'game_id' => 'game_id'],
                'message' => 'По указанному user_id не существует игрока'
            ],
            ['amount', 'validateBuyIn'],
            ['amount', 'validateBalance'],
        ]);
    }

    /**
     * Проверка суммы buy-in
     * 
     * @return void
     */
    public function validateBuyIn()
    {
        if ($this->amount < $this->game->table->buy_in_min || $this->amount > $this->game->table->buy_in_max) {
            $this->addError("amount", "Недопустимая сумма бай-ина");
        }
    }

    /**
     * Проверка баланса участника клуба
     * 
     * @return void
     */
    public function validateBalance()
    {
        $currentBalance = $this->clubMember->balance;
        $requiredBalance = $this->amount;
        if ($currentBalance < $requiredBalance) {
            $this->addError("amount", "Недопустимая сумма бай-ина. Ваш баланс: $currentBalance. Запрашиваемая сумма: $requiredBalance");
        }
    }

    /**
     * Сохранение транзакции
     * 
     * @return void
     */
    public function saveTransaction()
    {
        $this->addTransaction(
            TransactionType::BUY_IN,
            $this->amount,
            $this->gameUser->user_id,
            ClubMember::class,
            GameUser::class,
            $this->clubMember->id,
            $this->gameUser->id
        );
    }

    /**
     * Проврка роли
     * 
     * @return boolean
     */
    public function isClubManagement(): bool
    {   
        $clubMember = $this->clubMember;
        if ($clubMember->is_manager == true || $clubMember->is_owner == true) {
            return true;
        }
        return false;
    }

    private function getResponsible(ClubMember $clubMember): array
    {
        return ClubMember::find()
            ->where(['club_id' => $clubMember->club_id])
            ->andWhere(['or', ['is_manager' => true], ['is_owner' => true]])
            ->all();
    }

    public function checkResponsible(ClubMember $clubMember): bool
    {
        /** @var ClubMember $responsible */
        foreach ($this->getResponsible($clubMember) as $responsible) {
            if ($responsible->id === $clubMember->id) return true;
        }

        return false;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $data = [
            "type" => "buy-in",
            "url" => Yii::$app->urlManager->createUrl("/api/clubs/{$this->game->club_id}/buy-in/$this->id"),
            "accept" => "Одобрить",
            "decline" => "Отклонить"
        ];

        /** @var ClubMember $person */
        foreach ($this->getResponsible($this->clubMember) ?? [] as $person) {
            FireBasePushTransport::sendPush(
                $person->user,
                "Запрос на бай-ин от пользователя #$this->user_id",
                "Пользователь #$this->user_id хочет пополнить свой баланс. Запрашиваемая сумма: $this->amount",
                $data
            );
        }
    }
}