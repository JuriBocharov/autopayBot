<?php

namespace NPF\Commands;

use NPF\Constant;
use NPF\Service\Payment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Консольная командя для запуска автоплатежного бота.
 */
class testBot extends Command
{
    private $payService;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('app:run_test')
            ->setDescription('Run test')
            ->addOption(
                'debug',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'You must specify the debug status'
            );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $debug = reset($input->getOption('debug'));
        $env = getenv('APP_ENV');

        $this->payService = new Payment(Constant::SB_API_TEST, Constant::MERCHANT_LOGIN_TEST, Constant::MERCHANT_PASSWORD_TEST);

        $clientId = '999-9003-000131668';
        $rsRest = $this->payService->getBindings(['clientId' => $clientId]);

        $output->writeln([
            'User Creator',
            '============',
            '',
            $debug,
            $env,
            var_dump($rsRest),
        ]);
    }
}
