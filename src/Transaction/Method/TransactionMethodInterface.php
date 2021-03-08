<?php namespace App\Transaction\Method;

use App\Entity\Transaction;
use DateTime;

interface TransactionMethodInterface
{
    const STATE_EXECUTED = 'executed';
    const STATE_PROCESS = 'process';
    const STATE_ERROR = 'error';

    public function compileDescription(Transaction $transaction): string;
    public function getState(Transaction $transaction): string;
    public function getDate(Transaction $transaction): DateTime;
    public function getId(Transaction $transaction): int;
    public function isShowInWallet(): bool;
}
