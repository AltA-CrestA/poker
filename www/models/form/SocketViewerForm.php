<?php
namespace app\modules\socket\models\form;

use app\components\datetime\SmartDateTime;
use app\modules\socket\models\SocketClubTable;
use yii\base\Model;

use app\models\{
    Club,
    User,
    ClubMember
};

/**
 * @property int $user_id
 * @property int $club_id
 * @property int $table_id
 *
 * @property SocketClubTable $table
 */
class SocketViewerForm extends Model 
{
    public $club_id;
    public $table_id;
    public $user_id;
    public SocketClubTable $table;

    public function init()
    {
        parent::init();
        $this->table_id = $this->table->id;
    }

    public function rules(): array
    {
        return [
            [['club_id', 'table_id', 'user_id'], 'required'],
            [['club_id', 'table_id', 'user_id'], 'integer'],
            ['club_id', 'exist', 'targetClass' => Club::class, 'targetAttribute' => 'id'],
            ['user_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            ['user_id', 'exist', 'targetClass' => ClubMember::class, 'targetAttribute' => ['user_id' => 'user_id', 'club_id' => 'club_id']],
            ['table_id', 'validateTable']
        ];
    }

    /**
     * Проверка стола на активность
     */
    public function validateTable()
    {
        if (!$this->hasErrors()) {
            $nowTime = (new SmartDateTime())->toUTC();
            $activeTo = (new SmartDateTime($this->table->active_to))->toUTC();

            if ($activeTo < $nowTime) {
                $this->addError("table_id", "Стол не является активным #$this->table_id");
            }
        }
    }
}