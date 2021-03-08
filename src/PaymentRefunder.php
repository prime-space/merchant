<?php namespace App;

use App\Entity\Currency;
use App\Entity\Payment;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Voucher;
use App\Exception\CannotInitPaymentRefundException;
use App\Exception\CannotPaymentRefundException;
use App\Repository\PaymentRepository;
use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Exception\NoAffectedRowsException;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Exception;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Form\FormFactory;

class PaymentRefunder
{
    private $repositoryProvider;
    private $logger;
    private $defaultDbClient;
    private $accountant;
    private $mailer;
    private $formFactory;
    private $domain;
    private $voucherManager;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        Logger $logger,
        DbClient $defaultDbClient,
        Accountant $accountant,
        Mailer $mailer,
        FormFactory $formFactory,
        VoucherManager $voucherManager,
        string $domain
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->logger = $logger;
        $this->defaultDbClient = $defaultDbClient;
        $this->accountant = $accountant;
        $this->mailer = $mailer;
        $this->formFactory = $formFactory;
        $this->domain = $domain;
        $this->voucherManager = $voucherManager;
    }

    /** @throws CannotInitPaymentRefundException */
    public function initRefund(Payment $payment)
    {
        if ($payment->refundStatusId !== Payment::REFUND_STATUS_ID_WAS_NOT) {
            $error = "Payment refund status is not 1";
            $this->logger->error("Cannot init refund. $error", ['paymentId' => $payment->id]);

            throw new CannotInitPaymentRefundException($error);
        }
        if (empty($payment->email)) {
            $error = "Email is require for payment refunding";
            $this->logger->crit("Cannot init refund. $error", ['paymentId' => $payment->id]);

            throw new CannotInitPaymentRefundException($error);
        }
        if ($payment->statusId !== Payment::STATUS_ID_SUCCESS) {
            $error = "Refund possible only for success payments";
            $this->logger->crit("Cannot init refund. $error", ['paymentId' => $payment->id]);

            throw new CannotInitPaymentRefundException($error);
        }
        /** @var Shop $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

        $this->defaultDbClient->beginTransaction();
        try {
            /** @var PaymentRepository $paymentRepository */
            $paymentRepository = $this->repositoryProvider->get(Payment::class);
            $paymentRepository->initRefundAtomic($payment->id);
            $refundAmount = $this->calcRefundAmount($payment);
            $this->accountant->increase(
                $shop->userId,
                Accountant::METHOD_PAYMENT_REFUND,
                $payment->id,
                -$refundAmount,
                $payment->currency
            );
            $voucher = $this->voucherManager
                ->create(Voucher::METHOD_NAME_REFUND, $payment->id, $payment->currency, $refundAmount);
            $this->mailer->create(
                $payment->email,
                Mailer::LETTER_PAYMENT_REFUND,
                ['paymentId' => $payment->id, 'voucherKey' => $voucher->key, 'domain' => $this->domain]
            );
            $this->defaultDbClient->commit();
            $this->logger->info("Success init refund", ['paymentId' => $payment->id]);
        } catch (NoAffectedRowsException|Exception $e) {
            $error = $e instanceof NoAffectedRowsException ? 'Cannot change refund status' : $e->getMessage();
            $this->logger->crit("Cannot init refund. $error", ['paymentId' => $payment->id]);
            $this->defaultDbClient->rollback();

            throw new CannotInitPaymentRefundException($error);
        }
    }

    private function calcRefundAmount(Payment $payment)
    {
        bcscale(2);
        $amount = $payment->isFeeByClient
            ? bcadd($payment->amount, $payment->fee)
            : bcsub($payment->amount, $payment->fee);

        return $amount;
    }
}
