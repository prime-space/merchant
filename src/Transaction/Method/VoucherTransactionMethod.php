<?php namespace App\Transaction\Method;

use App\Entity\Transaction;
use App\Entity\Voucher;
use App\TagServiceProvider\TagServiceInterface;
use DateTime;
use RuntimeException;

class VoucherTransactionMethod extends AbstractTransactionMethod implements
    TagServiceInterface,
    TransactionMethodInterface
{
    const METHOD = 'voucher';

    public function getTagServiceName(): string
    {
        return self::METHOD;
    }

    public function compileDescription(Transaction $transaction): string
    {
        $voucher = $this->getVoucher($transaction);

        if ($voucher->method === Voucher::METHOD_NAME_REFUND) {
            $parameters = ['%paymentId%' => $voucher->methodId];
            $description = $this->translator->trans('description.'.self::METHOD.'.payment', $parameters, 'transaction');
        } else {
            throw new RuntimeException('Unknown voucher method');
        }

        return $description;
    }

    public function getState(Transaction $transaction): string
    {
        $voucher = $this->getVoucher($transaction);

        if (in_array($voucher->statusId, [Voucher::STATUS_ID_NEW, Voucher::STATUS_ID_PROCESS], true)) {
            $state = TransactionMethodInterface::STATE_PROCESS;
        } elseif (!$transaction->isExecuted()) {
            $state = TransactionMethodInterface::STATE_PROCESS;
        } else {
            $state = TransactionMethodInterface::STATE_EXECUTED;
        }

        return $state;
    }

    public function getDate(Transaction $transaction): DateTime
    {
        $voucher = $this->getVoucher($transaction);

        return $voucher->usedTs ?? $voucher->createdTs;
    }

    public function getId(Transaction $transaction): int
    {
        $voucher = $this->getVoucher($transaction);

        return $voucher->id;
    }

    private function getVoucher(Transaction $transaction): Voucher
    {
        if (self::METHOD !== $transaction->method) {
            throw new RuntimeException("Expect '".self::METHOD."' transaction method");
        }

        /** @var Voucher $voucher */
        $voucher = $this->repositoryProvider->get(Voucher::class)->findById($transaction->methodId);

        return $voucher;
    }
}
