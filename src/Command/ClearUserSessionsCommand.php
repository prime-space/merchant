<?php namespace App\Command;

use App\Entity\User;
use App\Logger\LogExtraDataKeeper;
use App\Repository\UserRepository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearUserSessionsCommand extends AbstractCommand
{
    const COMMAND_NAME = 'user:clear-sessions';

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);
    }

    public function __construct(
        Logger $logger
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->logger = $logger;
    }

    protected function do(InputInterface $input, OutputInterface $output)
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->repositoryProvider->get(User::class);
        $userRepository->clearSessions();
    }
}
