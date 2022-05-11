<?php

namespace app\modules\socket\components;

use app\modules\api\models\ApiUser;
use yii\base\Arrayable;
use yii\base\Model;
use yii\data\DataProviderInterface;
use yii\data\Pagination;
use Yii;

class Serializer extends \yii\rest\Serializer
{

    /**
     * Serializes a data provider.
     * @param \yii\data\DataProviderInterface $dataProvider
     * @return array the array representation of the data provider.
     */
    protected function serializeDataProvider($dataProvider)
    {
        if ($this->preserveKeys) {
            $models = $dataProvider->getModels();
        } else {
            $models = array_values($dataProvider->getModels());
        }
        $models = $this->serializeModels($models);

        if (($pagination = $dataProvider->getPagination()) !== false) {
            $this->addPaginationHeaders($pagination);
        }

        if ($this->request->getIsHead()) {
            return null;
        } elseif ($this->collectionEnvelope === null) {
            return $models;
        }

        $result = [
            $this->collectionEnvelope => $models,
        ];
        $result = $this->serializeData($result);
        if ($pagination !== false) {
            $result = array_merge($result, $this->serializePagination($pagination));
        }

        return $result;
    }

    public function serialize($data)
    {
        if ($data instanceof Model && $data->hasErrors()) {
            return ['error' => $this->serializeModelErrors($data), 'data' => []];
        } elseif ($data instanceof Arrayable) {
            return ['data' => $this->serializeModel($data), 'error'=> []];
        } elseif ($data instanceof DataProviderInterface) {
            return ['data' => $this->serializeDataProvider($data), 'error'=> []];
        }
        return ['data'=>$this->serializeData($data), 'error' => []];
    }

    protected function serializeModel($model)
    {
        if ($this->request->getIsHead()) {
            return null;
        }

        list($fields, $expand) = $this->getRequestedFields();
        return array_merge($model->toArray($fields, $expand), $this->serializeData());
    }

    public function serializeData($data = []){
        return $data;
    }

    /**
     * Serializes a pagination into an array.
     * @param Pagination $pagination
     * @return array the array representation of the pagination
     * @see addPaginationHeaders()
     */
    protected function serializePagination($pagination)
    {
        return [
            $this->metaEnvelope => [
                'totalCount' => $pagination->totalCount,
                'pageCount' => $pagination->getPageCount(),
                'currentPage' => $pagination->getPage() + 1,
                'perPage' => $pagination->getPageSize(),
            ],
        ];
    }

    /**
     * @return array the names of the requested fields. The first element is an array
     * representing the list of default fields requested, while the second element is
     * an array of the extra fields requested in addition to the default fields.
     * @see Model::fields()
     * @see Model::extraFields()
     */
    protected function getRequestedFields()
    {
        $fields = $this->request->get($this->fieldsParam);
        $expand = $this->request->get($this->expandParam, false) ?: $this->request->post($this->expandParam);

        return [
            is_string($fields) ? preg_split('/\s*,\s*/', $fields, -1, PREG_SPLIT_NO_EMPTY) : [],
            is_string($expand) ? preg_split('/\s*,\s*/', $expand, -1, PREG_SPLIT_NO_EMPTY) : is_array($expand) ? $expand : [],
        ];
    }
}