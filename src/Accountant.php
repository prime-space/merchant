<?php namespace App;

use App\Constraints\Account as AccountConstraint;
use App\Constraints\Accuracy;
use App\Constraints\Password;
use App\Constraints\PayoutInternalUsersId;
use App\Constraints\PayoutMethod as PayoutMethodConstraint;
use App\Constraints\PayoutReceiver;
use App\Constraints\UserBlock;
use App\Entity\Account;
use App\Entity\PaymentSystem;
use App\Entity\Payout;
use App\Entity\PayoutMethod;
use App\Entity\PayoutSet;
use App\Entity\SystemAddBalance;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\InsufficientFundsException;
use App\Form\Extension\Core\DataTransformer\PayoutMethodCodeToIdTransformer;
use App\Form\Extension\Core\Type\VuetifyCheckboxType;
use App\PaymentSystemManager\PaymentSystemManagerInterface;
use App\PaymentSystemManager\PayoutInterface;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use App\TagServiceProvider\TagServiceProvider;
use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Exception\ExecuteException;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Exception;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class Accountant
{
    const METHOD_PAYMENT = 'payment';
    const METHOD_PAYMENT_REFUND = 'paymentRefund';
    const METHOD_VOUCHER = 'voucher';
    const METHOD_PAYOUT = 'payout';
    const METHOD_PAYOUT_SET = 'payoutSet';
    const METHOD_PAYOUT_INCOME = 'payoutIncome';
    const METHOD_PAYOUT_RETURN = 'payoutReturn';
    const METHOD_SYSTEM = 'system';

    const PAYOUT_FROM_FIELD_NAME_ID = 'id';
    const PAYOUT_FROM_FIELD_NAME_RECEIVER = 'receiver';
    const PAYOUT_FROM_FIELD_NAME_METHOD = 'method';
    const PAYOUT_FROM_FIELD_NAME_ACCOUNT = 'accountId';
    const PAYOUT_FROM_FIELD_NAME_AMOUNT = 'amount';
    const PAYOUT_FROM_FIELD_NAME_PASSWORD = 'password';
    const PAYOUT_FROM_FIELD_NAME_REMEMBER_PASSWORD = 'rememberPassword';

    private $repositoryProvider;
    private $messageBroker;
    private $defaultDbClient;
    private $translator;
    private $feeFetcher;
    private $formFactory;
    private $authenticator;
    private $tagServiceProvider;
    private $paymentSystemManagers;
    private $payoutMethodCodeToIdTransformer;
    private $logger;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        MessageBroker $messageBroker,
        DbClient $defaultDbClient,
        TranslatorInterface $translator,
        FeeFetcher $feeFetcher,
        FormFactoryInterface $formFactory,
        Authenticator $authenticator,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers,
        PayoutMethodCodeToIdTransformer $payoutMethodCodeToIdTransformer,
        Logger $logger
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->messageBroker = $messageBroker;
        $this->defaultDbClient = $defaultDbClient;
        $this->translator = $translator;
        $this->feeFetcher = $feeFetcher;
        $this->formFactory = $formFactory;
        $this->authenticator = $authenticator;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->payoutMethodCodeToIdTransformer = $payoutMethodCodeToIdTransformer;
        $this->logger = $logger;
    }

    /** @throws Exception */
    public function systemAdd(Account $account, string $amount, string $comment): void
    {
        try {
            $this->defaultDbClient->beginTransaction();

            $systemAddBalance = SystemAddBalance::create($account->id, $amount, $comment);
            $this->repositoryProvider->get(SystemAddBalance::class)->create($systemAddBalance);

            $transaction = Transaction::create(
                $account->userId,
                $account->id,
                self::METHOD_SYSTEM,
                $systemAddBalance->id,
                $amount,
                $account->currencyId
            );
            $this->repositoryProvider->get(Transaction::class)->create($transaction);
            $this->messageBroker->createMessage(MessageBroker::QUEUE_TRANSACTION_NAME, ['id' => $transaction->id], 5);

            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw $e;
        }
    }

    /** @TODO OUTSIDE TRANSACTION */
    public function increase(int $userId, string $method, int $methodId, string $amount, int $currencyId)
    {
        $account = $this->getPaymentAccountByUserIdAndCurrencyId($userId, $currencyId);
        $transaction = Transaction::create($userId, $account->id, $method, $methodId, $amount, $currencyId, null);

        $this->repositoryProvider->get(Transaction::class)->create($transaction);

        $this->messageBroker->createMessage(MessageBroker::QUEUE_TRANSACTION_NAME, ['id' => $transaction->id], 5);
    }

    /**
     * @throws InsufficientFundsException
     */
    public function payout(
        string $payoutMethodId,
        User $user,
        string $amount,
        int $accountId,
        string $receiver,
        int $internalUsersId = null
    ): PayoutSet {
        /** @var PayoutMethod $payoutMethod */
        $payoutMethod = $this->repositoryProvider->get(PayoutMethod::class)->findById($payoutMethodId);
        /** @var PaymentSystem $paymentSystem */
        $paymentSystem = $this->repositoryProvider->get(PaymentSystem::class)
            ->findById($payoutMethod->paymentSystemId);
        /** @var PaymentSystemManagerInterface $paymentSystemManager */
        $paymentSystemManager = $this->tagServiceProvider
            ->get($this->paymentSystemManagers, $paymentSystem->name);
        if (null === $paymentSystemManager || !$paymentSystemManager instanceof PayoutInterface) {
            throw new RuntimeException('Method not implemented');
        }

        /** @var AccountRepository $accountRepository */
        $accountRepository = $this->repositoryProvider->get(Account::class);
        /** @var TransactionRepository $transactionRepository */
        $transactionRepository = $this->repositoryProvider->get(Transaction::class);
        $payoutSetRepository = $this->repositoryProvider->get(PayoutSet::class);

        for ($i=0; $i<5; $i++) {
            try {
                $this->defaultDbClient->beginTransaction();

                $account = $accountRepository->getAccountWithLock($accountId);
                if (null === $account) {
                    throw new RuntimeException('Account not found');
                }

                $fee = $this->feeFetcher->fetchPayoutFee($payoutMethod, $user);
                $feeAmount = $this->feeFetcher->calcFeeAmount($amount, $fee);
                $credit = bcadd($amount, $feeAmount, 8);
                $chunkNum = (int)ceil(bcdiv($amount, $payoutMethod->chunkSize, 10));
                $payoutSet = PayoutSet::create(
                    $payoutMethod->id,
                    $user->id,
                    $internalUsersId,
                    $account->id,
                    $paymentSystemManager->getPaymentSystemId(),
                    $receiver,
                    $amount,
                    $feeAmount,
                    $credit,
                    $chunkNum
                );

                $payoutSetRepository->create($payoutSet);

                $unexecutedDecreaseTransactionSum = $transactionRepository
                    ->calcUnexecutedDecreaseTransactionSum($user->id, $account->currencyId);
                $balance = $transactionRepository->getBalance($user->id, $account->currencyId);

                $realBalance = bcadd($balance, $unexecutedDecreaseTransactionSum, 8);
                $isEnough = bccomp($realBalance, $credit, 8) > -1;
                if (!$isEnough) {
                    $this->logger->error('InsufficientFundsException', [
                        'payoutSet' => $payoutSet->id,
                        'balance' => $balance,
                        'realBalance' => $realBalance,
                        'amount' => $amount,
                        'fee' => $fee,
                        'feeAmount' => $feeAmount,
                        'credit' => $credit,
                    ]);

                    throw new InsufficientFundsException();
                }

                $transactionAmount = bcmul($credit, '-1', 8);
                $transaction = Transaction::create(
                    $user->id,
                    $account->id,
                    self::METHOD_PAYOUT_SET,
                    $payoutSet->id,
                    $transactionAmount,
                    $account->currencyId
                );
                $transactionRepository->create($transaction);
                $this->messageBroker->createMessage(
                    MessageBroker::QUEUE_TRANSACTION_NAME,
                    ['id' => $transaction->id],
                    15
                );
                $payoutQueueName = $paymentSystemManager->getPayoutQueueName();
                for ($i = 1; $i <= $chunkNum; $i++) {
                    if ($chunkNum === 1) {
                        $chunkAmount = $amount;
                    } elseif ($i === $chunkNum) {
                        $chunkAmount = bcmod($amount, $payoutMethod->chunkSize, 8);
                        if (bccomp($chunkAmount, 0, 8) === 0) {
                            $chunkAmount = $payoutMethod->chunkSize;
                        }
                    } else {
                        $chunkAmount = $payoutMethod->chunkSize;
                    }
                    $this->createPayout($payoutSet, $chunkAmount, $fee, $payoutQueueName);
                }

                $accountRepository->incrementLockCounter($account->id);
                $this->defaultDbClient->commit();

                return $payoutSet;
            } catch (Exception|InsufficientFundsException $e) {
                $this->defaultDbClient->rollback();
                if ($e instanceof ExecuteException && $e->getCode() === ExecuteException::DEADLOCK_CODE) {
                    $this->logger->crit('DEADLOCK_DEBUG', ['payout' => $payoutSet->id ?? null,]);

                    continue;
                }
                throw $e;
            }
        }
        throw new RuntimeException('Cannot realise locking');
    }

    /** @throws Exception */
    public function executeTransaction(Transaction $transaction)
    {
        bcscale(2);

        $transactionRepository = $this->repositoryProvider->get(Transaction::class);
        $accountRepository = $this->repositoryProvider->get(Account::class);

        /** @var Account $account */
        $account = $accountRepository->findOneBy([
            'userId' => $transaction->userId,
            'currencyId' => $transaction->currencyId,
        ]);

        if (null === $account) {
            $account = Account::create($transaction->userId, $transaction->currencyId);
        }

        if ($account->lastTransactionId === Account::NO_LAST_TRANSACTION_ID) {
            $accountOperationId = 1;
            $oldBalance = '0';
        } else {
            /** @var Transaction $lastAccountTransaction */
            $lastAccountTransaction = $transactionRepository->findById($account->lastTransactionId);
            $oldBalance = $lastAccountTransaction->balance;
            $accountOperationId = $lastAccountTransaction->accountOperationId + 1;
        }

        $this->defaultDbClient->beginTransaction();
        try {
            $transaction->accountOperationId = $accountOperationId;
            $transaction->balance = bcadd($oldBalance, $transaction->amount);
            $account->lastTransactionId = $transaction->id;
            $account->balance = $transaction->balance;

            $transactionRepository->update($transaction);
            if (null === $account->id) {
                $accountRepository->create($account);
            } else {
                $accountRepository->update($account);
            }

            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();
            throw $e;
        }
    }

    public function compileAccountsView(User $user): array
    {
        $accountsView = [];
        /** @var Account[] $accounts */
        $accounts = $this->repositoryProvider->get(Account::class)->findBy(['userId' => $user->id]);
        foreach ($accounts as $account) {
            $currencySign = $this->translator->trans("currency.$account->currencyId.sign", [], 'payment');
            $accountsView[] = [
                'id' => $account->id,
                'balance' => $account->balance,
                'currencyId' => $account->currencyId,
                'currencySign' => $currencySign,
            ];
        }

        return $accountsView;
    }

    public function getLKPayoutForm(): FormInterface
    {
        $formBuilder = $this->getPayoutFormBuilder('form');

        $passConstraints = $this->authenticator->doNotAskPass() ? [] : [
            new NotBlank(['message' => 'fill-field']),
            new Password(),
        ];

        $formBuilder
            ->add(self::PAYOUT_FROM_FIELD_NAME_PASSWORD, TextType::class, ['constraints' => $passConstraints])
            ->add(self::PAYOUT_FROM_FIELD_NAME_REMEMBER_PASSWORD, VuetifyCheckboxType::class);

        $form = $formBuilder->getForm();

        return $form;
    }

    public function getApiPayoutForm(): FormInterface
    {
        $formBuilder = $this->getPayoutFormBuilder()
            ->add(self::PAYOUT_FROM_FIELD_NAME_ID, IntegerType::class, ['constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual(0),
                    new PayoutInternalUsersId(),
                ]
            ]);

        $form = $formBuilder->getForm();

        return $form;
    }

    public function getPaymentAccountByUserIdAndCurrencyId(int $userId, int $currencyId): Account
    {
        $accountRepository = $this->repositoryProvider->get(Account::class);

        /** @var Account $account */
        $account = $accountRepository->findOneBy([
            'userId' => $userId,
            'currencyId' => $currencyId,
        ]);

        if (null === $account) {
            $account = Account::create($userId, $currencyId);
            $accountRepository->create($account);
        }

        return $account;
    }

    private function getPayoutFormBuilder(string $name = null): FormBuilderInterface
    {
        //@TODO hardcode for debug
        $userId = $this->authenticator->getUser()->id;
        $maxAmount = $userId === 4 ? 100000 : 10000;

        $formBuilder = $this->formFactory
            ->createNamedBuilder($name, FormType::class, null, ['constraints' => [
                new UserBlock,
            ]])
            ->add(self::PAYOUT_FROM_FIELD_NAME_RECEIVER, TextType::class, ['constraints' => [
                new NotBlank(['message' => 'fill-field']),
                new PayoutReceiver(),
            ]])
            //@TODO 3 times for select PaymentMethod by code...
            ->add(self::PAYOUT_FROM_FIELD_NAME_METHOD, TextType::class, ['constraints' => [
                new NotBlank(['message' => 'fill-field']),
                new PayoutMethodConstraint(),
            ]])
            ->add(self::PAYOUT_FROM_FIELD_NAME_ACCOUNT, IntegerType::class, ['constraints' => [
                new NotBlank(['message' => 'fill-field']),
                new AccountConstraint(),
            ]])
            ->add(self::PAYOUT_FROM_FIELD_NAME_AMOUNT, NumberType::class, ['label' => false, 'constraints' => [
                new NotBlank(['message' => 'fill-field']),
                new GreaterThanOrEqual(10),
                new LessThanOrEqual($maxAmount),
                new Accuracy(2),
            ]]);

        $formBuilder->get(self::PAYOUT_FROM_FIELD_NAME_METHOD)
            ->addModelTransformer($this->payoutMethodCodeToIdTransformer);

        return $formBuilder;
    }

    private function createPayout(PayoutSet $payoutSet, string $amount, string $fee, string $queueName): void
    {
        $feeAmount = $this->feeFetcher->calcFeeAmount($amount, $fee);
        $credit = bcadd($amount, $feeAmount, 8);
        $payout = Payout::create(
            $payoutSet->id,
            $amount,
            $credit
        );
        $this->repositoryProvider->get(Payout::class)->create($payout);
        $this->messageBroker->createMessage(
            $queueName,
            ['id' => $payout->id, 'try' => 1,],
            15
        );
    }
}
