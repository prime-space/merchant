<?php namespace App\Command;

use App\Entity\PayoutMethod;
use App\Entity\User;
use App\Repository\UserRepository;
use Ewll\DBBundle\DB\Client as DbClient;
use Exception;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ExcludePayoutMethodByIdCommand extends AbstractCommand
{
    const COMMAND_NAME = 'payout-method:exclude';

    private $defaultDbClient;

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);
        $this->addArgument('payoutMethodId', InputArgument::REQUIRED);
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
        $payoutMethodIdToExclude = (int)$input->getArgument('payoutMethodId');
        $payoutMethodRepository = $this->repositoryProvider->get(PayoutMethod::class);
        $payoutMethod = $payoutMethodRepository->findById($payoutMethodIdToExclude);
        if ($payoutMethod === null) {
            $style->error('Invalid payout method id');

            return;
        }
        /** @var UserRepository $userRepository */
        $userRepository = $this->repositoryProvider->get(User::class);
        $offset = 0;
        $limit = 100;
        $usersTotal = $userRepository->getAllUsersCount();
        $progressBar = new ProgressBar($output, $usersTotal);
        $progressBar->start();
        do {
            $users = $userRepository->findByLimitOffset($offset, $limit);
            try {
                $this->defaultDbClient->beginTransaction();
                /** @var User $user */
                foreach ($users as $user) {
                    if (!in_array($payoutMethodIdToExclude, $user->excludedPayoutMethods)) {
                        $user->excludedPayoutMethods[] = $payoutMethodIdToExclude;
                        $userRepository->update($user, ['excludedPayoutMethods']);
                    }
                    $progressBar->advance();
                }
                $this->defaultDbClient->commit();
                $userRepository->clear();
            } catch (Exception $e) {
                $this->defaultDbClient->rollback();
                $style->error($e->getMessage());

                return;
            }
            $usersCount = count($users);
            $offset += $usersCount;
        } while ($usersCount > 0);

        $progressBar->finish();
        $style->success('Done');
    }
}
