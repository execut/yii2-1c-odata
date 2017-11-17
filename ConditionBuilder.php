<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 10/18/17
 * Time: 6:04 PM
 */

namespace execut\oData;


use yii\base\BaseObject;
use yii\base\Exception;

class ConditionBuilder extends BaseObject
{
    public $tableSchema = null;
    protected function isEmptyGuid($value) {
        return $value === null;
    }

    public function buildColumnCondition($column, $value, $operator = null) {
        if ($operator == null) {
            $operator = $this->detectOperatorByColumnType($column);
        }

        $value = $this->escapeValue($value);

        switch ($operator) {
            case 'substringof':
                return 'substringof(\'' . $value . '\', ' . $column . ')';
                break;
            case 'like':
                return 'like(' . $column . ', \'' . $value . '\')';
                break;
            case 'eqGuid':
                if ($this->isEmptyGuid($value)) {
                    return;
                }

                return $column . ' eq guid\'' . $value . '\'';
                break;
            case 'eq':
                return '\'' . $value . '\' eq ' . $column . '';
                break;
            case 'bool':
                $value = (bool) $value;
                if ($value) {
                    $value = 'true';
                } else {
                    $value = 'false';
                }

                return $column . ' eq ' . $value;
                break;
            case 'castToGuid':
                $type = $this->getColumnType($column);

                return $column . ' eq cast(guid\'' . $value . '\', \'' . $type . '\')';
        }
    }

    protected function getColumnType($name) {
        $column = $this->tableSchema->getColumn($name);
        if (!$column) {
            throw new Exception('Column "' . $name . '" is not found');
        }

        return $column->dbType;
    }

    public function escapeValue($value) {
        return str_replace(['\'', '%27', "\n", "\r"], '', $value);
    }

    public function detectOperatorByColumnType($column) {
        $type = $this->getColumnType($column);
        switch ($type) {
            case 'text2':
                return 'substringof';
                break;
            case 'text':
                return 'like';
                break;
            case 'Edm.Guid':
                return 'eqGuid';
                break;
            case 'Edm.String':
            case 'Edm.Int64':
            case 'Edm.Double':
            case 'Edm.Int':
                return 'eq';
                break;
            case 'Edm.Boolean':
                return 'bool';
                break;
            default:
                return 'castToGuid';
        }
    }
}