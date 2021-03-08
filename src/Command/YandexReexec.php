<?php namespace App\Command;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\MessageBroker;
use App\PaymentSystemManager\YandexManager;
use App\Repository\PaymentShotRepository;
use Monolog\Logger;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class YandexReexec extends AbstractCommand
{
    const COMMAND_NAME = 'yandex:reexec';

    private $messageBroker;
    private $yandexManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addOption('interval', null, InputOption::VALUE_OPTIONAL)
            ->addOption('paymentShotId', null, InputOption::VALUE_OPTIONAL);
    }

    public function __construct(
        Logger $logger,
        YandexManager $yandexManager,
        MessageBroker $messageBroker
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->logger = $logger;
        $this->messageBroker = $messageBroker;
        $this->yandexManager = $yandexManager;
    }

    protected function do(InputInterface $input, OutputInterface $output)
    {
        $interval = $input->getOption('interval');
        $paymentShotId = $input->getOption('paymentShotId');
        /** @var PaymentShotRepository $paymentShotRepository */
        $paymentShotRepository = $this->repositoryProvider->get(PaymentShot::class);

        if (null !== $interval) {
            $startDate = strtotime("-$interval");
            $endDate = strtotime('now');
            $minutes = round(abs($startDate - $endDate) / 60);
            $output->writeln("Interval $minutes minutes");

            $paymentShots = $paymentShotRepository
                ->getLastShotsByPaymentMethodId($minutes, 30, PaymentMethod::METHOD_YANDEX_CARD_ID);
        } elseif (null !== $paymentShotId) {
            $paymentShotId = (int) $paymentShotId;
            /** @var PaymentShot|null $paymentShot */
            $paymentShot = $paymentShotRepository->findById($paymentShotId);
            if (null === $paymentShot) {
                throw new RuntimeException('PaymentShot not found');
            } elseif ($paymentShot->paymentMethodId !== PaymentMethod::METHOD_YANDEX_CARD_ID) {
                throw new RuntimeException('It\'s not yandex card');
            }
            $paymentShots = [$paymentShot];
        } else {
            throw new RuntimeException('interval or paymentShotId option is required');
        }

        $paymentShotsNum = count($paymentShots);
        foreach ($paymentShots as $k => $paymentShot) {
            $output->writeln("Handling $k/$paymentShotsNum");
            /** @var Payment $payment */
            $payment = $this->repositoryProvider->get(Payment::class)->findById($paymentShot->paymentId);
            /** @var PaymentAccount $paymentAccount */
            $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
                ->findById($paymentShot->paymentAccountId);
            $processParams = $this->yandexManager
                ->compileProcessParams($paymentShot->initData['params']['cps_context_id'], $paymentAccount, $payment);
            $this->yandexManager->sendCheckMessage($payment, $paymentShot, $processParams, 0, true);
            usleep(200000);
        }
    }
}
