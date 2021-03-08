<?php namespace App\PaymentSystemManager;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\Shop;
use App\Exception\CannotBuildLinkUrlException;
use App\Exception\CannotInitPaymentException;
use App\Exception\PaymentMethodNotAvailableException;
use App\PaymentAccountFetcher;
use App\TagServiceProvider\TagServiceInterface;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

class ExchangerManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PreInitPaymentInterface,
    PaymentOverLink
{
    private $yandexManager;
    private $repositoryProvider;
    private $paymentAccountFetcher;

    public function __construct(
        Logger $logger,
        Router $router,
        YandexManager $yandexManager,
        RepositoryProvider $repositoryProvider,
        PaymentAccountFetcher $paymentAccountFetcher
    ) {
        parent::__construct($logger, $router);
        $this->yandexManager = $yandexManager;
        $this->repositoryProvider = $repositoryProvider;
        $this->paymentAccountFetcher = $paymentAccountFetcher;
    }

    public function getTagServiceName(): string
    {
        return 'exchanger';
    }

    public function getPaymentSystemId(): int
    {
        return 13;
    }

    /** @inheritdoc */
    public function preInitPayment(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): array {
        $this->logger->info("Pre init payment {$paymentShot->paymentId}", [
            'paymentShotId' => $paymentShot->id,
            'paymentMethodId' => $paymentMethod->id,
        ]);
        $yandexCardPaymentMethod = $this->repositoryProvider->get(PaymentMethod::class)
            ->findById(YandexManager::PAYMENT_METHOD_CARD_ID);
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

        $yandexPaymentAccount = $this->paymentAccountFetcher
            ->fetchOneForPayment($yandexCardPaymentMethod->paymentSystemId, $shop);
        if (null === $yandexPaymentAccount) {
            throw new CannotInitPaymentException();
        }
        $paymentShot->subPaymentAccountId = $yandexPaymentAccount->id;
        $this->repositoryProvider->get(PaymentShot::class)->update($paymentShot, ['subPaymentAccountId']);

        return [];
    }

    /** @inheritdoc */
    public function getLinkUrl(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): string {
        $url = "https://{$paymentAccount->config['host']}/payment/{$payment->hash}";

        return $url;
    }
}
