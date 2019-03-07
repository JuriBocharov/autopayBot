<?php

namespace NPF\Autopay\Bot\Service;

use Psr\Log\LoggerInterface;

/**
 * Класс для подключения к сервису НПФ Сбербанка.
 */
class SOAP
{
    /*
     * @var SoapClient указатель на soap клиент
     */
    protected $soapClient;


    public function __construct($wsdl, array $options = null, LoggerInterface $logger, $debug = null)
    {
        // todo вынести настройки
        // todo error
        if (!$options) {
            $options = ['exceptions' => true];
        }
        $this->soapClient = new \SoapClient($wsdl, $options); // todo add wsdl
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
