<?php namespace App\Transaction\Method;

use App\Entity\Payout;
use App\Entity\PayoutMethod;
use App\Entity\PayoutSet;
use App\Entity\Transaction;
use App\TagServiceProvider\TagServiceInterface;
use DateTime;
use RuntimeException;

class PayoutTransactionMethod extends AbstractTransactionMethod implements
    TagServiceInterface,
    TransactionMethodInterface
{
    const METHOD = 'payout';

    public function getTagServiceName(): string
    {
        return self::METHOD;
    }

    public function compileDescription(Transaction $transaction): string
    {
        $payout = $this->getPayout($transaction);
        /** @var PayoutSet $payoutSet */
        $payoutSet = $this->repositoryProvider->get(PayoutSet::class)->findById($payout->payoutSetId);

        $payoutMethod = $this->repositoryProvider->get(PayoutMethod::class)->findById($payoutSet->payoutMethodId);
        $methodName = $this->translator->trans("method.name.{$payoutMethod->name}", [], 'payout');

        $parameters = ['%method%' => $methodName, '%reciever%' => $payoutSet->receiver];
        $description = $this->translator->trans('description.'.self::METHOD, $parameters, 'transaction');

        return $description;
    }

    public function getState(Transaction $transaction): string
    {
        $payout = $this->getPayout($transaction);

        if ($payout->statusId === Payout::STATUS_ID_FAIL) {
            $state = TransactionMethodInterface::STATE_ERROR;
        } elseif (in_array($payout->statusId, [Payout::STATUS_ID_QUEUE, Payout::STATUS_ID_PROCESS], true)) {
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
        $payout = $this->getPayout($transaction);

        return $payout->createdTs;
    }

    public function getId(Transaction $transaction): int
    {
        $payout = $this->getPayout($transaction);

        return $payout->id;
    }

    private function getPayout(Transaction $transaction): Payout
    {
        if (self::METHOD !== $transaction->method) {
            throw new RuntimeException("Expect '".self::METHOD."' transaction method");
        }

        /** @var Payout $payout */
        $payout = $this->repositoryProvider->get(Payout::class)->findById($transaction->methodId);

        return $payout;
    }
}
