<?php
namespace app\modules\socket\models\form;

use app\components\datetime\SmartDateTime;
use Exception;
use app\modules\socket\models\{SocketClubTable, SocketViewer, User};
use yii\base\Model;

use app\models\{
    Club,
    ClubMember
};

/**
 * Class SocketJoinForm
 *
 * @property int $user_id
 * @property int $club_id
 * @property int $table_id
 * @property int $position
 * @property string $ip
 * @property double $latitude
 * @property double $longitude
 * @property bool $send_push
 *
 * @property SocketClubTable $table
 */
class SocketJoinForm extends Model 
{
    public const SCENARIO_QUEUE = 'queue';

    public $user_id;
    public $club_id;
    public $table_id;
    public $position;
    public $ip;
    public $latitude;
    public $longitude;
    public $send_push;
    public SocketClubTable $table;
    private ?SocketViewer $_viewer = null;
    private ?Club $_club;
    private ?User $_user;

    public function init()
    {
        parent::init();
        $this->table_id = $this->table->id;
    }

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_DEFAULT] = ['table_id', 'user_id', 'club_id', 'position'];
        $scenarios[self::SCENARIO_QUEUE] = ['table_id', 'user_id', 'club_id'];

        return $scenarios;
    }

    public function rules(): array
    {
        return [
            [['user_id', 'club_id', 'table_id', 'position'], 'required'],
            [['user_id', 'club_id', 'table_id', 'position'], 'integer'],
            ['ip', 'string'],
            [['longitude', 'latitude'], 'number'],
            ['send_push', 'boolean'],
            ['send_push', 'default', 'value' => false],
            ['club_id', 'validateClub'],
            ['table_id', 'validateTable'],
            ['user_id', 'validateUser'],
            ['user_id', 'validateClubMember'],
            ['user_id', 'validateViewer'],
        ];
    }

    public function validateUser()
    {
        if (!$this->hasErrors()) {
            if (!$this->_user = User::findOne(['id' => $this->user_id])) {
                $this->addError('user_id', '???????????????????? ???????????????????????? ???? ????????????????????');
            }
        }
    }

    public function validateClub()
    {
        if (!$this->hasErrors()) {
            if (!$this->_club = Club::findOne(['id' => $this->club_id])) {
                $this->addError('club_id', '???????????????????? ?????????? ???? ????????????????????');
            }
        }
    }

    /**
     * ???????????????? ???????????????? ???? ???????????????????????? ???????????????????? ??????????
     * 
     * @return void
     */
    public function validateClubMember()
    {
        if (!$this->hasErrors()) {
            if (!ClubMember::findOne(['user_id' => $this->user_id, 'club_id' => $this->club_id])) {
                $this->addError("user_id", "?????????????????? ???????????????????????? ???? ?????????????? ?? ???????????? ?????????? #$this->club_id");
            }
        }
    }

    /**
     * ???????????????? ?????????? ???? ????????????????????
     *
     * @return void
     */
    public function validateViewer()
    {
        if (!$this->hasErrors()) {
            $this->_viewer = SocketViewer::find()->where([
                'user_id' => $this->user_id,
                'club_id' => $this->club_id,
                'table_id' => $this->table_id
            ])->one();

            if (!$this->_viewer) {
                $this->addError("user_id", "?????????????? ???? ????????????");
            }
        }
    }

    /**
     * ???????????????? ?????????? ???? ????????????????????
     *
     * @return void
     * @throws Exception
     */
    public function validateTable()
    {
        if (!$this->hasErrors()) {
            $nowTime = (new SmartDateTime())->toUTC();
            $activeTo = (new SmartDateTime($this->table->active_to))->toUTC();

            if ($activeTo < $nowTime) {
                $this->addError("table_id", "???????? ???? ???????????????? ???????????????? #$this->table_id");
            } elseif
            (
                $this->scenario == self::SCENARIO_DEFAULT
                &&
                $this->table->max_table_users_count <= $this->table->current_users_count
            )
            {
                $this->addError("table_id", "???????????????????? ???????????????????????????? ?? ????????, ???? ???????????? ???????????????????????? ???????????????????? ??????????????");
            }
        }
    }

    /**
     * ???????????????? ????????
     *
     * @return Club
     */
    public function getClub(): Club
    {
        return $this->_club;
    }

    /**
     * ???????????????? ??????????????
     *
     * @return SocketViewer
     */
    public function getViewer(): SocketViewer
    {
        return $this->_viewer;
    }

    /**
     * ???????????????? ????????????????????????
     *
     * @return User
     */
    public function getUser(): User
    {
        return $this->_user;
    }

    /**
     * ???????????????? ???????????????? ?????? ?????????????? GameUser
     * 
     * @return array
     */
    public function getAttributesForGameUser(): array
    {
        $attributes = $this->attributes;
        unset($attributes['table_id'], $attributes['club_id'], $attributes['table']);

        return $attributes;
    }
}