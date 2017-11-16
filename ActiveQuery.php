<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 9/20/17
 * Time: 3:24 PM
 */

namespace execut\oData;


use Kily\Tools1C\OData\Client;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class ActiveQuery extends \yii\db\ActiveQuery
{
    public function findWith($with, &$models)
    {
        return parent::findWith($with, $models);
    }

    /**
     * Executes the query and returns a single row of result.
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return array|bool the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     */
    public function one($db = null)
    {
        if ($this->emulateExecution) {
            return false;
        }

        $this->limit(1);
        $data = $this->getData();
        if ($data) {
            $models = $this->populate($data);
            return reset($models) ?: null;
        } else {
            return null;
        }
    }

    /**
     * Executes the query and returns all results as an array.
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all($db = null)
    {
        if ($this->emulateExecution) {
            return [];
        }

        $rows = $this->getData();
        return $this->populate($rows);
    }

    public function count($q = '*', $db = null) {
        $table = $this->getTableName();
        $filters = $this->getFilters();
        $client = $this->getClient();
        $count = 0;
        foreach ($filters as $filter) {
            $count += $client->{$table . '/$count'}->get(null, $filter, $this->getOptions());
            if (!$count) {
                break;
            }
        }

        if (!empty($count)) {
            return $count;
        }
    }

    protected function getFilters() {
        if ($this->primaryModel !== null) {
            $idKey = key($this->link);
            $primaryKey = current($this->link);
            if ($this->via) {
                $viaIdKey = key($this->via[1]->link);
                $viaPrimaryKey = current($this->via[1]->link);
                $viaQuery = $this->via[1];
                $this->andWhere([
                    $idKey => $viaQuery->select($primaryKey),
                ]);
            } else {
                $id = $this->primaryModel->$primaryKey;
                if (empty($id)) {
                    return ['true eq false'];
                }

                $this->andWhere([
                    $idKey => $id
                ]);
            }
        }

        $where = $this->where;

        if (is_array($where)) {
            $where = $this->filterCondition($where);
        }

        if ($where === null) {
            return ['true eq true'];
        }

        $filters = $this->buildFilters($where);

        return $filters;
    }

    public function getClient() {
        $modelClass = $this->modelClass;

        return $modelClass::getClient();
    }

    /**
     * @param $client
     */
    protected function getData()
    {
        if (!empty($this->primaryModel)) {
            $idKey = key($this->link);
            $primaryKey = current($this->link);
            if ($this->via) {
                $viaIdKey = key($this->via[1]->link);
                $viaPrimaryKey = current($this->via[1]->link);
                $viaQuery = $this->via[1];
                $this->andWhere([
                    $idKey => $viaQuery->select($primaryKey),
                ]);
            } else {
                $this->andWhere([
                    $idKey => $this->primaryModel->$primaryKey
                ]);
            }
        }

        $tableName = $this->getTableName();
        $options = $this->getOptions();
        $client = $this->getClient();
        $filters = $this->getFilters();
        $result = [];
        foreach ($filters as $filter) {
            $data = $client->$tableName->get(null, $filter, $options);
            if (!empty($data['value'])) {
                $result = array_merge($result, $data['value']);
            }
        }

        return $result;
    }

    protected function getOptions() {
        $query = [];
        if ($this->offset !== -1) {
            $query['$skip'] = $this->offset;
        }

        if ($this->limit !== -1) {
            $query['$top'] = $this->limit;
        }

        if (!empty($this->select)) {
            $query['$select'] = implode(',', $this->select);
        }

        if (!empty($this->orderBy)) {
            $query['$orderby'] = $this->buildOrder();
        }

        return [
            'query' => $query,
        ];
    }

    protected function buildOrder() {
        $orderParts = [];
        foreach ($this->orderBy as $attribute => $direction) {
            if (is_int($attribute)) {
                $orderParts[] = $direction;
                continue;
            }

            if ($direction === SORT_ASC) {
                $direction = 'asc';
            } else {
                $direction = 'desc';
            }

            $orderParts[] = $attribute . ' ' . $direction;
        }
        return implode(',', $orderParts);
    }

    /**
     * @return mixed
     */
    protected function getTableName()
    {
        $class = $this->modelClass;
        $tableName = $class::tableName();
        return $tableName;
    }

    const MAX_WHERE_LENGTH = 3000;
    protected function deleteDublicatesFromWhere($where) {
        $existed = [];
        foreach ($where as $whereKey => $item) {
            $key = serialize($item);
            if (isset($existed[$key])) {
                unset($where[$whereKey]);
            } else {
                $existed[$key] = true;
            }
        }

        return $where;
    }

    protected function buildFilters($where) {
        $where = $this->parseWhere($where);
        $where = array_filter($where);
        $where = $this->deleteDublicatesFromWhere($where);

        if (empty($where)) {
            return [];
        }
        $result = [];
        $filterPrefx = '';
        $arrayValue = null;
        foreach ($where as &$value) {
            if (is_array($value)) {
                if ($arrayValue !== null) {
                    throw new Exception('Many in where conditions is not supported');
                }

                $arrayValue = $value;
            } else {
                $result[] = $value;
            }
        }

        if (!empty($result)) {
            $filterPrefx = '(' . implode(' and ', $result) . ')';
            if ($arrayValue !== null) {
                $filterPrefx .= ' and ';
            }
        } else {
            $filterPrefx = '';
        }

        if ($arrayValue === null) {
            if (!empty($filterPrefx)) {
                return [$filterPrefx];
            } else {
                return [];
            }
        }

        $result = [];
        $currentFilterPostfix = '';
        foreach ($arrayValue as $key => $value) {
            if ($currentFilterPostfix === '') {
                $currentFilterPostfix = $value;
            } else {
                $currentFilterPostfix .= ' or ' . $value;
            }

            $filter = $filterPrefx . '(' . $currentFilterPostfix . ')';
            if (strlen($filter) > self::MAX_WHERE_LENGTH) {
                throw new Exception('Very big condition. Make it smaller');
            }

            if ($key + 1 == count($arrayValue) || strlen($filterPrefx . '(' . $currentFilterPostfix . ' or ' . $arrayValue[$key + 1]) > self::MAX_WHERE_LENGTH) {
                if ($currentFilterPostfix) {
                    $r = $filterPrefx . '(' . $currentFilterPostfix . ')';
                }

                $result[] = $r;
                $currentFilterPostfix = '';
            }
        }

        return $result;
    }

    /**
     * @param $where
     * @return string
     */
    protected function parseWhere($where, $condition = 'and')
    {
        if (is_string($where)) {
            return [$where];
        }

        $searchQueries = [];
        if (isset($where[0])) {
            $condition = $where[0];
            unset($where[0]);
            if (strtolower($condition) === 'ilike') {
                if (!empty($where[1]) && !empty($where[2]) && is_string($where[1]) && is_string($where[2])) {
                    $searchQueries[] = 'like(' . $where[1] . ',\'%' . $where[2] . '%\')';
                }
            } else if (strtolower($condition) === 'in') {
                if (!empty($where[1]) && !empty($where[2])) {
                    if (is_array($where[1])) {
                        if (count($where[1]) !== 1) {
                            throw new Exception('Many attributes of IN condition is not supported');
                        }

                        $where[1] = current($where[1]);
                    }

                    if (!(is_string($where[1]) && is_array($where[2]))) {
                        throw new Exception('This variation of in condition is not supported');
                    }

                    if ($where[2] instanceof \yii\db\ActiveQuery) {
                        $where[2] = $where[2]->column();
                    }

                    if (is_array($where[2])) {
                        $subWhere = [];
                        foreach ($where[2] as $v) {
                            $subWhere[] = $this->buildColumnCondition($where[1], $v);
                        }

                        $searchQueries[] = $subWhere;
                    } else {
                        $searchQueries[] = $this->buildColumnCondition($where[1], $where[2]);
                    }
                }
            } else {
                foreach ($where as $value) {
                    $subWhere = $this->parseWhere($value, $condition);
                    foreach ($subWhere as $where) {
                        $searchQueries[] = $where;
                    }
                }
            }
        } else {
            foreach ($where as $attribute => $value) {


                if ($value instanceof \yii\db\ActiveQuery) {
                    $value = $value->column();
                }

                if (is_array($value)) {
                    $subWhere = [];
                    if (empty($value)) {
                        $subWhere[] = 'true eq false';
                    } else {
                        foreach ($value as $v) {
                            $subWhere[] = $this->buildColumnCondition($attribute, $v);
                        }
                    }

                    $searchQueries[] = $subWhere;
                } else {
                    $searchQueries[] = $this->buildColumnCondition($attribute, $value);
                }
            }
        }

        return $searchQueries;
    }

    protected function buildColumnCondition($column, $value) {
        $class = $this->modelClass;
        $builder = new ConditionBuilder([
            'tableSchema' => $class::getTableSchema()
        ]);
        return $builder->buildColumnCondition($column, $value);
    }

    /**
     * Executes the query and returns the first column of the result.
     * @param Connection $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return array the first column of the query result. An empty array is returned if the query results in nothing.
     */
    public function column($db = null)
    {
        if ($this->emulateExecution) {
            return [];
        }

        if ($this->indexBy === null) {
            $data = $this->getData();
            $pks = $this->primaryModel::primaryKey();
            $result = [];
            foreach ($data as $row) {
                $key = [];
                foreach ($pks as $pk) {
                    $key[] = $row[$pk];
                }

                $key = implode('-', $key);

                $result[$key] = $row;
            }

            return $result;
        }

        if (is_string($this->indexBy) && is_array($this->select) && count($this->select) === 1) {
            if (!in_array($this->indexBy, $this->select)) {
                $this->select[] = $this->indexBy;
            }
        }

        $rows = $this->getData();
        $results = [];
        foreach ($rows as $row) {
            $value = reset($row);

            if ($this->indexBy instanceof \Closure) {
                $results[call_user_func($this->indexBy, $row)] = $value;
            } else {
                $results[$row[$this->indexBy]] = $value;
            }
        }

        return $results;
    }
}