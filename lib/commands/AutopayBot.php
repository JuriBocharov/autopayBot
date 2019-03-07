<?php

namespace app\commands;

use DateTime;
use NPF\Autopay\Bot\Constant;
use NPF\Autopay\Bot\Helpers;
use NPF\Autopay\Bot\Service\DummyPayment;
use NPF\Autopay\Bot\Service\DummyService;
use NPF\Autopay\Bot\Service\Payment;
use NPF\Autopay\Bot\Service\SOAP;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use NPF\Autopay\Bot\Logger;

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
        $env =  $input->getOption('env');
        if ($env !== 'prod') {
            $env = 'dev';
        }

        $this->Init($debug, $env);

        //Получаем список автоплатежей
        $autopayments = $this->getPageAutopay();
        if (!empty($autopayments) && is_array($autopayments)) {
            //В цикле обрабатываем все полученные АП
            foreach ($autopayments as $autopay) {


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

    protected function registerOrder($item)
    {
        $orderId = '';
        //Запрашиваем расширенную информацию о АП
        $autopayExt = $this->soapService->GetAutoPayByGUID($item['AutoPayGUID']);
        if (!empty($autopayExt['AutoPayDetail']) && is_array($autopayExt['AutoPayDetail'])) {
            $clientId = $this->helper->getClientIdReplacement($autopayExt['AutoPayDetail']['ContractNumber']);

            //реализуем проверку наличия связки через getBindings.do
            $rsRest = $this->payService->getBindings(['clientId' => $clientId]);
            $data = json_decode($rsRest['RESULT'], true);
            if ($data['errorCode'] !== 0) {
                //todo: закинуть в логирование отсутствие связки
                //вероятно откючить надо будет
            }

            //регистрируем платеж
            $rsPayment = $this->payService->register([
                'orderNumber' => 0, //todo: сгенерировать
                'amount' => $item['PayAmount'] * 100, // convert
                'returnUrl' => 'fake', // sberbank api workaround
                'clientId' => $clientId,
                'bindingId' => strtolower($autopayExt['AutoPayDetail']['BindingID']),
                'description' => 'autopayment',
                'expirationDate' => $this->helper->getExpirationDate(),
            ]);
        }

    }

    protected function getPageAutopay()
    {
        $arAutopay = $this->soapService->GetAutoPayHistList();

        return $arAutopay;
    }

}
