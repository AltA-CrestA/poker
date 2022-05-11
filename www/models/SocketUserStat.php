<?php
namespace app\modules\socket\models;

use Yii;

use app\models\UserStat;

class SocketUserStat extends UserStat
{
    public function rules()
    {
        return parent::rules();
    }

}