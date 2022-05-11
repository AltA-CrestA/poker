<?php
namespace app\modules\socket\models;

use DateTime;
use app\models\GameStateLog;
use app\modules\oldadmin\helpers\ARHelper;
use yii\helpers\ArrayHelper;

/**
 * Class SocketGameStateLog
 * @property int $user_id
 */
class SocketGameStateLog extends GameStateLog
{
    public $user_id;

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            ['user_id', 'integer']
        ]);
    }

    public function fields()
    {
        $removeFields = [
            'id',
            'game_id',
            'user_last_turn_time',
            'user_id'
        ];
        $addFields = [
            'user_last_turn_time' => function(SocketGameStateLog $log) {
                return $log->user_last_turn_time ? (new DateTime($log->user_last_turn_time))->format('U') : null;
            }
        ];
        return ARHelper::overrideFields(parent::fields(), $addFields, $removeFields);
    }
}
