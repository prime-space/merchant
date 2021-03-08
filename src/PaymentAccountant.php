<?php namespace App;

use App\Entity\PaymentAccount;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bridge\Monolog\Logger;

class PaymentAccountant
{
    private $repositoryProvider;
    private $logger;
    private $telegramSender;
    private $adminApi;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        Logger $logger,
        TelegramSender $telegramSender,
        AdminApi $adminApi
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->logger = $logger;
        $this->telegramSender = $telegramSender;
        $this->adminApi = $adminApi;
    }

    public function deactivateAccount(PaymentAccount $account, string $reason = null)
    {
        $message = "Deactivate payment account #{$account->id}";
        if (null !== $reason) {
            $message .= " ($reason)";
        }
        $this->logger->critical($message);
        $account->isActive = false;
        $this->repositoryProvider->get(PaymentAccount::class)->update($account, ['isActive']);
        $this->telegramSender->send($message);
        $this->adminApi->deactivatePaymentAccount($account->id);
    }

    public function dropBalance(PaymentAccount $paymentAccount)
    {
        $paymentAccount->dropBalance();
        $this->repositoryProvider->get(PaymentAccount::class)->update($paymentAccount, ['balance']);
    }
}
