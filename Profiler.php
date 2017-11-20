<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 11/9/17
 * Time: 9:12 AM
 */

namespace execut\oData;


use yii\helpers\Html;

class Profiler extends \Kily\Tools1C\OData\Profiler
{
    public function begin()
    {
        \yii::beginProfile($this->getMessage(), 'oData');
    }

    public function end()
    {
        \yii::endProfile($this->getMessage(), 'oData');
    }

    protected function getMessage() {
        return trim($this->request->getHost(), '/') . $this->request->getUrl() . ' (' . $this->request->getMethod() . '), options: ' . var_export($this->request->getOptions(), true);
    }
}