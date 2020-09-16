<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 10/31/19
 * Time: 5:05 PM
 */

namespace execut\oData\logger;


class Container extends \Kily\Tools1C\OData\Profiler
{
    public $loggers = [];
    protected $_loggers = [];
    public function begin()
    {
        $this->initLoggers();
        foreach ($this->getLoggers() as $logger) {
            $logger->begin();
        }
    }

    public function end()
    {
        foreach ($this->getLoggers() as $logger) {
            $logger->end();
        }
    }

    protected function initLoggers() {
        $loggers = $this->loggers;
        foreach ($loggers as $key => $logger) {
            if (is_array($logger)) {
                $loggers[$key] = \yii::createObject($logger);
            }
        }

        $this->loggers = $loggers;
    }

    protected function getLoggers() {
        foreach ($this->loggers as $logger) {
            $logger->request = $this->request;
        }

        return $this->loggers;
    }
}