<?php
namespace app\modules\socket\models;

use DateInterval;
use app\models\ClubTable;
use app\components\datetime\SmartDateTime;
use app\modules\oldadmin\helpers\ARHelper;
use Exception;
use yii\db\ActiveQuery;

/**
 * @property SocketGame $game
 *
 * Class SocketClubTable
 * @package app\modules\socket\models
 */
class SocketClubTable extends ClubTable
{
    public function fields(): array
    {
        $addFields = [
            'id '=> fn (SocketClubTable $socketClubTable) => $socketClubTable->id,
            'club_id' => fn (SocketClubTable $socketClubTable) => $socketClubTable->club_id,
            'title' => fn (SocketClubTable $socketClubTable) => $socketClubTable->title,
            'type' => fn (SocketClubTable $socketClubTable) => $socketClubTable->getTypeSlug(),
            'is_auto_renew' => fn (SocketClubTable $socketClubTable) => $socketClubTable->is_auto_renew,
            'auto_renew_current_count' => fn (SocketClubTable $socketClubTable) => $socketClubTable->auto_renew_current_count ?? 0,
            'auto_renew_max_count' => fn (SocketClubTable $socketClubTable) => $socketClubTable->auto_renew_max_count ?? 0,
            'time_left' =>  fn (SocketClubTable $socketClubTable) => (new SmartDateTime($socketClubTable->active_to))->deltaInSeconds(SmartDateTime::now())
        ];

        $removeFields = [];

        return ARHelper::overrideFields([], $addFields, $removeFields);
    }

    /**
     * @return string
     */
    public function getTypeSlug(): string
    {
        $parts[] = $this->tableSubtype && $this->tableSubtype->type ? $this->tableSubtype->type->code : '';
        $parts[] = $this->tableSubtype && $this->tableSubtype->tournamentType ? $this->tableSubtype->tournamentType->code : '';
        $parts[] = $this->tableSubtype ? $this->tableSubtype->code : '';

        return strtolower(implode('_', $parts));
    }

    /**
     * Gets query for [[SocketGame]].
     *
     * @return ActiveQuery
     */
    public function getGame(): ActiveQuery
    {
        return $this->hasOne(SocketGame::class, ['table_id' => 'id']);
    }

    /**
     * Создание игры
     *
     * @return SocketGame
     * @throws Exception
     */
    public function createGame(): SocketGame
    {
        $game = new SocketGame();
        $game->club_id = $this->club_id;
        $game->status = SocketGame::GAME_STATUS_NEW;
        $game->type = $this->getTypeSlug();
        $game->deck = SocketGame::STANDARD_DECK;

        if ($game->type == SocketGame::GAME_NLH_RG_SIX_PLUS_TYPE) {
            $game->deck = SocketGame::SIX_PLUS_DECK;
        }
        $game->attributes = $this->getAttributes(null, ['id', 'club_id', 'tournament_type_id', 'table_type_id', 'table_subtype_id', 'created_at', 'updated_at', 'is_removed']);
        $this->link('game', $game);
        $state = new SocketGameStateLog([
            'main_pot' => 0,
            'side_pots' => [],
            'ante_amount' => $this->ante_amount,
            'current_bet' => 0,
            'big_blind_amount' => $this->minimal_bet * 2,
            'small_blind_amount' => $this->minimal_bet,
            'bank_money_amount' => 0,
            'state_money_amount' => 0,
            'state_type' => SocketGameStateLog::GAME_STATE_NEW,
            'is_game_started' => false
        ]);
        $game->link('gameStateLogsByItsGame', $state);
        return $game;
    }

    /**
     * Автопродление стола
     *
     * @return void
     * @throws Exception
     */
    public function autoExtend(): void
    {
        $hours = floor($this->lifetime);
        $minutes = 60 * ($this->lifetime - floor($this->lifetime));
        $date = SmartDateTime::create($this->active_to);
        if ($minutes > 0) {
            $date = $date->add(new DateInterval("PT30M"));
        }
        if ($hours > 0) {
            $date = $date->add(new DateInterval("PT{$hours}H"));
        }

        $this->active_to = $date->format('Y-m-d\TH:i:s\Z');
        $this->auto_renew_current_count++;
    }
}
