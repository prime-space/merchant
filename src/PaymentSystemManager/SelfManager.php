<?php namespace App\PaymentSystemManager;

use App\Accountant;
use App\Authenticator;
use App\Constraints\PayoutReceiver;
use App\Entity\Account;
use App\Entity\Currency;
use App\Entity\PaymentAccount;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\Entity\Transaction;
use App\Exception\PayoutReceiverNotValidException;
use App\MessageBroker;
use App\TagServiceProvider\TagServiceInterface;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

class SelfManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PayoutInterface
{
    private $repositoryProvider;
    private $authenticator;
    private $messageBroker;

    public function __construct(
        Logger $logger,
        Router $router,
        RepositoryProvider $repositoryProvider,
        Authenticator $authenticator,
        MessageBroker $messageBroker
    ) {
        parent::__construct($logger, $router);
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
        $this->messageBroker = $messageBroker;
    }

    public function getTagServiceName(): string
    {
        return 'self';
    }

    public function getPaymentSystemId(): int
    {
        return 9;
    }

    public function isNeedToWaitingPage(): bool
    {
        return false;
    }

    /** @inheritdoc */
    public function checkReceiver(string $receiver, int $accountId): void
    {
        $receiverAccountId = (int) $receiver;
        /** @var Account|null $account */
        $account = $this->repositoryProvider->get(Account::class)->findById($receiverAccountId);
        if (null === $account) {
            throw new PayoutReceiverNotValidException(PayoutReceiver::MESSAGE_KEY_NOT_FOUND);
        }
        //@TODO
        if ($account->currencyId !== Currency::ID_RUB) {
            throw new PayoutReceiverNotValidException(PayoutReceiver::MESSAGE_KEY_CURRENCY);
        }
        if ($account->id === $accountId) {
            throw new PayoutReceiverNotValidException(PayoutReceiver::MESSAGE_KEY_SELF);
        }
    }

    public function getPayoutQueueName(): string
    {
        return MessageBroker::QUEUE_PAYOUT_SELF_NAME;
    }

    public function getPayoutBalanceKey(): string
    {
        throw new RuntimeException('Self transfer have no balance');
    }

    /** @inheritdoc */
    public function payout(
        Payout $payout,
        PayoutSet $payoutSet,
        string $description,
        PaymentAccount $paymentAccount = null
    ): void {
        $accountId = (int) $payoutSet->receiver;
        /** @var Account $account */
        $account = $this->repositoryProvider->get(Account::class)->findById($accountId);
        $transaction = Transaction::create(
            $account->userId,
            $account->id,
            Accountant::METHOD_PAYOUT_INCOME,
            $payout->id,
            $payout->amount,
            $account->currencyId
        );
        $this->repositoryProvider->get(Transaction::class)->create($transaction);
        $this->messageBroker->createMessage(
            MessageBroker::QUEUE_TRANSACTION_NAME,
            ['id' => $transaction->id],
            15
        );
    }
}
