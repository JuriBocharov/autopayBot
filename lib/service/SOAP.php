<?php

namespace NPF\Autopay\Bot\Service;

/**
 * Класс для подключения к сервису НПФ Сбербанка.
 */
class SOAP
{
    protected $soapClient;

    public function __construct()
    {
        // todo вынести настройки
        // todo error
        $this->soapClient = new \SoapClient(); // todo add wsdl
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

        return $result;
    }

    /**
     * Получить очередь на проведение автоплатежей.
     */
    protected function GetAutoPayHistList()
    {
        $params = [];
        $userParams = [];
//        $userParams = ['Company' => 'ЛК', 'UserID' => $UserID, 'UserLogin' => $UserLogin];
        $result = $this->WSRequest(__FUNCTION__, $params, $userParams);

        return $result['ResponseParams'];
    }

    /**
     * Изменить статус проведения автоплатежа.
     */
    protected function ChangeAutoPayHist()
    {
        // todo write soap call
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
        // todo userParams? UserLogin? UserID?
//        $userParams = ['Company' => 'ЛК', 'UserID' => $UserID, 'UserLogin' => $UserLogin];
        $params = array_merge(['Method' => $method], $params);

        // todo get json soap call
        $result = $this->doSoapCall(
            'WSRequest',
            [
                'UserParams' => json_encode($userParams, JSON_UNESCAPED_UNICODE),
                'RequestParams' => json_encode($params, JSON_UNESCAPED_UNICODE),
            ]
        );

        // todo json? to array?
        return json_decode($result, true);
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
}
