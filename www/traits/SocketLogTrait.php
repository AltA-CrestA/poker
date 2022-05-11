<?php 
namespace app\modules\socket\traits;

use Yii;
use yii\helpers\Json;

use app\models\SocketLog;

trait SocketLogTrait 
{
    /**
     * Сохранение лога 
     * 
     * @return boolean
     */

    public function setSocketLog(): bool
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $socketLog = new SocketLog([
                'request_url' => Yii::$app->request->url,
                'controller' => Yii::$app->controller->id,
                'action' => Yii::$app->controller->action->id,
                'headers' => ($headers = Yii::$app->request->headers) ? Json::encode($headers) : null,
                'get_data' => ($getData = Yii::$app->request->get()) ? Json::encode($getData) : null,
                'post_data' => ($postData = Yii::$app->request->post()) ? Json::encode($postData) : null,
            ]);

            if ($socketLog->validate() && $socketLog->save()) {

                Yii::info("$socketLog->controller/$socketLog->action \n get_data: $socketLog->get_data \n post_data $socketLog->post_data", 'akpoker_telegram_log');

                $transaction->commit();
                return true;
            }

            return false;
        } catch(\Exception $th) {
            $transaction->rollBack();

            Yii::error($th->getMessage(), "Ошибка сохранения SocketLog");
            return false;
        }
    }   

}