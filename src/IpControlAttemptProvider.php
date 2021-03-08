<?php namespace App;

use App\Entity\IpControlAttempt;
use App\Repository\IpControlAttemptRepository;
use Ewll\DBBundle\Exception\ExecuteException;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bridge\Monolog\Logger;

class IpControlAttemptProvider
{
    const TIME_FOR_DELETION_MINUTES = 15;

    private $logger;
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider, Logger $logger)
    {
        $this->logger = $logger;
        $this->repositoryProvider = $repositoryProvider;
    }

    public function create(string $ip): void
    {
        $ipControlAttempt = IpControlAttempt::create($ip);
        try {
            /** @var IpControlAttemptRepository $ipControlAttemptRepository */
            $ipControlAttemptRepository = $this->repositoryProvider->get(IpControlAttempt::class);
            $ipControlAttemptRepository->create($ipControlAttempt);
        } catch (ExecuteException $e) {
            $this->logger->crit("Unknown ip type: '$ip'");

            throw $e;
        }
    }

    public function isTooManyAttempts(string $ip): bool
    {
        /** @var IpControlAttemptRepository $ipControlAttemptRepository */
        $ipControlAttemptRepository = $this->repositoryProvider->get(IpControlAttempt::class);
        $isTooManyAttempts = $ipControlAttemptRepository->isTooManyAttempts($ip);


        return $isTooManyAttempts;
    }

    public function clear($ip)
    {
        /** @var IpControlAttemptRepository $ipControlAttemptRepository */
        $ipControlAttemptRepository = $this->repositoryProvider->get(IpControlAttempt::class);
        $ipControlAttemptRepository->deleteByIp($ip);
    }
}
