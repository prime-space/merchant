<?php namespace App\Transaction\Method;

use App\Entity\Payout;
use App\Entity\Transaction;
use App\TagServiceProvider\TagServiceInterface;
use DateTime;
use RuntimeException;

class PayoutReturnTransactionMethod extends AbstractTransactionMethod implements
    TagServiceInterface,
    TransactionMethodInterface
{
    const METHOD = 'payoutReturn';

    public function getTagServiceName(): string
    {
        return self::METHOD;
    }

    public function compileDescription(Transaction $transaction): string
    {
        $payout = $this->getPayout($transaction);

        $parameters = ['%payoutSetId%' => $payout->payoutSetId];
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
        return $transaction->createdTs;
    }

    public function getId(Transaction $transaction): int
    {
        $payout = $this->getPayout($transaction);

        return $payout->payoutSetId;
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
