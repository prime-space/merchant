<?php namespace App\Command;

use App\Entity\Session;
use App\Logger\LogExtraDataKeeper;
use App\Repository\SessionRepository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearSessionsCommand extends AbstractCommand
{
    const COMMAND_NAME = 'sessions:clear';

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
        /** @var SessionRepository $sessionRepository */
        $sessionRepository = $this->repositoryProvider->get(Session::class);
        $sessionRepository->deleteByHours(Session::CLEAR_SESSIONS_INTERVAL_HR);
    }
}
