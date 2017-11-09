<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 9/20/17
 * Time: 2:05 PM
 */

namespace execut\oData;


use Kily\Tools1C\OData\Client;
use yii\db\ColumnSchema;
use yii\db\TableSchema;

class Schema extends \yii\db\Schema
{
    /**
     * @var Client
     */
    public $client = null;

    public function loadTableSchema($name) {
    }

    public function getCustomColumnsTypes() {
        return $this->client->customColumnsTypes;
    }

    protected function getMetadata() {
        $cacheKey = __CLASS__;
        $cache = \yii::$app->cache;
        if ($result = $cache->get($cacheKey)) {
            return $result;
        }

        $metadata = $this->client->getMetadata();
        $result = array_merge($metadata['EntityType'], $metadata['ComplexType']);
        $cache->set($cacheKey, $result);

        return $result;
    }

    public function getTableSchema($name, $refresh = false)
    {
        $cache = \yii::$app->cache;
        $cacheKey = __CLASS__ . __FUNCTION__ . var_export($this->getCustomColumnsTypes(), true) . '9' . $name;
        if ($schema = $cache->get($cacheKey)) {
            return $schema;
        }

        $metadata = $this->getMetadata();

        foreach ($metadata as $params) {
            if ($params['@attributes']['Name'] === $name) {
                break;
            }
        }

        if (empty($params['Key'])) {
            $primaryKey = ['Ref_Key', 'LineNumber'];
        } else if (empty($params['Key']['PropertyRef']['@attributes'])) {
            $primaryKey = [];
            foreach ($params['Key']['PropertyRef'] as $properyRef) {
                $primaryKey[] = $properyRef['@attributes']['Name'];
            }
        } else {
            $primaryKey = $params['Key']['PropertyRef']['@attributes'];
            $primaryKey = array_values($primaryKey);
        }

        $columns = [];
        foreach ($params['Property'] as $property) {
            $property = $property['@attributes'];
            if (strpos($property['Type'], 'Collection') !== false) {
                continue;
            }

            $customColumnsTypes = $this->getCustomColumnsTypes();
            if (!empty($customColumnsTypes[$name]) && !empty($customColumnsTypes[$name][$property['Name']])) {
                $type = $customColumnsTypes[$name][$property['Name']];
            }  else {
                $type = $this->getColumnDbType($property['Type']);
            }

            $column = new ColumnSchema([
                'name' => $property['Name'],
                'type' => $this->getColumnAbstractType($property['Type']),
                'allowNull' => $property['Nullable'] === 'true',
                'phpType' => $this->getColumnPhpType($property['Type']),
                'dbType' => $type,
            ]);
            $columns[$column->name] = $column;
        }

        $tableSchema = new TableSchema([
            'name' => $name,
            'fullName' => $name,
            'primaryKey' => $primaryKey,
            'columns' => $columns
        ]);

        $cache->set($cacheKey, $tableSchema);

        return $tableSchema;
    }

    protected function getColumnDbType($type) {
        return $type;
    }

    protected function getColumnAbstractType($type) {
        return $this->getColumnPhpType($type);
    }

    protected function getColumnPhpType($type) {
        $typesMap = [
            'Edm.Guid' => 'string',
            'Edm.Boolean' => 'boolean',
            'Edm.String' => 'string',
            'Edm.Int16' => 'int',
            'Edm.Int32' => 'int',
            'Edm.Int64' => 'int',
            'Edm.Double' => 'double',
        ];
        if (empty($typesMap[$type])) {
            return $type;
        }

        return $typesMap[$type];
    }
}