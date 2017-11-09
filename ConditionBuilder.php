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

    public function buildColumnCondition($column, $value) {
        $type = $this->getColumnType($column);
        switch ($type) {
            case 'text2':
                return 'substringof(\'' . $value . '\', ' . $column . ') eq true';
                break;
            case 'text':
                return 'like(' . $column . ', \'' . $value . '\') eq true';
                break;
            case 'Edm.Guid':
                if ($this->isEmptyGuid($value)) {
                    return;
                }

                return $column . ' eq guid\'' . $value . '\'';
                break;
            case 'Edm.String':
                return '\'' . $value . '\' eq ' . $column . '';
                break;
            case 'Edm.Int64':
            case 'Edm.Double':
            case 'Edm.Int':
                return $column . ' eq ' . $value;
                break;
            case 'Edm.Boolean':
                if ($value) {
                    $value = 'true';
                } else {
                    $value = 'false';
                }

                return $column . ' eq ' . $value;
                break;
            default:
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
}