<?php namespace App\Command;

use App\MessageBroker;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OptimizeQueueTablesCommand extends AbstractCommand
{
    const COMMAND_NAME = 'message-broker:optimize-queue-tables';

    private $messageBroker;

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);
    }

    public function __construct(
        Logger $logger,
        MessageBroker $messageBroker
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->logger = $logger;
        $this->messageBroker = $messageBroker;
    }

    protected function do(InputInterface $input, OutputInterface $output)
    {
        $queueNames = MessageBroker::QUEUE_NAMES;
        foreach ($queueNames as $queueName) {
            $this->messageBroker->optimizeQueueTable($queueName);
        }
    }
}
