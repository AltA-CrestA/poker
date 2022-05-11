<?php
namespace app\modules\socket\services;

use Exception;

use yii\{
    base\Component,
    db\Expression,
    web\NotFoundHttpException
};

use app\helpers\GeoHelper;

use app\modules\socket\models\{
    SocketGame,
    SocketGameStateLog,
    SocketGameUser,
    SocketLobbyTable,
    form\SocketLobbyJoinForm
};
use yii2mod\collection\Collection;

class LobbyTableService extends Component
{
    /**
     * Получить стол по идентификатору
     *
     * @param int $id
     * @return SocketLobbyTable
     * @throws NotFoundHttpException
     */
    public function getLobbyTable(int $id): SocketLobbyTable
    {
        if ($socketLobbyTable = SocketLobbyTable::findOne(['id' => $id])) return $socketLobbyTable;

        throw new NotFoundHttpException("Стол не найден #$id");
    }

    /**
     * @param SocketLobbyJoinForm $form
     * @return SocketGame
     * @throws Exception
     */
    public function getFreeTable(SocketLobbyJoinForm $form): SocketGame
    {
        $query = SocketLobbyTable::find()
            ->with('game')
            ->where([
                'lobby_type_id' => $form->lobby_type_id,
            ])
            ->andWhere(['<', '{{%lobby_table}}.player_count', $form->getLobbyType()->max_table_users_count])
            ->orderBy(['{{%lobby_table}}.player_count' => SORT_DESC]);

        /** @var SocketLobbyTable $socketLobbyTable */
        if ($socketLobbyTable = $query->one()) {
            $idList[] = $socketLobbyTable->id;

            while (!$this->checkGeoAndIp($socketLobbyTable, $form)) {
                /** @var SocketLobbyTable $socketLobbyTable */
                $socketLobbyTable = $query->andWhere(['not in', 'id', $idList])->one();

                $idList[] = $socketLobbyTable->id;

                if ($socketLobbyTable == null) {
                    return $this->createLobby($form)->game;
                }
            }
            $this->createGameUser($socketLobbyTable->game, $form);
        } else {
            $socketLobbyTable = $this->createLobby($form);
        }

        return $socketLobbyTable->game;
    }

    private function createLobby(SocketLobbyJoinForm $form): SocketLobbyTable
    {
        $socketLobbyTable = $this->createTable($form);
        $this->createGame($socketLobbyTable);
        $this->createGameUser($socketLobbyTable->game, $form);

        return $socketLobbyTable;
    }

    private function createTable(SocketLobbyJoinForm $form): SocketLobbyTable
    {
        $socketLobbyTable = new SocketLobbyTable();
        $socketLobbyTable->attributes = $form->getLobbyType()->getAttributes(null, ['id']);
        $socketLobbyTable->is_chat_disallowed = false;
        $socketLobbyTable->lobby_type_id = $form->lobby_type_id;
        $socketLobbyTable->save();

        return $socketLobbyTable;
    }

    private function createGame(SocketLobbyTable $table): void
    {
        $game = new SocketGame();
        $game->club_id = null;
        $game->status = SocketGame::GAME_STATUS_NEW;
        $game->type = $table->getTypeSlug();
        $game->deck = ($game->type == SocketGame::GAME_NLH_RG_SIX_PLUS_TYPE) ? SocketGame::SIX_PLUS_DECK : SocketGame::STANDARD_DECK;
        $game->attributes = $table->getAttributes(null, [
            'id',
            'tournament_type_id',
            'table_type_id',
            'table_subtype_id',
            'created_at',
            'updated_at',
            'is_removed'
        ]);
        $game->link('lobbyTable', $table);
        $state = new SocketGameStateLog([
            'main_pot' => 0,
            'side_pots' => [],
            'ante_amount' => $table->ante_amount,
            'current_bet' => 0,
            'big_blind_amount' => $table->minimal_bet * 2,
            'small_blind_amount' => $table->minimal_bet,
            'bank_money_amount' => 0,
            'state_money_amount' => 0,
            'state_type' => SocketGameStateLog::GAME_STATE_NEW,
            'is_game_started' => false
        ]);
        $state->link('game', $game);
    }

    /**
     * @param SocketGame $game
     * @param SocketLobbyJoinForm $form
     */
    private function createGameUser(SocketGame $game, SocketLobbyJoinForm $form)
    {
        $socketGameUser = new SocketGameUser([
            'user_id' => $form->user_id,
            'is_active' => $game->status != $game::GAME_STATUS_RUN,
            'ip' => $form->ip,
            'longitude' => $form->longitude,
            'latitude' => $form->latitude,
            'position' => $this->findFreePosition($game)
        ]);
        $socketGameUser->link('game', $game);
    }

    private function findFreePosition(SocketGame $game): int
    {
        $gameUsers = Collection::make($game->gameUsersByItsGame);
        for ($i = 1; $i <= $game->max_table_users_count; $i++) {
            $userOnThisPosition = $gameUsers->where('position', $i)->first();
            if (!$userOnThisPosition) {
                $foundedPosition = $i;
                break;
            }
        }
        return $foundedPosition ?? 0;
    }

    private function checkGeoAndIp(SocketLobbyTable $table, SocketLobbyJoinForm $form): bool
    {
        $gameUsers = $table
            ->game
            ->getGameUsersByItsGame()
            ->withRemoved()
            ->andWhere(['!=', 'user_id', $form->user_id])
            ->all();

        /** @var SocketGameUser $gameUser */
        foreach ($gameUsers ?? [] as $gameUser) {
            if ($gameUser->ip == $form->ip) return false;
            $distance = GeoHelper::distanceTo($form->latitude, $form->longitude, $gameUser->latitude, $gameUser->longitude);
            if ($distance < 400) return false;
        }

        return true;
    }
}