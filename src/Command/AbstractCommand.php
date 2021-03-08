<?php namespace App\Command;

use App\Logger\LogExtraDataKeeper;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;

abstract class AbstractCommand extends Command
{
    /** @var RepositoryProvider */
    protected $repositoryProvider;
    /** @var LogExtraDataKeeper */
    protected $logExtraDataKeeper;
    /** @var Logger */
    protected $logger;

    public function setLogExtraDataKeeper(LogExtraDataKeeper $logExtraDataKeeper): void
    {
        $this->logExtraDataKeeper = $logExtraDataKeeper;
    }

    public function setRepositoryProvider(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logExtraDataKeeper->setData([
            'module' => 'command',
            'name' => $this->getName(),
            'session' => microtime(true)
        ]);

        $this->logger->info("Start {$this->getName()}");

        $store = new FlockStore();
        $factory = new Factory($store);
        $lock = $factory->createLock(preg_replace('/[^\w\d\s]/', '_', $this->getName()));

        if (!$lock->acquire()) {
            $this->logger->critical('Sorry, cannot lock file');
            return;
        }
        $this->do($input, $output);
        $this->logger->info("Command {$this->getName()} has successfully finished");
    }

    abstract protected function do(InputInterface $input, OutputInterface $output);
}
