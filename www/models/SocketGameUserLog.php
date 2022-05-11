<?php
namespace app\modules\socket\models;

use app\models\GameUserLog;
use app\modules\oldadmin\helpers\ARHelper;

class SocketGameUserLog extends GameUserLog
{
    public function fields()
    {
        $removeFields = [
            'id',
        ];
        $addFields = [
            'user_id' => function(SocketGameUserLog $log) {
                return $log->gameUser ? $log->gameUser->id : null;
            }
        ];
        return ARHelper::overrideFields(parent::fields(), $addFields, $removeFields);
    }
}
