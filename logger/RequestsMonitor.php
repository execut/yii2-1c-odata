<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 11/9/17
 * Time: 9:12 AM
 */

namespace execut\oData\logger;


use detalika\base\bootstrap\Common;
use execut\oData\Exception;
use execut\oData\LongRequestsException;
use execut\requestsMonitor\models\Request;
use execut\requestsMonitor\Monitor;
use execut\requestsMonitor\requestsStorage\adapter\ActiveRecord;
use execut\requestsMonitor\requestsStorage\adapter\Cache;
use yii\helpers\Html;

class RequestsMonitor extends \Kily\Tools1C\OData\Profiler
{
    protected $monitor = null;

    /**
     * @return Monitor
     */
    public function getMonitor() {
        if ($this->monitor === null) {
            $this->monitor = \yii::createObject([
                'class' => \execut\requestsMonitor\Monitor::class,
                'maxRequests' => 10,
                'requestTimeLimit' => 30 * 1000 * 1000,
                'requestsStorage' => [
                    'class' => \execut\requestsMonitor\requestsStorage\MutexStorage::class,
                    'isCheckProcessesExists' => true,
                    'adapter' => [
                        'class' => ActiveRecord::class,
                        'defaultAttributes' => [
                            'service_id' => Common::SERVICE_ONE_C,
                        ]
                    ]
//                    'cacheKey' => 'odataRequestsStorage',
                ],
                'handler' => [
                    'class' => \execut\requestsMonitor\handler\ExceptionHandler::class,
                    'exceptionClass' => LongRequestsException::class,
                    'exceptionMessage' => 'Many long requests to odata. Requests count limit: {maxRequests}, time limit: {requestTimeLimit} useconds, current time {currentTime} useconds',
                ]
            ]);
        }

        return $this->monitor;
    }

    protected $requestId = null;
    public function begin()
    {
        $method = Request::getMethodFromString($this->request->getMethod());
        if (!$method) {
            throw new Exception('Undefined method ' . $this->request->getMethod());
        }

        $requestParams = [
            'url' => substr(trim($this->request->getHost(), '/') . $this->request->getUrl(), 0, 2048),
            'post_params' => \mb_substr(serialize($this->request->getOptions()), 0, 8192),
            'method' => $method,
        ];

        $this->requestId = $this->getMonitor()->startRequest($requestParams);
    }

    protected function getRequestId() {
        return $this->request->getUrl() . '/' . $this->request->getMethod() . '/' . serialize($this->request->getOptions()) . '/' . microtime();
    }

    public function end()
    {
        $this->getMonitor()->finishRequest($this->requestId);
    }
}