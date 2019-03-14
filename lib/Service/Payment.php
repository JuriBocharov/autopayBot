<?php

namespace NPF\Service;

use GuzzleHttp;
use GuzzleHttp\Psr7\Request;

/**
 * Класс для подключения к сервису оплаты НПФ Сбербанка.
 */
class Payment
{
    /**
     * @var string
     */
    protected $url;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var GuzzleHttp\Client
     */
    protected $client;

    public function __construct($url, $userName, $password)
    {
        $this->url = $url;

        $this->options = [
            'userName' => $userName,
            'password' => $password,
        ];

        if (!$this->client) {
            $this->client = new GuzzleHttp\Client();
        }
    }

    /**
     * @param GuzzleHttp\Client $client
     */
    public function setClient(GuzzleHttp\Client $client)
    {
        $this->client = $client;
    }

    /**
     * Проверяет наличие связки для проведения АП
     *
     * @param array $params
     *
     * @return array
     *
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function getBindings(array $params)
    {
        return $this->doRequest(__FUNCTION__, $params, 'POST');
    }

    /**
     * Регистрирует платеж на сервисе и возвращает информацию о платеже.
     * В том числе и ссылку для перехода на оплату.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function register(array $params)
    {
        return $this->doRequest(__FUNCTION__, $params, 'POST');
    }

    /**
     * Проводит платеж по связкам
     *
     * @param array $params
     *
     * @return array
     *
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function paymentOrderBinding(array $params)
    {
        return $this->doRequest(__FUNCTION__, $params, 'POST');
    }

    /**
     * Возвращает расширенную информацию о статусе платежа.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function getOrderStatusExtended(array $params)
    {
        return $result = $this->doRequest(__FUNCTION__, $params, 'POST');
    }

    /**
     * Формирует данные для запроса к сервису, обрабатывает и возвращает ответ.
     *
     * @param string $operation
     * @param array  $params
     * @param string $method
     *
     * @return array
     *
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    protected function doRequest($operation, array $params, $method = 'GET')
    {
        // todo log
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $params = http_build_query(array_merge($params, $this->options));

        //try {
        $request = new Request($method, $this->createOperationUrl($operation), $headers, $params);

        $result = $this->client->send($request);
        $bodyCotents = json_decode($result->getBody()->getContents(), true);
        $bodyCotents['https_code'] = $result->getStatusCode();

        return  $bodyCotents;
        /*} catch (\Exception $exception) {
            return [];
            // todo log
            // todo return
        }*/
    }

    /**
     * Формирует ссылку для запроса.
     *
     * @param string $operation
     *
     * @return string
     */
    protected function createOperationUrl($operation)
    {
        return $this->url . $operation . '.do';
    }
}
