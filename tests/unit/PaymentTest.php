<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use NPF\Autopay\Bot\Service\Payment;

/**
 * Класс для тестирования сервиса оплаты.
 */
class PaymentTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testRegister()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['result' => 'value'])),
        ]);

        $handler = HandlerStack::create($mock);

        $service = new Payment();
        $service->setClient(new Client(['handler' => $handler]));

        $result = $service->register(['testParam' => 'testValue']);

        $this->assertSame(['result' => 'value'], $result);
    }

    public function testPaymentOrderBinding()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['result' => 'value'])),
        ]);

        $handler = HandlerStack::create($mock);

        $service = new Payment();
        $service->setClient(new Client(['handler' => $handler]));

        $result = $service->paymentOrderBinding(['testParam' => 'testValue']);

        $this->assertSame(['result' => 'value'], $result);
    }

    public function testGetOrderStatusExtended()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['result' => 'value'])),
        ]);

        $handler = HandlerStack::create($mock);

        $service = new Payment();
        $service->setClient(new Client(['handler' => $handler]));

        $result = $service->paymentOrderBinding(['testParam' => 'testValue']);

        $this->assertSame(['result' => 'value'], $result);
    }
}
