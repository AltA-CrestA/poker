<?php
namespace app\modules\socket\models;

use GuzzleHttp\Exception\GuzzleException;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use app\models\{
    GameUser,
    Game,
    ClubMember
};

use app\services\notification\transport\FireBasePushTransport;
use app\components\datetime\SmartDateTime;
use app\helpers\GeoHelper;
use app\modules\oldadmin\helpers\ARHelper;

/**
 * Class SocketGameUser
 * @property string $position_name
 *
 * @property-read SocketGame $game
 * @property-read SocketViewer $viewer
 * @property-read User $user
 * @property-read ClubMember $clubMember
 */
class SocketGameUser extends GameUser
{
    public ?float $requiredBalance = null;
    public $action_logs;
    public $position_name;
    public ?bool $send_push = false;

    public const SCENARIO_NEW = 'new';
    public const SCENARIO_RECONNECT = 'reconnect';
    public const SCENARIO_COMEBACK = 'comeback';
    public const SCENARIO_LEAVE = 'leave';
    private const PUSH_TYPE = 'join';

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_NEW] = ['game_id', 'user_id', 'requiredBalance', 'position', 'game_user_status_id', 'position_name', 'name', 'is_bot', 'is_removed', 'is_active'];
        $scenarios[self::SCENARIO_COMEBACK] = ['game_id', 'user_id', 'requiredBalance', 'position', 'game_user_status_id', 'position_name', 'name', 'is_bot', 'is_removed', 'is_active'];
        $scenarios[self::SCENARIO_RECONNECT] = ['game_id', 'user_id', 'game_user_status_id', 'is_active', 'is_removed', 'position_name', 'cards', 'cards_title'];
        $scenarios[self::SCENARIO_LEAVE] = ['game_id', 'user_id', 'game_user_status_id', 'is_active', 'is_removed', 'position_name', 'cards', 'cards_title'];

        if ($this->game->table->is_ip_restriction) {
            $scenarios[self::SCENARIO_NEW] = ArrayHelper::merge($scenarios[self::SCENARIO_NEW], ['ip']);
        }

        if ($this->game->table->is_gps_restriction) {
            $scenarios[self::SCENARIO_NEW] = ArrayHelper::merge($scenarios[self::SCENARIO_NEW], ['latitude', 'longitude']);
        }
        
        return $scenarios;
    }

    public function rules(): array
    {
        return ArrayHelper::merge(parent::rules(), [
            [['user_id', 'game_id', 'game_user_status_id', 'ip', 'latitude', 'longitude'], 'required'],
            [['user_id', 'game_id'], 'integer'],
            ['game_id', 'exist', 'targetClass' => Game::class, 'targetAttribute' => 'id'],
            ['user_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            ['position', 'validatePosition'],
            ['user_id', 'validateVpip'],
            ['requiredBalance', 'validateBalance'],
            ['ip', 'validateIp'],
            ['longitude', 'validateGps'],
            ['position_name', 'string'],
            ['name', 'default', 'value' => $this->user->login],
            ['is_active', 'default', 'value' => $this->game->status != SocketGame::GAME_STATUS_RUN],
            [['name', 'cards_title'], 'string'],
            [['is_bot', 'is_removed', 'is_active'], 'boolean'],
            ['action_logs', 'safe']
        ]);
    }

    public function beforeValidate(): bool
    {
        parent::beforeValidate();

        switch ($this->scenario) {
            case self::SCENARIO_NEW:
                $this->requiredBalance = $this->game->table->buy_in_min;
                break;
            case self::SCENARIO_COMEBACK:
                $this->requiredBalance = $this->balance_left;
        }
        return true;
    }

    /**
     * @return void
     * @throws GuzzleException
     */
    public function afterValidate()
    {
        parent::afterValidate();
        if ($this->hasErrors() && $this->send_push) {
            $errors = $this->getFirstErrors();

            FireBasePushTransport::sendPush(
                $this->user,
                'Не удалось сесть за стол',
                array_shift($errors),
                ['type' => self::PUSH_TYPE]
            );
        }
    }

    /**
     * @param $insert
     * @param $changedAttributes
     * @return boolean
     * @throws GuzzleException
     */
    public function afterSave($insert, $changedAttributes): bool
    {
        parent::afterSave($insert, $changedAttributes);

        if ($this->scenario == self::SCENARIO_NEW) {
            if ($this->send_push) {
                FireBasePushTransport::sendPush(
                    $this->user,
                    'Подошла ваша очередь',
                    'Вы сели за стол в клубе "'.$this->game->club->title.'"',
                    ['type' => self::PUSH_TYPE]
                );
            }
        }

        if ($insert) {
            if ($clubTable = $this->game->table ?? null) {
                $clubTable->current_users_count++;
                $clubTable->save();
            }
        } else {
            if (isset($changedAttributes['is_removed'])) {

                if ($clubTable = $this->game->table ?? null) {
                    if ($this->is_removed) $clubTable->current_users_count--;
                    else $clubTable->current_users_count++;
                    $clubTable->save();
                }
            }
        }
        return true;
    }

    /**
     * Проверка показателя VPIP
     * 
     * @return void
     */
    public function validateVpip()
    {
        $min_vpip = $this->game->table->min_vpip;

        if ($min_vpip > 0) {
            /** @var SocketUserStat $socketUserStat */
            $socketUserStat = SocketUserStat::find()->where(['user_id' => $this->user_id])->andWhere(['indicator' => SocketUserStat::INDICATOR_VPIP])->orderBy(['on_date' => SORT_DESC])->one();
            if (!$socketUserStat || $min_vpip > $socketUserStat->indicator_value) {
                $this->addError('user_id', 'Ваш показатель VPIP меньше допустимого для доступа в игру');
            }
        }
    }

    public function validatePosition()
    {
        if (!$this->hasErrors()) {
            $gameUsers = $this->game->gameUsersByItsGame;

            foreach ($gameUsers as $gameUser) {
                if ($this->position === $gameUser->position) {
                    $this->addError('position', "Данное место занято #$this->position");
                    break;
                }
            }
        }
    }

    /**
     * Проверка баланса пользователя
     * 
     * @return void
     */
    public function validateBalance()
    {
        if (!$this->hasErrors()) {
            $currentBalance = $this->clubMember->balance;
            if ($currentBalance < $this->requiredBalance) {
                $this->addError('balance_current', "Недостаточно средств для входа в игру. Текущий баланс: $currentBalance. Необходимо: $this->requiredBalance");
            }
        }
    }

    /**
     * Проверка IP адреса в одной игре
     * 
     * @return void
     */
    public function validateIp()
    {
        if (!$this->hasErrors()) {
            if ($this->game->getGameUsersByItsGame()->withRemoved()->where(['ip' => $this->ip])->andWhere(['!=', 'user_id', $this->user_id])->exists()) {
                $this->addError('ip', 'Игроки с одинаковым IP-адресом не могут играть за одним столом');
            }
        }
    }

    /**
     * Проверка пользователя по координатам
     * 
     * @return void
     */
    public function validateGps()
    {
        if (!$this->hasErrors()) {
            $gameUsers = $this->game->getGameUsersByItsGame()->withRemoved()->where(['!=', 'user_id', $this->user_id])->all();

            foreach ($gameUsers ?? [] as $gameUser) {
                $distance = GeoHelper::distanceTo($this->latitude, $this->longitude, $gameUser->latitude, $gameUser->longitude);
                if ($distance < 400) {
                    $this->addError("latitude", "Игроки находящиеся близко друг к другу, не могут играть за одним столом");
                    $this->addError("longitude", "Игроки находящиеся близко друг к другу, не могут играть за одним столом");
                }
            }
        }
    }

    public function fields()
    {
        $removeFields = [
            'id',
            'user_id',
            'game_id',
            'name'
        ];
        $addFields = [
            'id' => function(SocketGameUser $u) {
                return $u->user_id;
            },
            'name' => function(SocketGameUser $u) {
               return $u->name ?? (!$u->is_bot && $u->user ? $u->user->login : "Игрок #$u->user_id");
            },
            'image' => function(SocketGameUser $u) {
                return !$u->is_bot ? $u->user->photo : null;
            },
            'action_logs' => function(SocketGameUser $u) {
                return []; //$u->gameUserLogs; на сокетах почему-то нужен только пустой массив
            },
            'crystals' => function(SocketGameUser $u) {
                return !$u->is_bot ? $u->user->crystals : null;
            },
            'calltime' => fn (SocketGameUser $socketGameUser) => (new SmartDateTime($socketGameUser->calltime))->deltaInSeconds(SmartDateTime::now())
        ];
        return ARHelper::overrideFields(parent::fields(), $addFields, $removeFields);
    }

    /**
     * Gets query for [[SocketGameUserLog]].
     *
     * @return ActiveQuery
     */
    public function getGameUserLogs(): ActiveQuery
    {
        return $this->hasMany(SocketGameUserLog::class, ['game_user_id' => 'id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return ActiveQuery
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Gets query for [[SocketGame]].
     *
     * @return ActiveQuery
     */
    public function getGame(): ActiveQuery
    {
        return $this->hasOne(SocketGame::class, ['id' => 'game_id']);
    }

    /**
     * Gets query for [[SocketViewer]].
     *
     * @return ActiveQuery
     */
    public function getViewer(): ActiveQuery
    {
        return $this->hasOne(SocketViewer::class, ['user_id' => 'user_id'])->where(['club_id' => $this->game->club_id, 'table_id' => $this->game->table_id]);
    }
}
