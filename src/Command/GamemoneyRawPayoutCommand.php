<?php namespace App\Command;

use App\Entity\PaymentAccount;
use App\Entity\PaymentSystem;
use App\PaymentSystemManager\GamemoneyManager;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GamemoneyRawPayoutCommand extends AbstractCommand
{
    const COMMAND_NAME = 'gamemoney:raw-payout';
    private $gamemoneyManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument('id', InputArgument::REQUIRED)
            ->addArgument('type', InputArgument::REQUIRED)
            ->addArgument('wallet', InputArgument::REQUIRED)
            ->addArgument('amount', InputArgument::REQUIRED)
            ->addArgument('currency', InputArgument::REQUIRED);
    }

    public function __construct(Logger $logger, GamemoneyManager $gamemoneyManager) {
        parent::__construct(self::COMMAND_NAME);
        $this->gamemoneyManager = $gamemoneyManager;
        $this->logger = $logger;
    }

    protected function do(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');
        $type = $input->getArgument('type');
        $wallet = $input->getArgument('wallet');
        $amount = $input->getArgument('amount');
        $currency = $input->getArgument('currency');

        $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
            ->findOneBy(['paymentSystemId' => PaymentSystem::GAMEMONEY_ID]);

        $this->gamemoneyManager->rawPayout($paymentAccount, $id, $type, $wallet, $amount, $currency);
    }
}
