<?php namespace App\Consumer;

use App\Logger\LogExtraDataKeeper;
use App\PostbackManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class PostbackConsumer implements ConsumerInterface
{
    private $postbackManager;
    private $logExtraDataKeeper;

    public function __construct(LogExtraDataKeeper $logExtraDataKeeper, PostbackManager $postbackManager)
    {
        $this->postbackManager = $postbackManager;
        $this->logExtraDataKeeper = $logExtraDataKeeper;
    }

    /** @inheritdoc */
    public function execute(AMQPMessage $msg)
    {
        $message = json_decode($msg->body, true);

        $this->logExtraDataKeeper->setData([
            'iterationKey' => uniqid(),
            'name' => 'PostbackConsumer',
            'paymentId' => $message['paymentId'],
        ]);

        $this->postbackManager->send($message['paymentId'], $message['event']);
    }
}

