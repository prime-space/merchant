<?php namespace App\Daemon;

use App\MessageBroker;
use App\TelegramSender;
use GuzzleHttp\Exception\RequestException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TelegramNotificationDaemon extends Daemon
{
    private $messageBroker;
    private $chatId;
    private $botToken;
    private $telegramSender;

    public function __construct(
        Logger $logger,
        MessageBroker $messageBroker,
        string $chatId,
        string $botToken,
        TelegramSender $telegramSender
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->messageBroker = $messageBroker;
        $this->chatId = $chatId;
        $this->botToken = $botToken;
        $this->telegramSender = $telegramSender;
    }

    /** @throws RequestException */
    protected function do(InputInterface $input, OutputInterface $output)
    {
        $message = $this->messageBroker->getMessage(MessageBroker::QUEUE_TELEGRAM_NOTIFICATIONS_NAME);
        $this->logger->info("Send notification. Text - {$message['text']}");
        $this->telegramSender->doRequest($message['text']);
        $this->logger->info('Success');
    }
}
