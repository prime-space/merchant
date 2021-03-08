<?php namespace App;

use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\Shop;
use App\Exception\CannotFetchPaymentAccountException;
use App\Exception\InsufficientFundsException;
use App\Exception\PaymentMethodNotAvailableException;
use App\Repository\PaymentAccountRepository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use LogicException;
use Symfony\Bridge\Monolog\Logger;

class PaymentAccountFetcher
{
    const PAYMENT_SYSTEM_YANDEX_ID = 1;
    const PAYMENT_SYSTEM_YANDEX_CARD_ID = 2;
    const PAYMENT_SYSTEM_QIWI_ID = 3;
    const PAYMENT_SYSTEM_ROBOKASSA_ID = 4;
    const PAYMENT_SYSTEM_INTERKASSA_ID = 5;
    const PAYMENT_SYSTEM_WEBMONEY_ID = 6;

    const ENABLED_FOR_MERCHANT = 'merchant';
    const ENABLED_FOR_WITHDRAW = 'withdraw';

    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    /** @throws PaymentMethodNotAvailableException */
    public function findForPayment(
        PaymentMethod $paymentMethod,
        Shop $shop,
        PaymentShot $paymentShot = null
    ): PaymentAccount {
        if ($paymentMethod->id === PaymentMethod::METHOD_TEST_ID) {
            $paymentAccount = PaymentAccount::getFakeForTestMethod();
        } elseif (null !== $paymentShot) {
            $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
                ->findById($paymentShot->paymentAccountId);
        } else {
            $paymentAccount = $this->fetchOneForPayment($paymentMethod->paymentSystemId, $shop->id);
            if (null === $paymentAccount) {
                throw new PaymentMethodNotAvailableException(__FILE__.__LINE__);
            }
        }

        return $paymentAccount;
    }

    /**
     * @throws CannotFetchPaymentAccountException
     * @throws InsufficientFundsException
     */
    public function fetchOneForPayout($paymentSystemId, $balanceKey, $amount, Logger $logger = null): PaymentAccount
    {
        /** @var PaymentAccountRepository $paymentAccountRepository */
        $paymentAccountRepository = $this->repositoryProvider->get(PaymentAccount::class);
        /** @var PaymentAccount[] $rawPaymentAccounts */
        $rawPaymentAccounts = $paymentAccountRepository->findBy(['paymentSystemId' => $paymentSystemId]);
        $filteredPaymentAccounts = $this->filterPaymentAccounts($rawPaymentAccounts, self::ENABLED_FOR_WITHDRAW, false);

        if (count($filteredPaymentAccounts) === 0) {
            throw new CannotFetchPaymentAccountException();
        }

        $whitePaymentAccountViews = [];
        $whitePaymentAccounts = [];
        $normalPaymentAccountViews = [];
        $normalPaymentAccounts = [];
        foreach ($filteredPaymentAccounts as $paymentAccount) {
            if ($paymentAccount->isWhite) {
                $whitePaymentAccountViews[] = $paymentAccount->compileLogView($balanceKey);
                $whitePaymentAccounts[] = $paymentAccount;
            } else {
                $normalPaymentAccountViews[] = $paymentAccount->compileLogView($balanceKey);
                $normalPaymentAccounts[] = $paymentAccount;
            }
        }

        if (null !== $logger) {
            $logger->info(
                'Fetched accounts',
                ['white' => $whitePaymentAccountViews, 'normal' => $normalPaymentAccountViews]
            );
        }

        foreach ([$normalPaymentAccounts, $whitePaymentAccounts] as $paymentAccounts) {
            $paymentAccount = $this->getMostFatAccount($paymentAccounts, $balanceKey);
            if (null !== $paymentAccount && $this->isSufficientFunds($paymentAccount, $amount, $balanceKey)) {
                if (null !== $logger) {
                    $logger->info("Fetched account #{$paymentAccount->id}");
                }

                return $paymentAccount;
            }
        }

        throw new InsufficientFundsException();
    }

    public function fetchOneForPayment($paymentSystemId, $assignedId): ?PaymentAccount
    {
        /** @var PaymentAccountRepository $paymentAccountRepository */
        $paymentAccountRepository = $this->repositoryProvider->get(PaymentAccount::class);
        /** @var PaymentAccount[] $rawPaymentAccounts */
        $rawPaymentAccounts = $paymentAccountRepository->findBy(['paymentSystemId' => $paymentSystemId]);
        $assignedPaymentAccounts = [];
        $commonPaymentAccounts = [];
        foreach ($rawPaymentAccounts as $paymentAccount) {
            if (count($paymentAccount->assignedIds) === 0) {
                $commonPaymentAccounts[] = $paymentAccount;
            } elseif (in_array($assignedId, $paymentAccount->assignedIds, true)) {
                $assignedPaymentAccounts[] = $paymentAccount;
            }
        }
        $paymentAccounts = count($assignedPaymentAccounts) > 0 ? $assignedPaymentAccounts : $commonPaymentAccounts;

        $paymentAccounts = $this->filterPaymentAccounts($paymentAccounts, self::ENABLED_FOR_MERCHANT);

        if (count($paymentAccounts) === 0) {
            return null;
        }

        $keys = array_keys($paymentAccounts);
        $weights = array_map(function (PaymentAccount $a) {
            return $a->weight;
        }, $paymentAccounts);

        $key = $this->weightedRandom($keys, $weights);

        return $paymentAccounts[$key];
    }

    public function fetchOneForWhiteBalancing($paymentSystemId): ?PaymentAccount
    {
        $paymentAccounts = $this->repositoryProvider->get(PaymentAccount::class)->findBy([
            'paymentSystemId' => $paymentSystemId,
            'isActive' => 1,
            'isWhite' => 1,
        ]);

        if (count($paymentAccounts) === 0) {
            return null;
        }

        $randKey = array_rand($paymentAccounts);

        return $paymentAccounts[$randKey];
    }

    private function weightedRandom($keys, $weights)
    {
        $total = array_sum($weights);
        $n = 0;

        $num = mt_rand(1, $total);
        foreach ($keys as $i => $value) {
            $n += $weights[$i];
            if ($n >= $num) {
                return $keys[$i];
            }
        }
    }

    /**
     * @param PaymentAccount[] $paymentAccounts
     * @return PaymentAccount[]
     */
    private function filterPaymentAccounts(array $paymentAccounts, $enabledFor, $excludeWhite = true): array
    {
        $filteredPaymentAccounts = [];
        foreach ($paymentAccounts as $paymentAccount) {
            if (!$paymentAccount->isActive) {
                continue;
            }
            if ($excludeWhite && $paymentAccount->isWhite) {
                continue;
            }
            if (!in_array($enabledFor, $paymentAccount->enabled, true)) {
                continue;
            }

            $filteredPaymentAccounts[] = $paymentAccount;
        }

        return $filteredPaymentAccounts;
    }

    private function getMostFatAccount(array $accounts, $balanceKey): ?PaymentAccount
    {
        if (count($accounts) === 0) {
            return null;
        }

        usort($accounts, function (PaymentAccount $a, PaymentAccount $b) use ($balanceKey) {
            return bccomp($b->getBalance($balanceKey), $a->getBalance($balanceKey), 2);
        });

        return $accounts[0];
    }

    private function isSufficientFunds(PaymentAccount $paymentAccount, $amount, $balanceKey): bool
    {
        $balance = $paymentAccount->getBalance($balanceKey);

        return bccomp($balance, $amount, 2) >= 0;
    }
}
