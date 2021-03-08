<?php namespace App;

use App\Entity\Payment;
use App\Entity\User;
use App\Entity\Voucher;
use App\Exception\CannotExecVoucherException;
use App\Repository\PaymentRepository;
use DateTime;
use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Exception;
use Symfony\Bridge\Monolog\Logger;

class VoucherManager
{
    private $repositoryProvider;
    private $logger;
    private $defaultDbClient;
    private $accountant;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        Logger $logger,
        DbClient $defaultDbClient,
        Accountant $accountant
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->logger = $logger;
        $this->defaultDbClient = $defaultDbClient;
        $this->accountant = $accountant;
    }

    /** OUTSIDE MYSQL TRANSACTION!!! */
    public function create(string $method, int $methodId, int $currencyId, string $amount): Voucher
    {
        $key = hash('sha256', $method.$methodId.microtime());
        $voucher = Voucher::create($key, $method, $methodId, $currencyId, $amount);
        $this->repositoryProvider->get(Voucher::class)->create($voucher);

        return $voucher;
    }

    /** @throws CannotExecVoucherException */
    public function execute(Voucher $voucher, User $user): void
    {
        $this->defaultDbClient->beginTransaction();
        try {
            $voucher->statusId = Voucher::STATUS_ID_USED;
            $voucher->userId = $user->id;
            $voucher->usedTs = new DateTime();
            $this->repositoryProvider->get(Voucher::class)->update($voucher, ['statusId', 'userId', 'usedTs']);

            $this->accountant
                ->increase($user->id, Accountant::METHOD_VOUCHER, $voucher->id, $voucher->amount, $voucher->currencyId);

            //TODO service provider
            if ($voucher->method === Voucher::METHOD_NAME_REFUND) {
                /** @var PaymentRepository $paymentRepository */
                $paymentRepository = $this->repositoryProvider->get(Payment::class);
                /** @var Payment $payment */
                $payment = $paymentRepository->findById($voucher->methodId);
                $payment->refundStatusId = Payment::REFUND_STATUS_ID_USED;
                $paymentRepository->update($payment, ['refundStatusId']);
            }

            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();

            throw new CannotExecVoucherException($e->getMessage());
        }
    }
}
