<?php namespace App\Payout\Processor;

use App\Accountant;
use App\Entity\Account;
use App\Entity\PaymentAccount;
use App\Entity\PaymentSystem;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\Exception\CannotCheckException;
use App\Exception\NotEnoughFundsException;
use App\PaymentSystemManager\PayoutWithChecking;
use App\Payout\Processor\Exception\ProcessCheckingOrUnknownStatusException;
use App\Repository\PayoutSetRepository;
use App\TagServiceProvider\TagServiceProvider;
use App\TelegramSender;
use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Exception;
use Symfony\Bridge\Monolog\Logger;

class PayoutProcessor
{
    private $repositoryProvider;
    private $tagServiceProvider;
    private $paymentSystemManagers;
    private $defaultDbClient;
    private $logger;
    private $accountant;
    private $telegramSender;

    public function __construct(
        Logger $logger,
        RepositoryProvider $repositoryProvider,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers,
        DbClient $defaultDbClient,
        Accountant $accountant,
        TelegramSender $telegramSender
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->defaultDbClient = $defaultDbClient;
        $this->logger = $logger;
        $this->accountant = $accountant;
        $this->telegramSender = $telegramSender;
    }

    /**
     * @throws ProcessCheckingOrUnknownStatusException
     */
    public function processCheckingOrUnknownStatus(Payout $payout): int
    {
        if (!in_array($payout->statusId, [Payout::STATUS_ID_UNKNOWN, Payout::STATUS_ID_CHECKING], true)) {
            throw new ProcessCheckingOrUnknownStatusException(
                ProcessCheckingOrUnknownStatusException::CODE_PAYOUT_STATUS_MISMATCH,
                "Expect UNKNOWN or CHECKING status, got {$payout->statusId}"
            );
        }
        if (null === $payout->paymentAccountId) {
            throw new ProcessCheckingOrUnknownStatusException(
                ProcessCheckingOrUnknownStatusException::CODE_PAYMENT_ACCOUNT_ID_IS_NULL
            );
        }
        /** @var PaymentAccount $paymentAccount */
        $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
            ->findById($payout->paymentAccountId);
        /** @var PaymentSystem $paymentSystem */
        $paymentSystem = $this->repositoryProvider->get(PaymentSystem::class)
            ->findById($paymentAccount->paymentSystemId);
        $paymentSystemManager = $this->tagServiceProvider
            ->get($this->paymentSystemManagers, $paymentSystem->name);
        if (null === $paymentSystemManager) {
            throw new ProcessCheckingOrUnknownStatusException(
                ProcessCheckingOrUnknownStatusException::CODE_PAYMENT_SYSTEM_MANAGER_NOT_FOUND
            );
        }
        if (!$paymentSystemManager instanceof PayoutWithChecking) {
            throw new ProcessCheckingOrUnknownStatusException(
                ProcessCheckingOrUnknownStatusException::CODE_PAYMENT_SYSTEM_MANAGER_WITHOUT_CHECKING
            );
        }
        try {
            $payoutCheckingResult = $paymentSystemManager->checkPayout($payout->initData, $paymentAccount);
        } catch (CannotCheckException $e) {
            throw new ProcessCheckingOrUnknownStatusException(
                ProcessCheckingOrUnknownStatusException::CODE_CANNOT_CHECK
            );
        } catch (NotEnoughFundsException $e) {
            throw new ProcessCheckingOrUnknownStatusException(
                ProcessCheckingOrUnknownStatusException::CODE_NOT_ENOUGH_FUNDS
            );
        }
        /** @var PayoutSet $payoutSet */
        $payoutSet = $this->repositoryProvider->get(PayoutSet::class)
            ->findById($payout->payoutSetId);
        if ($payoutCheckingResult === 1) {
            $this->moveToSuccessStatus($payout, $payoutSet);
        } elseif ($payoutCheckingResult === -1) {
            $this->moveToFailStatusWithRefund($payout, $payoutSet);
        }

        return $payoutCheckingResult;
    }

    public function moveToSuccessStatus(Payout $payout, PayoutSet $payoutSet)
    {
        $this->defaultDbClient->beginTransaction();
        try {
            $this->updateStatus($payout, Payout::STATUS_ID_SUCCESS, 'info', 'Success');
            $this->increaseProcessed($payoutSet, true, $payout->amount);
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    public function updateStatus(
        Payout $payout,
        int $status,
        string $logLevel = null,
        string $message = null,
        array $context = []
    ): void {
        $payout->statusId = $status;
        $this->repositoryProvider->get(Payout::class)->update($payout);
        if (null !== $logLevel) {
            $this->logger->$logLevel($message, $context);
        }
    }

    public function increaseProcessed(
        PayoutSet $payoutSet,
        bool $success = false,
        string $transferredAmount = '0'
    ): void {
        /** @var PayoutSetRepository $payoutSetRepository */
        $payoutSetRepository = $this->repositoryProvider->get(PayoutSet::class);
        $payoutSetRepository->increaseProcessed($payoutSet, $success, $transferredAmount);
        //@TODO refresh functionality inside db bundle
        $payoutSetRepository = $this->repositoryProvider->get(PayoutSet::class);
        $payoutSetRepository->clear();
        /** @var PayoutSet $payoutSet */
        $payoutSet = $payoutSetRepository->findById($payoutSet->id);
        if ($payoutSet->chunkNum === $payoutSet->chunkProcessedNum) {
            if ($payoutSet->chunkSuccessNum === $payoutSet->chunkNum) {
                $status = PayoutSet::STATUS_ID_SUCCESS;
            } elseif ($payoutSet->chunkSuccessNum === 0) {
                $status = PayoutSet::STATUS_ID_ERROR;
            } else {
                $status = PayoutSet::STATUS_ID_PART;
            }
            $payoutSet->statusId = $status;
            $payoutSetRepository->update($payoutSet, ['statusId']);
        }
    }

    public function moveToFailStatusWithRefund(Payout $payout, PayoutSet $payoutSet, array $context = [])
    {
        $this->defaultDbClient->beginTransaction();
        try {
            $this->updateStatus($payout, Payout::STATUS_ID_FAIL, 'error', 'Cannot payout, refund', $context);
            /** @var Account $account */
            $account = $this->repositoryProvider->get(Account::class)->findById($payoutSet->accountId);
            $this->accountant->increase(
                $payoutSet->userId,
                Accountant::METHOD_PAYOUT_RETURN,
                $payout->id,
                $payout->credit,
                $account->currencyId
            );
            $this->increaseProcessed($payoutSet);
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    /** @throws Exception */
    public function moveToUnknownStatus(
        Payout $payout,
        PayoutSet $payoutSet,
        PaymentAccount $paymentAccount,
        string $reason
    ) {
        $this->defaultDbClient->beginTransaction();
        try {
            $this->updateStatus($payout, Payout::STATUS_ID_UNKNOWN, 'error', "Payout got unknown status: '$reason'");
            /** @var PaymentSystem $paymentSystem */
            $paymentSystem = $this->repositoryProvider->get(PaymentSystem::class)
                ->findById($payoutSet->paymentSystemId);
            $message = sprintf(
                "Payout #%s got unknown status: '%s'.\n %s account #%s\n amount: %s\n receiver: %s",
                $payout->id,
                $reason,
                $paymentSystem->name,
                $paymentAccount->id,
                $payout->amount,
                $payoutSet->receiver
            );
            $this->telegramSender->send($message);
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }
}
