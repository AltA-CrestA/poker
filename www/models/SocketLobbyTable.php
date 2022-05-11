<?php
namespace app\modules\socket\models;

use app\models\LobbyTable;
use app\components\datetime\SmartDateTime;
use app\modules\oldadmin\helpers\ARHelper;

/**
 * @property-read SocketGame $game
 */
class SocketLobbyTable extends LobbyTable
{
    public function fields()
    {
        $fields = [
            'id',
            'table_type_id',
            'title',
        ];
        $addFields = [
            'type' => function (SocketLobbyTable $t) {
                return $t->getTypeSlug();
            }
        ];
        $removeFields = [];
        return ARHelper::overrideFields($fields, $addFields, $removeFields);
    }

    public function getGame()
    {
        return $this->hasOne(SocketGame::class, ['table_id' => 'id'])->andOnCondition(['club_id' => null]);
    }

    /**
     * @return string
     */
    public function getTypeSlug()
    {
        $parts[] = ($this->tableSubtype && $this->tableSubtype->type) ? $this->tableSubtype->type->code : '';
        $parts[] = ($this->tableSubtype && $this->tableSubtype->tournamentType) ? $this->tableSubtype->tournamentType->code : '';
        $parts[] = ($this->tableSubtype) ? $this->tableSubtype->code : '';
        return strtolower(implode('_', $parts));
    }

    /**
     * @return SocketGame
     * @throws \Exception
     */
    public function createGame()
    {
        $game = new SocketGame();
        $game->club_id = null;
        $game->status = SocketGame::GAME_STATUS_NEW;
        $game->type = $this->getTypeSlug();
        $game->deck = SocketGame::STANDARD_DECK;
        $game->created_at = SmartDateTime::now();
        $game->updated_at = SmartDateTime::now();
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
}
