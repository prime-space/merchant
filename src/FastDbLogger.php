<?php namespace App;

use App\Entity\FastLog;
use Ewll\DBBundle\Repository\RepositoryProvider;

class FastDbLogger
{
    private $repositoryProvider;

    public function __construct(
        RepositoryProvider $repositoryProvider
    ) {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function log(string $method, int $methodId, array $data): void
    {
        $fastLog = FastLog::create($method, $methodId, $data);

        $this->repositoryProvider->get(FastLog::class)->create($fastLog);
    }
}
