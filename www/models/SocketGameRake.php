<?php
namespace app\modules\socket\models;

use app\models\Club;
use app\models\ClubRake;
use app\models\GameUser;
use app\models\TransactionType;
use app\traits\TransactionTrait;
use yii\db\ActiveQuery;

class SocketGameRake extends ClubRake
{
    use TransactionTrait;
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if ($insert) {
            $this->addTransaction(
                TransactionType::CLUB_RAKE,
                $this->amount,
                $this->user_id,
                GameUser::class,
                Club::class,
                $this->gameUser->id,
                $this->club->id
            );
        }
    }

    /**
     * Gets query for [[SocketGameUser]].
     *
     * @return ActiveQuery
     */
    public function getGameUser(): ActiveQuery
    {
        return $this->hasOne(SocketGameUser::class, ['game_id' => 'game_id'])->andWhere(['user_id' => $this->user_id]);
    }
}