<?php namespace App\Command;

use App\Entity\IpControlAttempt;
use App\IpControlAttemptProvider;
use App\Logger\LogExtraDataKeeper;
use App\Repository\IpControlAttemptRepository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearIpControlCommand extends AbstractCommand
{
    const COMMAND_NAME = 'ip-control:clear';

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
        /** @var IpControlAttemptRepository $ipControlRepository */
        $ipControlRepository = $this->repositoryProvider->get(IpControlAttempt::class);
        $ipControlRepository->deleteByLastMinutes(IpControlAttemptProvider::TIME_FOR_DELETION_MINUTES);
    }
}
