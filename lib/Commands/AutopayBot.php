<?php

namespace NPF\Commands;

use NPF\Constant;
use NPF\Helpers;
use NPF\Service\DummyPayment;
use NPF\Service\DummyService;
use NPF\Service\Payment;
use NPF\Service\SOAP;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use NPF\Logger;

/**
 * Консольная командя для запуска автоплатежного бота.
 */
class AutopayBot extends Command
{
    private $soapService = null;
    private $payService = null;

    private $logger = null;
    private $logContents = [];

    private $helper = null;

    private $OrderNumber;
    private $countOrderNumber;

    private $debug = null;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('app:run_autyopay')
            ->setDescription('Run the execution of auto payments')
            ->addOption(
                'debug',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'You must specify the debug status'
            )->addOption(
                'env',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Runtime Environment'
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->debug = $input->getOption('debug');
        $env = $input->getOption('env');

        if ($env !== 'prod') {
            $env = 'dev';
        }

        $this->Init($this->debug, $env);

        //Получаем список автоплатежей
        $autopayments = $this->soapService->GetAutoPayHistList();

        if (!empty($autopayments) && is_array($autopayments['GetAutoPayHistList'])) {
            //В цикле обрабатываем все полученные АП
            foreach ($autopayments['GetAutoPayHistList'] as $autopay) {
                if ($clientId = $this->getContractNumber($autopay)) {
                    if ($orderId = $this->registerOrder($autopay, $clientId)) {
                        $this->payOrder($autopay, $orderId);
                    }
                }
                $this->Log();   //Сохраняем накопленный  статус в логах
            }
        } else {
            if ($this->logger) {
                $this->logger->info('Данные отсутствуют, работа закончена');
            }
        }
    }

    protected function Init($debug, $env)
    {
        /*
         * Инициируем логер
         */
        $this->logger = new Logger\Logger();

        // Логи будем писать в файл
        $this->logger->routes->attach(new Logger\Routes\FileRoute([
            'isEnable' => true,
            'filePath' => $this->logger->getPathToFile(),
        ]));

        $this->helper = new Helpers;

        if ($debug) {
            $this->soapService = new DummyService();
            $this->payService = new DummyPayment();
        } else {
            if ($env === 'prod') {
                $this->soapService = new SOAP(Constant::NPF_WSDL, ['exceptions' => true], $this->logger, $debug);
                $this->payService = new Payment(Constant::SB_API, Constant::MERCHANT_LOGIN, Constant::MERCHANT_PASSWORD);
            } else {
                $this->soapService = new SOAP(Constant::NPF_WSDL_TEST, ['exceptions' => true], $this->logger, $debug);
                $this->payService = new Payment(Constant::SB_API_TEST, Constant::MERCHANT_LOGIN_TEST, Constant::MERCHANT_PASSWORD_TEST);
            }
        }
    }

    protected function getContractNumber(&$autopay)
    {
        $clientId = '';

        //Реализуем проверку на замену (связано со старой ошибкой в первом ЛК)
        $clientId = $this->helper->getClientIdReplacement($autopay['ContractNumber']);

        //реализуем проверку наличия связки через getBindings.do
        //(раньше просто требовали дергать этот сервис, незнамо зачем)
        $rsRest = $this->payService->getBindings(['clientId' => $clientId]);
        if ($rsRest['errorCode'] !== 0) {
            $this->disableAutopay($autopay);
            $this->logContents = ['status' => 'errorGetBindings', 'description' => "clientId={$clientId}"];
        }

        return $clientId;
    }

    /**
     * Функция регистрирует автоплатеж в шлюзе сбербанка и отчитывается в НПФ.
     *
     * @param $item/array AutoPayHistList => [AutoPayGUID,PayDate,PayAmount,AutoPayHistGUID]
     *
     * @return string orderId - идентификатор платежа
     */
    protected function registerOrder(array &$item, $ContractNumber)
    {
        $orderId = '';

        //регистрируем автоплатеж
        $rsPayment = $this->payService->register([
            'orderNumber' => $this->getOrderNumber(),
            'amount' => $item['PayAmount'] * 100, // convert
            'returnUrl' => 'fake', // sberbank api workaround
            'clientId' => $ContractNumber,
            'bindingId' => strtolower($item['BindingID']),
            'description' => 'autopayment',
            'expirationDate' => $this->helper->getExpirationDate(),
        ]);

        if ($rsPayment['errorCode']) {
            $this->logContents = ['status' => 'errorRegistered', 'description' => $rsPayment['errorMessage']];
            //отключить АП так как errorCode приходит только при ошибке
            $this->disableAutopay($item);
        } else {
            $orderId = $rsPayment['orderId'];
            $this->logContents['register'] = ['status' => 'registered', 'description' => $rsPayment['orderId']];

            //Передаем данные НПФ о состоянии автоплатежа (зарегистрировали)
            $rsSendResultHPF = $this->soapService->ChangeAutoPayHist($item['AutoPayHistGUID'], 'registered', $rsPayment['orderId']);
        }

        return $orderId;
    }

