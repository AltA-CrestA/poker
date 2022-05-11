<?php
namespace app\modules\socket\models;

use app\models\GameWinner;
use app\modules\oldadmin\helpers\ARHelper;

class SocketGameWinner extends GameWinner
{
    public function fields()
    {
        $removeFields = [
            'id',
            'game_id'
        ];
        $addFields = [];
        return ARHelper::overrideFields(parent::fields(), $addFields, $removeFields);
    }
}
