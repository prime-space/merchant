<?php namespace App\Command;

use App\Entity\Payout;
use App\Payout\Processor\Exception\ProcessCheckingOrUnknownStatusException;
use App\Payout\Processor\PayoutProcessor;
use Exception;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PayoutRecheckCommand extends AbstractCommand
{
    const COMMAND_NAME = 'payout:recheck';

    private $payoutProcessor;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument('payoutId', InputArgument::REQUIRED);
    }

    public function __construct(
        Logger $logger,
        PayoutProcessor $payoutProcessor
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->logger = $logger;
        $this->payoutProcessor = $payoutProcessor;
    }

    /** @throws Exception */
    protected function do(InputInterface $input, OutputInterface $output)
    {
        $payoutId = $input->getArgument('payoutId');

        /** @var Payout|null $payout */
        $payout = $this->repositoryProvider->get(Payout::class)->findById($payoutId);
        if (null === $payout) {
            $output->writeln('Payout not found');

            return;
        }
        $this->logExtraDataKeeper->setParam('payoutId', $payoutId);
        $this->logger->info("Manual recheck");

        try {
            $payoutCheckingResult = $this->payoutProcessor->processCheckingOrUnknownStatus($payout);
            $this->logger->info("Status: $payoutCheckingResult");
            $output->writeln("Status: $payoutCheckingResult");
        } catch (ProcessCheckingOrUnknownStatusException $e) {
            $this->logger->error("ProcessCheckingOrUnknownStatusException Code: {$e->getCode()}");

            throw $e;
        }
    }
}