    /**
     * Функция осуществляет проведение автоплатежа и отписывается в НПФ и в логи.
     *
     * @param array $autopay
     * @param $orderId
     */
    protected function payOrder(array &$autopay, $orderId)
    {
        $rsPayment = $this->payService->paymentOrderBinding([
            'mdOrder' => $orderId,
            'bindingId' => strtolower($autopay['BindingID']),
        ]);

        $payStatus = [];

        if ($rsPayment['https_code'] != 200) {
            //Отчитываемся в лог, что не смогли достучаться до платежного шлюза
            $this->logContents = ['status' => 'errorPay', 'description' => "order pay request curl error, https_status={$rsPayment['https_code']}"];
        } else {
            //Проверяем статус проведения (если не пустые "errorCode" и "errorMessage" значит проблемы)
            if ($rsPayment['errorCode'] && $rsPayment['errorMessage']) {
                //Отчитываемся в логи об ошибках
                $this->logContents = ['status' => 'errorPay', 'description' => $rsPayment['errorMessage']];

                if ($rsPayment['errorCode'] == 2) {
                    //если errorCode == 2 и errorMessage == Связка не найдена, отключаем автоплатеж, чтоб не тревожить пользователя
                    $this->disableAutopay($autopay);
                }
            } else {
                //Получаем расширенный статус автоплатежа
                $payStatus = $this->payService->getOrderStatusExtended(['orderId' => $orderId]);

                if ($payStatus['https_code'] != 200) {
                    $this->logContents = ['status' => 'errorStatus', 'description' => "Can't get status after order paid, but seems success"];
                } else {
                    if ($payStatus['orderStatus'] == 2) {
                        $this->logContents = ['status' => 'paid', 'description' => $payStatus['errorMessage']];
                    } else {
                        $this->logContents = ['status' => 'error', 'description' => implode(',', [
                            $payStatus['orderStatus'],
                            $payStatus['errorMessage'],
                            $payStatus['actionCode'],
                            $payStatus['actionCodeDescription'],
                        ])];
                    }
                }
            }
        }
        //Передаем данные НПФ о состоянии автоплатежа (зарегистрировали)
        $rsSendResultHPF = $this->soapService->ChangeAutoPayHist(
            $autopay['AutoPayHistGUID'],
            $this->logContents['status'],
            $this->logContents['description']
        );

        // disable autopay by error status
        if (!empty($payStatus) && 2 != $payStatus['orderStatus']) {
            if ($this->helper->getDeactivateStatus($payStatus['actionCode'])) {
                $this->logContents = [
                    'status' => 'errorPay',
                    'description' => "disable autopay by order error, actionCode = {$payStatus['actionCode']}, errorStatus = " . $this->statusDesc($payStatus['actionCode']),
                ];
                $this->disableAutopay($autopay);
            } else {
                $this->logContents = [
                    'status' => 'errorPay',
                    'description' => "order error in valid array, skip autopay update. AutoPayGUID={$autopay['AutoPayGUID']}",
                ];
            }
        }
    }

    /**
     * Генерируем уникальный идентификатор автоплатежа.
     *
     * @return string
     */
    protected function getOrderNumber()
    {
        if (!$this->OrderNumber) {
            $this->OrderNumber = 'APB-' . (new \DateTime())->format('dmYHi') . '-';
            $this->countOrderNumber = 0;
        }

        return $this->OrderNumber . str_pad(++$this->countOrderNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Отключает автоплатеж на стороне НПФ.
     *
     * @param $data / array
     *
     * @return int
     */
    private function disableAutopay($data)
    {
        $return = false;
        $rs = $this->soapService->ChangeAutoPay(
            $data['AutoPayGUID'],
            $data['PayDay'],
            $data['Periodicity'],
            2,
            $data['PayAmount']
        );
        if ($rs) {
            $return = true;
        }

        return $return;
    }

    /**
     * По коду ошибки ответа формирует описание  ошибки.
     *
     * @param int $code - код ошибки
     *
     * @return string - описание
     */
    private function statusDesc($code = 0)
    {
        switch ($code) {
            case 101:
                $desc = 'Истек срок действия карты';
                break;
            case 107:
            case 120:
                $desc = 'Карта заблокирована';
                break;
            default:
                $desc = 'Операция отклонена. Обратитесь в НПФ Сбербанка, тел.: 8 800 555 00 41';
        }

        return $desc;
    }

    protected function Log()
    {
        if ($this->logger) {
            $this->logger->info($this->logContents['status'], $this->logContents['description']);
        }
    }

    protected function debugLog()
    {
        if ($this->logger && $this->debug) {
            $this->logger->info($this->logContents['status'], $this->logContents['description']);
        }
    }
}
