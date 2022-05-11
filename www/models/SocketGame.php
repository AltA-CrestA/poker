<?php
namespace app\modules\socket\models;

use Exception;
use yii\db\ActiveQuery;
use yii2mod\collection\Collection;
use app\models\Game;
use app\components\datetime\SmartDateTime;
use app\modules\oldadmin\helpers\ARHelper;

/**
 * Class SocketGame
 *
 * @property SocketViewer[] $gameViewersByItsGame
 */
class SocketGame extends Game
{
    const GAME_STATUS_NEW = 'new';
    const GAME_STATUS_RUN = 'run';
    const GAME_STATUS_STOP = 'stop';
    const STANDARD_DECK = [
        '2s',
        '3s',
        '4s',
        '5s',
        '6s',
        '7s',
        '8s',
        '9s',
        'Ts',
        'Js',
        'Qs',
        'Ks',
        'As',
        '2c',
        '3c',
        '4c',
        '5c',
        '6c',
        '7c',
        '8c',
        '9c',
        'Tc',
        'Jc',
        'Qc',
        'Kc',
        'Ac',
        '2d',
        '3d',
        '4d',
        '5d',
        '6d',
        '7d',
        '8d',
        '9d',
        'Td',
        'Jd',
        'Qd',
        'Kd',
        'Ad',
        '2h',
        '3h',
        '4h',
        '5h',
        '6h',
        '7h',
        '8h',
        '9h',
        'Th',
        'Jh',
        'Qh',
        'Kh',
        'Ah',
    ];
    const SIX_PLUS_DECK = [
        '6s',
        '7s',
        '8s',
        '9s',
        'Ts',
        'Js',
        'Qs',
        'Ks',
        'As',
        '6c',
        '7c',
        '8c',
        '9c',
        'Tc',
        'Jc',
        'Qc',
        'Kc',
        'Ac',
        '6d',
        '7d',
        '8d',
        '9d',
        'Td',
        'Jd',
        'Qd',
        'Kd',
        'Ad',
        '6h',
        '7h',
        '8h',
        '9h',
        'Th',
        'Jh',
        'Qh',
        'Kh',
        'Ah',
    ];
    const GAME_NLH_RG_SIMPLE_TYPE = 'nlh_ring_game_simple';
    const GAME_NLH_RG_SIX_PLUS_TYPE = 'nlh_ring_game_six_plus';
    const GAME_PLO_RG_PLO4_TYPE = 'plo_ring_game_plo4';
    const GAME_PLO_RG_PLO5_TYPE = 'plo_ring_game_plo5';
    const GAME_PLO_RG_PLO6_TYPE = 'plo_ring_game_plo6';

    public function fields()
    {
        $removeFields = $this->getRemoveFieldsByType($this->table ? $this->table->getTypeSlug() : '');
        $addFields = [
            'viewers' => function (SocketGame $g) {
                return $g->getGameViewersByItsGame()->all();
            },
            'users' => function (SocketGame $g) {
                return $g->getGameUsersByItsGame()->all();
            },
            'winners' => function (SocketGame $g) {
                return $g->getGameWinnersByItsGame()->all();
            },
            'state' => function (SocketGame $g) {
                return $g->getLastState();
            }
        ];
        return ARHelper::overrideFields(parent::fields(), $addFields, $removeFields);
    }

    /**
     * Связь с одной моделью "Столы клуба" (\app\models\{ClubTable})
     */
    public function getTable(): ActiveQuery
    {
        return $this->hasOne(SocketClubTable::class, ['id' => 'table_id']);
    }

    /**
     * Связь с одной моделью "Столы лобби" (\app\models\{LobbyTable})
     */
    public function getLobbyTable(): ActiveQuery
    {
        return $this->hasOne(SocketLobbyTable::class, ['id' => 'table_id']);
    }

    /**
     * Связь со многими моделями "Участники игры" (\app\models\GameUser)
     */
    public function getGameUsersByItsGame(): ActiveQuery
    {
        return $this->hasMany(SocketGameUser::class, ['game_id' => 'id']);
    }

    public function getGameViewersByItsGame(): ActiveQuery
    {
        return $this->hasMany(SocketViewer::class, ['table_id' => 'table_id'])->orderBy('queue_number');
    }

    /**
     * Связь со многими моделями "Лог состояний игры" (\app\models\GameStateLog)
     */
    public function getGameStateLogsByItsGame(): ActiveQuery
    {
        return $this->hasMany(SocketGameStateLog::class, ['game_id' => 'id']);
    }

    public function getLastState()
    {
        return $this->getGameStateLogsByItsGame()
            ->orderBy('game_cycle_number, bet_cycle_number, step_number DESC')
            ->one();
    }

