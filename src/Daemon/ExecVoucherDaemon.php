<?php namespace App\Daemon;

use App\Entity\User;
use App\Entity\Voucher;
use App\Exception\NotFoundException;
use App\MessageBroker;
use App\VoucherManager;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExecVoucherDaemon extends Daemon
{
    private $messageBroker;
    private $voucherManager;

    protected function configure()
    {
        $this->setName('daemon:exec-voucher');
    }

    public function __construct(
        Logger $logger,
        MessageBroker $messageBroker,
        VoucherManager $voucherManager
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->messageBroker = $messageBroker;
        $this->voucherManager = $voucherManager;
    }

    /** @inheritdoc */
    protected function do(InputInterface $input, OutputInterface $output)
    {
        $message = $this->messageBroker->getMessage(MessageBroker::QUEUE_EXEC_VOUCHER);
        $this->logExtraDataKeeper->setParam('voucherId', $message['voucherId']);
        $this->logger->info('Execute voucher');

        /** @var Voucher|null $voucher */
        $voucher = $this->repositoryProvider->get(Voucher::class)->findById($message['voucherId']);
        if (null === $voucher) {
            throw new NotFoundException('Transaction not found');
        } elseif ($voucher->statusId !== Voucher::STATUS_ID_NEW) {
            $this->logger->error('Already executed');

            return;
        }

        /** @var User $user */
        $user = $this->repositoryProvider->get(User::class)->findById($message['userId']);

        $this->voucherManager->execute($voucher, $user);

        $this->logger->info('Success');
    }
}
