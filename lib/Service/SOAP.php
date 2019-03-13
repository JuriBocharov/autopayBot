<?php

namespace NPF\Service;

use Psr\Log\LoggerInterface;
use SoapClient;

/**
 * Класс для подключения к сервису НПФ Сбербанка.
 */
class SOAP
{
    /*
     * @var SoapClient указатель на soap клиент
     */
    protected $soapClient;

    protected $loger = null;

    protected $debug = null;

    /**
     * SOAP constructor.
     *
     * @param $wsdl
     * @param array|null           $options
     * @param LoggerInterface|null $logger
     * @param null                 $debug
     */
    public function __construct($wsdl, array $options = null, LoggerInterface $logger = null, $debug = null)
    {
        if (!$options) {
            $options = ['exceptions' => true];
        }
        $this->soapClient = new SoapClient($wsdl, $options);

        if ($logger) {
            $this->loger = $logger;
        }

        if ($debug) {
            $this->debug = $debug;
        }
    }

    /**
     * Получение информации об автоплатеже по его GUID.
     *
     * @param string $AutoPayGUID
     * @param int    $UserID
     *
     * @return mixed
     */
    public function GetAutoPayByGUID($AutoPayGUID, $UserID = 0)
    {
        $result = $this->doSoapCall(__FUNCTION__, [
                'UserID' => trim($UserID),
                'AutoPayGUID' => trim($AutoPayGUID),
            ]
        );

        return $this->toArray($result);
    }

    /**
     * Обновление автоплатежа.
     *
     * @param string $UserID
     * @param string $AutoPayGUID
     * @param string $PayDay
     * @param string $PayAmount
     * @param string $Periodicity
     * @param string $AutoPayStatus
     *
     * @return bool
     */
    public function ChangeAutoPay($AutoPayGUID, $PayDay, $PayAmount, $Periodicity, $AutoPayStatus)
    {
        $result = $this->doSoapCall(
            'ChangeAutoPay',
            [
                'AutoPayGUID' => trim($AutoPayGUID),
                'PayDay' => $PayDay,
                'Periodicity' => $Periodicity,
                'AutoPayStatus' => $AutoPayStatus,
                'PayAmount' => $PayAmount,
            ]
        );

        return $result->ResponseStatus === 0;
    }

    /**
     * Отключение автоплатежа.
     * Сетит автоплатежу AutoPayStatus = 2
     * $args[0] string AutoPayGUID - внутренний глоб. ид. автоплатежа.
     *
     * @param array $args см. выше
     *
     * @return array
     */
    public function DisableAutoPayByBot($AutoPayGUID)
    {
        $result = $this->doSoapCall(
            'DisableAutoPayByBot',
            [
                'AutoPayGUID' => trim($AutoPayGUID),
            ]
        );

        return $result->ResponseStatus === 0;
    }

    /**
     * (Сохранение информации о проведении автоплатежа).
     *
     * $args[0] string AutoPayGUID - внутренний глоб. ид. автоплатежа
     * $args[1] datetime PayDate -  Дата проведения очередного автоплатежа
     * $args[2] double PayAmount - сумма автоплатежа
     * $args[3] string AutoPayHistStatus - Статус проведенного автоплатежа
     * $args[4] string AutoPayHistStatusDetail - Описание статуса проведенного автоплатежа
     * $args[5] string AutoPayURL - Ссылка на страницу с квитанцией по проведенному автоплатежу.
     *
     * @param array $args см. выше
     *                    $result['AutoPayHistGUID'] string - Глобальный идентификатор проведенного автоплатежа
     *
     * @return array
     */
    public function AddAutoPayHist($AutoPayGUID, $PayDate, $PayAmount, $AutoPayHistStatus, $AutoPayHistStatusDetail)
    {
        $result = $this->doSoapCall(
            'AddAutoPayHist',
            [
                'AutoPayGUID' => trim($AutoPayGUID),
                'PayDate' => trim($PayDate),
                'PayAmount' => trim($PayAmount),
                'AutoPayHistStatus' => trim($AutoPayHistStatus),
                'AutoPayHistStatusDetail' => trim($AutoPayHistStatusDetail),
            ]
        );

        if (isset($result->GUID)) {
            $return = $result->GUID;
        } else {
            $return = $this->toArray($result);
        }

        return $return;
    }

