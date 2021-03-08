<?php namespace App\Command;

use App\Entity\PaymentMethod;
use App\Entity\Shop;
use App\Repository\ShopRepository;
use Ewll\DBBundle\DB\Client as DbClient;
use Exception;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ExcludePaymentMethodByIdCommand extends AbstractCommand
{
    const COMMAND_NAME = 'payment-method:exclude';

    private $defaultDbClient;

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);
        $this->addArgument('paymentMethodId', InputArgument::REQUIRED);
    }

    public function __construct(
        Logger $logger,
        DbClient $defaultDbClient
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->logger = $logger;
        $this->defaultDbClient = $defaultDbClient;
    }

    protected function do(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $paymentMethodIdToExclude = (int)$input->getArgument('paymentMethodId');
        $paymentMethodRepository = $this->repositoryProvider->get(PaymentMethod::class);
        $paymentMethod = $paymentMethodRepository->findById($paymentMethodIdToExclude);
        if ($paymentMethod === null) {
            $style->error('Invalid payment method id');

            return;
        }
        /** @var ShopRepository $shopRepository */
        $shopRepository = $this->repositoryProvider->get(Shop::class);
        $offset = 0;
        $limit = 100;
        $shopsTotal = $shopRepository->getAllShopsCount();
        $progressBar = new ProgressBar($output, $shopsTotal);
        $progressBar->start();
        do {
            $shops = $shopRepository->findByLimitOffset($offset, $limit);
            try {
                $this->defaultDbClient->beginTransaction();
                /** @var Shop $shop */
                foreach ($shops as $shop) {
                    if (!in_array($paymentMethodIdToExclude, $shop->excludedMethodsByAdmin)) {
                        $shop->excludedMethodsByAdmin[] = $paymentMethodIdToExclude;
                        $shopRepository->update($shop, ['excludedMethodsByAdmin']);
                    }
                    $progressBar->advance();
                }
                $this->defaultDbClient->commit();
                $shopRepository->clear();
            } catch (Exception $e) {
                $this->defaultDbClient->rollback();
                $style->error($e->getMessage());

                return;
            }
            $shopsCount = count($shops);
            $offset += $shopsCount;
        } while ($shopsCount > 0);

        $progressBar->finish();
        $style->success('Done');
    }
}
