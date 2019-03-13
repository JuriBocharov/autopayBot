<?php

namespace NPF\Service;

/**
 * Класс заглушка для эмуляции сервиса оплаты.
 */
class DummyPayment
{
    /**
     * Проверяет наличие связки для проведения АП
     * @param array $params
     *
     * @return array
     *
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function getBindings(array $params)
    {
        return [
            "errorCode" => "0",
            "errorMessage" => "Успешно",
            "bindings" => [
                [
                    "bindingId" => "8194d922-716d-7e59-b0bc-921700003f08",
                    "maskedPan" => "639002XXXXXX6381",
                    "expiryDate" => "202108",
                    "paymentWay" => "CARD"
                ],
            ],
            'https_code' => 200,
        ];
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
        return  [
            "orderId" => "95af4473-ed21-7b9b-b4db-3eea00003f08",
            "formUrl" => "https://securepayments.sberbank.ru/payment/merchants/npfsb/payment_ru.html?mdOrder=95af4473-ed21-7b9b-b4db-3eea00003f08",
            'https_code' => 200,
        ];
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
        return [
            "redirect" => 'fake?orderId=3ca43a25-985c-750f-3ca4-3a250000414b&lang=ru',
            "info" => 'Ваш платёж обработан, происходит переадресация...',
            "errorCode" => 0,
            'https_code' => 200,
        ];

        /*
         * Плохой ответ

        [
            'error' => 'Операция отклонена. Проверьте введенные данные, достаточность средств на карте и повторите операцию.<br>',
            'errorCode' => 0,
            'processingErrorType' => 'CLIENT_ERROR',
            'errorMessage' => 'Операция отклонена. Проверьте введенные данные, достаточность средств на карте и повторите операцию.<br>'
            'https_code' => 200
        ]
        */
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
        $badRequest = [
            "errorCode" => 0,
            "errorMessage" => "Успешно",
            "orderNumber" => 177605,
            "orderStatus" => 0,
            "actionCode" => 116,
            "actionCodeDescription" => "Операция отклонена. Проверьте введенные данные, достаточность средств на карте и повторите операцию.",
            "amount" => 50000,
            "currency" => 643,
            "date" => 1530425236949,
            "orderDescription" => "autopayment",
            "merchantOrderParams" => [
            ],

            "attributes" => [
                "0" => [
                    "name" => "mdOrder",
                    "value" => "51fab3b7-da88-77d9-51fa-b3b70000414b",
                ],

            ],

            "cardAuthInfo" => [
                "expiration" => 201909,
                "cardholderName" => "Andrey Grishin",
                "approvalCode" => 000000,
                "pan" => "546938**8816",
            ],

            "bindingInfo" => [
                "clientId" => "999-9003-000022225",
                "bindingId" => "b90677cd-1ece-4386-a585-cb7c3b24b138",
            ],

            "terminalId" => 10049728,
            "paymentAmountInfo" => [
                "paymentState" => "CREATED",
                "approvedAmount" => 0,
                "depositedAmount" => 0,
                "refundedAmount" => 0,
            ],

            "bankInfo" => [
                "bankName" => "SBERBANK OF RUSSIA",
                "bankCountryCode" => "RU",
                "bankCountryName" => "Россия",
            ],
            'https_code' => 200
        ];

        return [
            "errorCode" => "0",
            "errorMessage" => "Успешно",
            "orderNumber" => "009-144921-00000529799362",
            "orderStatus" => 2,
            "actionCode" => 0,
            "actionCodeDescription" => "",
            "amount" => 100000,
            "currency" => "643",
            "date" => 1552094313579,
            "orderDescription" => "248211",
            "ip" => "90.188.250.207",
            "merchantOrderParams" => [],
            "attributes" => [
                [
                    "name" => "mdOrder",
                    "value" => "95af4473-ed21-7b9b-b4db-3eea00003f08"
                ],
            ],
            "cardAuthInfo" => [
                "expiration" => "201903",
                "cardholderName" => "YURY AZARENOK",
                "approvalCode" => "249873",
                "pan" => "676280XXXXXX4900"
            ],
            "bindingInfo" => [
                "clientId" => "999-9008-000824565",
                "bindingId" => "3692cb06-491c-4d5f-a54a-0f7b1b14fffc"
            ],
            "authDateTime" => 1552094357491,
            "terminalId" => "10049728",
            "authRefNum" => "906886884958",
            "paymentAmountInfo" => [
                "paymentState" => "DEPOSITED",
                "approvedAmount" => 100000,
                "depositedAmount" => 100000,
                "refundedAmount" => 0
            ],
            "bankInfo" => [
                "bankName" => "SBERBANK OF RUSSIA",
                "bankCountryCode" => "RU",
                "bankCountryName" => "Россия"
            ],
            'https_code' => 200,
        ];
    }
}
