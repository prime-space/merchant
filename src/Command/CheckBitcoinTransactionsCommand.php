<?php namespace App\Command;

use App\PaymentSystemManager\BitcoinManager;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckBitcoinTransactionsCommand extends AbstractCommand
{
    const COMMAND_NAME = 'bitcoin:check-transactions';

    private $bitcoinManager;

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);
    }

    public function __construct(
        BitcoinManager $bitcoinManager,
        Logger $logger
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->logger = $logger;
        $this->bitcoinManager = $bitcoinManager;
    }

    protected function do(InputInterface $input, OutputInterface $output)
    {
        $this->bitcoinManager->checkTransactions($this->logger);
    }
}
