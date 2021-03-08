<?php namespace App\Daemon;

use App\Accountant;
use App\Entity\Transaction;
use App\Exception\NotFoundException;
use App\MessageBroker;
use Ewll\DBBundle\DB\Client as DbClient;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExecTransactionDaemon extends Daemon
{
    private $messageBroker;
    private $defaultDbClient;
    private $accountant;

    protected function configure()
    {
        $this->setName('daemon:exec-transaction');
    }

    public function __construct(
        Logger $logger,
        MessageBroker $messageBroker,
        DbClient $defaultDbClient,
        Accountant $accountant
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->messageBroker = $messageBroker;
        $this->defaultDbClient = $defaultDbClient;
        $this->accountant = $accountant;
    }

    /** @inheritdoc */
    protected function do(InputInterface $input, OutputInterface $output)
    {
        $message = $this->messageBroker->getMessage(MessageBroker::QUEUE_TRANSACTION_NAME);
        $this->logExtraDataKeeper->setParam('id', $message['id']);
        $this->logger->info('Execute transaction');

        /** @var Transaction $transaction */
        $transaction = $this->repositoryProvider->get(Transaction::class)->findById($message['id']);
        if (null === $transaction) {
            throw new NotFoundException('Transaction not found');
        }
        if ($transaction->isExecuted()) {
            $this->logger->error('Already executed');

            return;
        }

        $this->accountant->executeTransaction($transaction);

        $this->logger->info('Success');
    }
}