    /**
     * Связь со многими моделями "Победители игры" (\app\models\GameWinner)
     */
    public function getGameWinnersByItsGame(): ActiveQuery
    {
        return $this->hasMany(SocketGameWinner::class, ['game_id' => 'id']);
    }

    /**
     * Получение карт
     * 
     * @param array $cardTitles
     * @return string
     */
    private function getCardTitles(array $cardTitles)
    {
        return $cardTitles['descr'] ?? '';
    }

    /**
     * @param string $type
     * @return array|mixed
     */
    public function getRemoveFieldsByType(string $type)
    {
        $fields = [
            'nlh_ring_game_simple' => [
                'ante_amount',
                'buy_in_amount',
                'start_balance',
                'increase_bet_after',
                'structure',
                'rebuy_amount',
                'rebuy_count',
                'addon_amount',
                'addon_coefficient',
                'is_knockout',
                'is_has_five_minute_break',
                'is_has_guaranty',
                'jackpot',
                'jackpot_amount',
                'stack_min',
                'is_operation_order',
                'operation_order_value',
                'is_chip_output',
                'is_hi_lo',
                'created_at',
                'updated_at',
                'is_removed'
            ],
            'nlh_ring_game_six_plus' => [
                'minimal_bet',
                'buy_in_amount',
                'is_calltime',
                'calltime_value',
                'start_balance',
                'increase_bet_after',
                'structure',
                'rebuy_amount',
                'rebuy_count',
                'addon_amount',
                'addon_coefficient',
                'is_knockout',
                'is_has_five_minute_break',
                'is_has_guaranty',
                'jackpot',
                'jackpot_amount',
                'stack_min',
                'is_operation_order',
                'operation_order_value',
                'is_chip_output',
                'is_hi_lo',
                'created_at',
                'updated_at',
                'is_removed'
            ],
            'plo_ring_game_plo4' => [
                'ante_amount',
                'buy_in_amount',
                'is_risk_management',
                'start_balance',
                'increase_bet_after',
                'structure',
                'rebuy_amount',
                'rebuy_count',
                'addon_amount',
                'addon_coefficient',
                'is_knockout',
                'is_has_five_minute_break',
                'is_has_guaranty',
                'jackpot',
                'jackpot_amount',
                'stack_min',
                'is_operation_order',
                'operation_order_value',
                'is_chip_output',
                'created_at',
                'updated_at',
                'is_removed'
            ],
            'plo_ring_game_plo5' => [
                'ante_amount',
                'buy_in_amount',
                'is_risk_management',
                'start_balance',
                'increase_bet_after',
                'structure',
                'rebuy_amount',
                'rebuy_count',
                'addon_amount',
                'addon_coefficient',
                'is_knockout',
                'is_has_five_minute_break',
                'is_has_guaranty',
                'jackpot',
                'jackpot_amount',
                'stack_min',
                'is_operation_order',
                'operation_order_value',
                'is_chip_output',
                'created_at',
                'updated_at',
                'is_removed'
            ],
            'plo_ring_game_plo6' => [
                'ante_amount',
                'buy_in_amount',
                'is_risk_management',
                'start_balance',
                'increase_bet_after',
                'structure',
                'rebuy_amount',
                'rebuy_count',
                'addon_amount',
                'addon_coefficient',
                'is_knockout',
                'is_has_five_minute_break',
                'is_has_guaranty',
                'jackpot',
                'jackpot_amount',
                'stack_min',
                'is_operation_order',
                'operation_order_value',
                'is_chip_output',
                'created_at',
                'updated_at',
                'is_removed'
            ]
        ];
        return $fields[$type] ?? [];
    }

    /**
     * @return bool
     */
    public function isLobbyGame()
    {
        return empty($this->table->club_id);
    }

    /**
     * @return int
     */
    private function findFreePosition()
    {
        $foundedPosition = 0;
        $gameUsers = Collection::make($this->gameUsersByItsGame);
        for ($i = 1; $i <= $this->max_users_count; $i++) {
            $userOnThisPosition = $gameUsers->where('position', $i)->first();
            if (!$userOnThisPosition) {
                $foundedPosition = $i;
                break;
            }
        }
        return $foundedPosition;
    }

    /**
     * Установить время активности стола
     *
     * @return void
     * @throws Exception
     */
    public function setTableActiveTo(): void
    {
        $table = $this->table;

        if (!$table->started_at) {
            $startedAt = $table->started_at = SmartDateTime::now();
            $createdAt = new SmartDateTime($table->created_at);
            $activeTo = new SmartDateTime($table->active_to);

            $diff = $startedAt->deltaInSeconds($createdAt);
            $table->active_to = $activeTo->add($diff);
            $table->save();
        }
    }
}
