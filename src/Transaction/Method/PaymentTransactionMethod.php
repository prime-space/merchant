<?php namespace App\Transaction\Method;

use App\Entity\Payment;
use App\Entity\Shop;
use App\Entity\Transaction;
use App\TagServiceProvider\TagServiceInterface;
use DateTime;
use RuntimeException;

class PaymentTransactionMethod extends AbstractTransactionMethod implements
    TagServiceInterface,
    TransactionMethodInterface
{
    const METHOD = 'payment';

    public function getTagServiceName(): string
    {
        return self::METHOD;
    }

    public function compileDescription(Transaction $transaction): string
    {
        $payment = $this->getPayment($transaction);

        /** @var Shop $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);
        $parameters = ['%shopName%' => $shop->name];
        $description = $this->translator->trans('description.'.self::METHOD, $parameters, 'transaction');

        return $description;
    }

    public function getState(Transaction $transaction): string
    {
        if (!$transaction->isExecuted()) {
            $state = TransactionMethodInterface::STATE_PROCESS;
        } else {
            $state = TransactionMethodInterface::STATE_EXECUTED;
        }

        return $state;
    }

    public function getDate(Transaction $transaction): DateTime
    {
        $payment = $this->getPayment($transaction);

        return $payment->createdTs;
    }

    public function getId(Transaction $transaction): int
    {
        $payment = $this->getPayment($transaction);

        return $payment->id;
    }

    private function getPayment(Transaction $transaction): Payment
    {
        if (self::METHOD !== $transaction->method) {
            throw new RuntimeException("Expect '".self::METHOD."' transaction method");
        }

        /** @var Payment $payment */
        $payment = $this->repositoryProvider->get(Payment::class)->findById($transaction->methodId);

        return $payment;
    }
}
