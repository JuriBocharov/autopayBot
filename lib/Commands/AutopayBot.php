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

    private $helper = null;

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
        $debug = $input->getOption('debug');
        $env = $input->getOption('env');

        if ($env !== 'prod') {
            $env = 'dev';
        }

        $this->Init($debug, $env);

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
            }
        } else {
            //todo: отписываемся в логах что нету ничего, а значит не проводили
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
        //Запрашиваем расширенную информацию, нужен номер договора и еще некоторые данные о автоплатеже
        $autopayExt = $this->soapService->GetAutoPayByGUID($autopay['AutoPayGUID']);
        if (!empty($autopayExt['AutoPayDetail']) && is_array($autopayExt['AutoPayDetail'])) {
            $autopay = array_merge($autopay, $autopayExt['AutoPayDetail']);
            $clientId = $this->helper->getClientIdReplacement($autopay['ContractNumber']);

            //реализуем проверку наличия связки через getBindings.do
            $rsRest = $this->payService->getBindings(['clientId' => $clientId]);
            if ($rsRest['errorCode'] !== 0) {
                //TODO: отключить АП (уточнить метод отключения)
                $this->DisableAutoPayByBot($autopay['AutoPayGUID']);
                //todo: закинуть в логирование отсутствие связки
                $clientId = '';
            }
        } else {
            //Не нашли такого Автоплатежа (сомнительно, но лучше страхуемся)
            //Для отключения недостаточно данных или используем  DisableAutoPayByBot
            //TODO: отключить АП (уточнить метод отключения)
            $this->DisableAutoPayByBot($autopay['AutoPayGUID']);
            //todo: закинуть в логирование отсутствие связки
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
        //Поверяем clientId и вносим корректировки если этот номер есть в ссписках попраки
        $clientId = $this->helper->getClientIdReplacement($ContractNumber);

        //регистрируем автоплатеж
        $rsPayment = $this->payService->register([
            'orderNumber' => 0, //todo: Нужен уникальный идентификатор, придумать скрипт генерирования
            'amount' => $item['PayAmount'] * 100, // convert
            'returnUrl' => 'fake', // sberbank api workaround
            'clientId' => $clientId,
            'bindingId' => strtolower($item['BindingID']),
            'description' => 'autopayment',
            'expirationDate' => $this->helper->getExpirationDate(),
        ]);

        if ($rsPayment['errorCode']) {
            $logContents = ['status' => 'errorRegistered', 'description' => $rsPayment['errorMessage']];
            //TODO: отключить АП (уточнить метод отключения)
            $this->disableAutopay($item['AutoPayDetail']);
        } else {
            $orderId = $rsPayment['orderId'];
            $logContents['register'] = ['status' => 'registered', 'description' => $rsPayment['orderId']];

            //Передаем данные НПФ о состоянии автоплатежа (зарегистрировали)
            $rsSendResultHPF = $this->soapService->ChangeAutoPayHist(
                $item['AutoPayGUID'],
                $logContents['status'],
                $logContents['description']
            );
        }


        return $orderId;
    }

    /**
     * Функция осуществляет проведение автоплатежа и отписывается в НПФ и в логи
     * @param array $autopay
     * @param $orderId
     */
    protected function payOrder(array &$autopay, $orderId )
    {
        $rsPayment = $this->payService->paymentOrderBinding([
            'mdOrder' => $orderId,
            'bindingId' => strtolower($autopay['BindingID']),
        ]);

        if ($rsPayment['https_code']) {
            //TODO: Нужно будет сгрузить в логер
            self::log('order pay request curl error', $rsPayment['https_code']);
        } else {
            //Проверяем статус проведения (если не пустые "errorCode" и "errorMessage" значит проблемы)
            if ($rsPayment['errorCode'] && $rsPayment['errorMessage']) {
                //TODO: Нужно будет сгрузить в логер
                $logContents = ['status' => 'errorPay' , 'description' => $rsPayment['errorMessage']];
                if ($rsPayment['errorCode'] == 2) {
                    //если errorCode == 2 и errorMessage == Связка не найдена,
                    // отключаем автоплатеж, чтоб не тревожить пользователя
                    $this->disableAutopay($autopay);
                }
            } else {
                //Получаем расширенный статус автоплатежа
                $payStatus = $this->payService->getOrderStatusExtended(['orderId' => $orderId]);

                if ($payStatus['https_code']) {
                    //TODO: Нужно будет сгрузить в логер  'order pay status request curl error';
                    $logContents = ['status' => 'errorStatus' , 'description' => "Can't get status after order paid, but seems success"];

                } else {
                    if ($payStatus['orderStatus'] == 2) {
                        $logContents = ['status' => 'paid' , 'description' => $payStatus['errorMessage']];
                    } else {
                        $logContents = ['status' => 'error' , 'description' => implode(',', [
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
            $logContents['status'],
            $logContents['description']
        );
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

    private function DisableAutoPayByBot($AutoPayGUID)
    {
        $return = false;
        $rs = $this->soapService->DisableAutoPayByBot($AutoPayGUID);
        if ($rs) {
            $return = true;
        }

        return $return;
    }

    /**
     * По коду ошибки ответа формирует описание  ошибки
     *
     * @param int $code - код ошибки
     *
     * @return string - описание
     */
    private static function statusDesc($code = 0)
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
}
