<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 9/20/17
 * Time: 2:41 PM
 */

namespace execut\oData;

use Kily\Tools1C\OData\Client;
use yii\db\Exception;
use yii\helpers\ArrayHelper;

class ActiveRecord extends \yii\db\ActiveRecord
{
    public $complexRelations = [];
    public static function find()
    {
        return new ActiveQuery(static::class);
    }

    protected static $attributesCache = [];
    /**
     * Returns the list of all attribute names of the model.
     * The default implementation will return all column names of the table associated with this AR class.
     * @return array list of attribute names.
     */
    public function attributes()
    {
        if (array_key_exists(static::tableName(), static::$attributesCache)) {
            return static::$attributesCache[static::tableName()];
        }

        return static::$attributesCache[static::tableName()] = parent::attributes();
    }

    /**
     * @inheritdoc
     */
    public function refresh()
    {
        $pk = [];
        // disambiguate column names in case ActiveQuery adds a JOIN
        foreach ($this->getPrimaryKey(true) as $key => $value) {
            $pk[$key] = $value;
        }
        /* @var $record BaseActiveRecord */
        $record = static::findOne($pk);
        return $this->refreshInternal($record);
    }

    public static function updateAll($attributes, $condition = NULL, $params = []) {
//        if ($condition === null || count($condition) != 1 || empty($condition['Ref_Key'])) {
//            throw new \yii\base\Exception('updateAll allowed only update by Ref_Key');
//        }
//
//        $id = $condition['Ref_Key'];
        $client = self::getClient();
        $attributes = static::filtrateAttributes($attributes);
        $result = self::doTries(function () use ($client, $attributes, $condition) {
            if (empty($condition['Ref_Key'])) {
                $client->{static::tableName() . self::buildIdFilter($condition)}->delete(null);
                return $client->{static::tableName()}->create(ArrayHelper::merge($attributes, $condition));
            } else {
                $id = $condition['Ref_Key'];
                return $client->{static::tableName()}->update($id, $attributes);
            }
        });

        return $result;
    }

    public static function buildFilterByCondition($condition) {
        $filterParts = [];
        foreach ($condition as $attribute => $value) {
            $builder = new ConditionBuilder([
                'tableSchema' => static::getTableSchema(),
            ]);

            $filterParts[] = $builder->buildColumnCondition($attribute, $value);
        }

        return implode(' and ', $filterParts);
    }

    public static function buildIdFilter($condition) {
        $result = [];
        foreach ($condition as $attribute => $value) {
            $result[] = $attribute . '=\'' . $value . '\'';
        }

        return '(' . implode(', ', $result) . ')';
    }

    public static function deleteAll($condition = null, $params = []) {
        $client = self::getClient();

        self::doTries(function () use ($client, $condition) {
            if (empty($condition['Ref_Key'])) {
                $client->{static::tableName() . self::buildIdFilter($condition)}->delete(null);
            } else {
                $id = $condition['Ref_Key'];
                $client->{static::tableName()}->update($id, [
                    'DeletionMark' => true,
                ]);
            }
        });

        return true;
    }

    /**
     * Inserts an ActiveRecord into DB without considering transaction.
     * @param array $attributes list of attributes that need to be saved. Defaults to `null`,
     * meaning all attributes that are loaded from DB will be saved.
     * @return bool whether the record is inserted successfully.
     */
    protected function insertInternal($attributes = null)
    {
        if (!$this->beforeSave(true)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        $insertResult = $this->doOperation('create');
        unset($insertResult['odata.metadata']);
        if (empty($insertResult)) {
            return false;
        }
        foreach ($insertResult as $name => $value) {
            if (empty(static::getTableSchema()->columns[$name])) {
                continue;
            }

            $id = static::getTableSchema()->columns[$name]->phpTypecast($value);
            $this->setAttribute($name, $id);
            $values[$name] = $id;
        }

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return true;
    }

    protected static function isGuid($attribute) {
        return self::getTableSchema()->getColumn($attribute)->dbType === 'Edm.Guid';
    }

    public static function primaryKey()
    {
        return self::getTableSchema()->primaryKey;
    }

    public static function getDb()
    {
//        throw new Exception(__FUNCTION__ . ' is not implemented');
    }

//    public static function instantiate($row) {
//        var_dump($row);
//        exit;
//    }
    
    public static function populateRecord($record, $row)
    {
        foreach ($row as $key => $value) {
            if ($value === '00000000-0000-0000-0000-000000000000') {
                unset($row[$key]);
            }
        }

        return parent::populateRecord($record, $row); // TODO: Change the autogenerated stub
    }

    public static function getClient() {
        return \yii::$app->oData;
    }

    public static function getTableSchema()
    {
        $oDataSchema = new Schema([
            'client' => self::getClient(),
        ]);

        return $oDataSchema->getTableSchema(static::tableName()); // TODO: Change the autogenerated stub
    }

    /**
     * @param $value
     * @return array
     */
    protected static function filtrateAttributes($attributes): array
    {
        foreach ($attributes as $attribute => &$value) {
            if (empty($value) && self::isGuid($attribute)) {
//                unset($attributes[$attribute]);
                $value = '00000000-0000-0000-0000-000000000000';
            } else if (self::getTableSchema()->getColumn($attribute)->dbType === 'Edm.Boolean') {
                if ($value === null) {
                    unset($attributes[$attribute]);
                } else {
                    $value = (boolean) $value;
                }
            }
        }
//        unset($attributes[current(self::primaryKey())]);
        unset($attributes['DataVersion']);
//        unset($attributes['Code']);
        return $attributes;
    }

    /**
     * @param $operation
     * @return bool
     * @throws \yii\base\Exception
     */
    protected function doOperation($operation)
    {
        $client = self::getClient();
        $attributes = static::filtrateAttributes($this->attributes);
        foreach ($this->complexRelations as $relation) {
            $models = $this->$relation;
            foreach ($models as $key => $model) {
                if (!is_array($model)) {
                    $models[$key] = $model->attributes;
                }
            }

            $attributes[$relation] = $models;
            $this->$relation = [];
        }

        $try = function () use ($client, $operation, $attributes) {
            $client->{static::tableName()};
            if ($this->isNewRecord) {
                return $client->$operation($attributes);
            } else {
                return $client->$operation($this->primaryKey, $attributes);
            }
        };
        $result = $this->doTries($try);

        return $result;
    }

    /**
     * @param $try
     * @return bool
     * @throws \execut\oData\Exception
     */
    protected static function doTries($try) {
        $key = 0;
        do {
            try {
                return $try();
            } catch (\execut\oData\Exception $e) {
                if (strpos($e->getMessage(), 'ПриЗаписи') !== false) {
                    if ($key === 2) {
                        throw $e;
                    }

                    $key++;
                    sleep(1);
                    continue;
                }

                throw $e;
            }
        } while ($key < 3);
    }

    public function doRequest($request) {
        $client = self::getClient();
        $client->{static::tableName() . '(guid\'' . $this->primaryKey . '\')/' . $request};
        return $client->request('POST');
    }

    protected static function filterCondition(array $condition, array $aliases = [])
    {
        return $condition;
    }
}