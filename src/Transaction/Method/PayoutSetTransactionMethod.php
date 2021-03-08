<?php namespace App\Transaction\Method;

use App\Entity\Payout;
use App\Entity\PayoutMethod;
use App\Entity\PayoutSet;
use App\Entity\Transaction;
use App\TagServiceProvider\TagServiceInterface;
use DateTime;
use RuntimeException;

class PayoutSetTransactionMethod extends AbstractTransactionMethod implements
    TagServiceInterface,
    TransactionMethodInterface
{
    const METHOD = 'payoutSet';

    public function getTagServiceName(): string
    {
        return self::METHOD;
    }

    public function compileDescription(Transaction $transaction): string
    {
        $payoutSet = $this->getPayoutSet($transaction);

        $payoutMethod = $this->repositoryProvider->get(PayoutMethod::class)->findById($payoutSet->payoutMethodId);
        $methodName = $this->translator->trans("method.name.{$payoutMethod->name}", [], 'payout');

        $parameters = ['%method%' => $methodName, '%reciever%' => $payoutSet->receiver];
        $description = $this->translator->trans('description.'.self::METHOD, $parameters, 'transaction');

        return $description;
    }

    public function getState(Transaction $transaction): string
    {
        $payoutSet = $this->getPayoutSet($transaction);

        if ($payoutSet->statusId === PayoutSet::STATUS_ID_NEW) {
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
        $payoutSet = $this->getPayoutSet($transaction);

        return $payoutSet->createdTs;
    }

    public function getId(Transaction $transaction): int
    {
        $payoutSet = $this->getPayoutSet($transaction);

        return $payoutSet->id;
    }

    private function getPayoutSet(Transaction $transaction): PayoutSet
    {
        if (self::METHOD !== $transaction->method) {
            throw new RuntimeException("Expect '".self::METHOD."' transaction method");
        }

        /** @var PayoutSet $payoutSet */
        $payoutSet = $this->repositoryProvider->get(PayoutSet::class)->findById($transaction->methodId);

        return $payoutSet;
    }
}
