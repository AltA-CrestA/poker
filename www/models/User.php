<?php


namespace app\modules\socket\models;


use app\modules\oldadmin\helpers\ARHelper;
use app\modules\api\models\ApiUser;

class User extends \app\models\User {

    public function rules()
    {
        return parent::rules();
    }

    public function fields()
    {
        $remove_fields = [
            "points",
            "crystals",
            "coins",
            "is_registration_complete",
            "is_indebted",
            "personal_discount",
            "role",
        ];

        $add_fields = [
            "config",
            "photo" => function(User $u){
                return $u->photo;
            }
        ];

        return ARHelper::overrideFields(parent::fields(), $add_fields, $remove_fields);
    }

}