    /**
     * Получить очередь на проведение автоплатежей.
     */
    public function GetAutoPayHistList()
    {
        $params = [];
        $userParams = ['Company' => 'ЛК', 'UserID' => 0, 'UserLogin' => 'autopay@bot'];
        $resultWSRequest = $this->WSRequest(__FUNCTION__, $params, $userParams);
        $result = json_decode($resultWSRequest, true);

        return $result['ResponseParams'];
    }

    /**
     * Изменить статус проведения автоплатежа.
     *
     * @param $histGUID
     * @param $histStatus
     * @param $histStatusDetail
     *
     * @return mixed
     */
    public function ChangeAutoPayHist($histGUID, $histStatus, $histStatusDetail)
    {
        $userParams = ['Company' => 'ЛК', 'UserID' => 0, 'UserLogin' => 'autopay@bot'];
        $params = [
            'AutoPayHistGUID' => $histGUID,
            'AutoPayHistStatus' => $histStatus,
            'AutoPayHistStatusDetail' => $histStatusDetail,
        ];
        $userParams = ['Company' => 'ЛК', 'UserID' => 0, 'UserLogin' => 'autopay@bot'];
        $result = $this->WSRequest('ChangeAutoPayHist', $params, $userParams);

        return $result->Response->ResponseStatus;
    }

    /**
     * Универсальный метод, позволяющий направлять запросы в формате JSON
     * и получать ответы в формате JSON.
     *
     * @param string $method
     * @param array  $params
     * @param array  $userParams
     *
     * @return array
     */
    protected function WSRequest($method, array $params = [], array $userParams = ['Company' => 'ЛК'])
    {
        $params = array_merge(['Method' => $method], $params);

        $result = $this->doSoapCall(
            'WSRequest',
            [
                'UserParams' => json_encode($userParams, JSON_UNESCAPED_UNICODE),
                'RequestParams' => json_encode($params, JSON_UNESCAPED_UNICODE),
            ]
        );

        return $result;
    }

    /**
     * Делает запрос к SOAP-сервису.
     *
     * @param string $method
     * @param array  $params
     *
     * @return array
     */
    protected function doSoapCall($method, array $params = [])
    {
        // todo log
        $soapResponse = $this->soapClient->__soapCall($method, $params);
        $result = $this->parseSoapResult($soapResponse);

        return $result;
    }

    /**
     * @param \stdClass $result
     *
     * @return array
     */
    protected function parseSoapResult($result)
    {
        // todo is object?
        if (!is_object($result)) {
            throw new Exception(
                'Empty response from soap, awaits stdClass object'
            );
        }

        if (!empty($result->ResponseStatus) && $result->ResponseStatus === 99) {
            throw new Exception($result->ResponseMessage);
        } elseif (!empty($result->Response->ResponseStatus) && $result->Response->ResponseStatus === 99) {
            throw new Exception($result->Response->ResponseMessage);
        }

        // todo check status
        // todo log

        // todo check result?
        return $this->objectToArray($result);
    }

    /**
     * @param \Exception $e
     *
     * @throws \Sbnpf\Service\Exception
     */
    protected function catchException(\Exception $e)
    {
        if ($this->logger) {
            $this->logger->error($e->getMessage(), [
                'MODULE_ID' => 'sbnpf.service',
                'AUDIT_TYPE_ID' => 'sbnpf_service_soap_exception',
                'ITEM_ID' => get_class($e),
            ]);
        }

        throw new Exception($e->getMessage(), $e->getCode(), $e);
    }

    /**
     * Записывает информацию в лог.
     *
     * @param string $type
     * @param string $itemId
     * @param array  $params
     */
    protected function debugInfo($type, $itemId, array $params)
    {
        if ($this->logger && $this->debug) {
            $message = json_encode($params, JSON_UNESCAPED_UNICODE);
            $this->logger->info($message, [
                'MODULE_ID' => 'soap.service',
                'AUDIT_TYPE_ID' => $type,
                'ITEM_ID' => $itemId,
            ]);
        }
    }

    /**
     * Преобразует объект к массиву.
     *
     * @param object $value
     *
     * @return array
     */
    protected function objectToArray($value)
    {
        return (array) $value;
    }

    /**
     * Преобразует объект к массиву.
     *
     * @param mixed $value
     *
     * @return array
     */
    protected function toArray($value)
    {
        $return = null;

        if (is_object($value)) {
            $return = $this->toArray((array) $value);
        } elseif (is_array($value)) {
            $return = [];
            foreach ($value as $key => $item) {
                $return[$key] = $this->toArray($item);
            }
        } else {
            $return = $value;
        }

        return $return;
    }
}
