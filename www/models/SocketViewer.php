<?php
namespace app\modules\socket\models;

use app\models\Viewer;

/**
 * Class SocketViewer
 * @package app\modules\socket\models
 *
 * @property-read SocketClubTable $clubTable
 * @property-read SocketGame $game
 */
class SocketViewer extends Viewer
{

    public function fields()
    {
        $fields = parent::fields();
        $fields['id'] = fn (SocketViewer $model) => $model->user_id;
        unset(
            $fields['user_id']
        );

        return $fields;
    }

    /**
     * Gets query for [[SocketClubTable]].
     *
     * @return \yii\db\ActiveQuery
     */

    public function getClubTable()
    {
        return $this->hasOne(SocketClubTable::class, ['id' => 'table_id', 'club_id' => 'club_id']);
    }

    /**
     * Gets query for [[Game]].
     *
     * @return \yii\db\ActiveQuery
     */

    public function getGame()
    {
        return $this->hasOne(SocketGame::class, ['table_id' => 'table_id', 'club_id' => 'club_id']);
    }
}