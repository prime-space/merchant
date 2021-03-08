<?php namespace App;

use Ewll\DBBundle\DB\Client;
use Ewll\DBBundle\Exception\ExecuteException;
use Symfony\Bridge\Monolog\Logger;

class MessageBroker
{
    const QUEUE_EXEC_PAYMENT_NAME = 'exec_payment';
    const QUEUE_NOTIFICATION_NAME = 'notification';
    const QUEUE_TRANSACTION_NAME = 'transaction';
    const QUEUE_TELEGRAM_NOTIFICATIONS_NAME = 'telegram';
    const QUEUE_PAYOUT_SELF_NAME = 'payout_self';
    const QUEUE_PAYOUT_QIWI_NAME = 'payout_qiwi';
    const QUEUE_PAYOUT_YANDEX_NAME = 'payout_yandex';
    const QUEUE_PAYOUT_WMR_NAME = 'payout_wmr';
    const QUEUE_PAYOUT_MPAY_NAME = 'payout_mpay';
    const QUEUE_PAYOUT_MPAY_CARD_NAME = 'payout_mpay_card';
    const QUEUE_PAYOUT_CHECK = 'payout_check';
    const QUEUE_YANDEX_PAYMENT_NAME = 'yandex_payment';
    const QUEUE_WHITE_BALANCING_NAME = 'white_balancing';
    const QUEUE_MAIL_NAME = 'mail';
    const QUEUE_EXEC_VOUCHER = 'exec_voucher';

    const QUEUE_NAMES = [
        self::QUEUE_EXEC_PAYMENT_NAME,
        self::QUEUE_NOTIFICATION_NAME,
        self::QUEUE_TRANSACTION_NAME,
        self::QUEUE_TELEGRAM_NOTIFICATIONS_NAME,
        self::QUEUE_PAYOUT_SELF_NAME,
        self::QUEUE_PAYOUT_QIWI_NAME,
        self::QUEUE_PAYOUT_YANDEX_NAME,
        self::QUEUE_PAYOUT_WMR_NAME,
        self::QUEUE_PAYOUT_MPAY_NAME,
        self::QUEUE_PAYOUT_MPAY_CARD_NAME,
        self::QUEUE_PAYOUT_CHECK,
        self::QUEUE_YANDEX_PAYMENT_NAME,
        self::QUEUE_WHITE_BALANCING_NAME,
        self::QUEUE_MAIL_NAME,
        self::QUEUE_EXEC_VOUCHER,
    ];

    private $queueDbClient;
    private $logger;

    public function __construct(Client $queueDbClient, Logger $logger)
    {
        $this->queueDbClient = $queueDbClient;
        $this->logger = $logger;
    }

    public function getMessage(string $queue): array
    {
        while (1) {
            $statement = $this->queueDbClient
                ->prepare("CALL sp_get_message('$queue')")
                ->execute();

            $data = $statement->fetchColumn();
            if (null === $data) {
                continue;
            }
            $data = json_decode($data, true);

            break;
        }

        return $data;
    }

    public function createMessage(string $queue, array $data, int $delay = 0): void
    {
        try {
            $this->queueDbClient
                ->prepare("CALL sp_create_message('$queue', :message, :delay)")
                ->execute([
                    'message' => json_encode($data),
                    'delay' => $delay,
                ]);
        } catch (\Exception $e) {//@TODO DEBUG
            $this->logger->error('MessageBroker::createMessage Exception', [
                'class' => get_class($e),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
            if ($e instanceof ExecuteException && 1 === preg_match('/^SQLSTATE\[HY000]/', $e->getMessage())) {
                $this->logger->error('MessageBroker::createMessage Mysql has gone away. Sleep 5 sec and reconnect');
                sleep(5);
                $this->queueDbClient->reconnect();
                $this->createMessage($queue, $data, $delay);
            } else {
                throw $e;
            }
        }
    }

    public function optimizeQueueTable(string $queueName): void
    {
        $this->queueDbClient
            ->prepare("CALL sp_optimize_queue_table(:queueName)")
            ->execute([
                'queueName' => $queueName,
            ]);
    }

    public function getQueueInfo(string $queueName): array
    {
        return $this->queueDbClient
            ->prepare("CALL sp_queue_info(:queueName)")
            ->execute(['queueName' => $queueName])
            ->fetchArray();
    }
}
