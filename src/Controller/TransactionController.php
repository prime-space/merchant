<?php namespace App\Controller;

use App\Authenticator;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\TagServiceProvider\TagServiceProvider;
use App\Transaction\Method\PayoutTransactionMethod;
use App\Transaction\Method\TransactionMethodInterface;
use App\VueViewCompiler;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

class TransactionController extends Controller implements SignControllerInterface
{
    private $repositoryProvider;
    private $translator;
    private $authenticator;
    private $tagServiceProvider;
    private $transactionMethods;
    /**
     * @var PayoutTransactionMethod
     */
    private $payoutTransactionMethod;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        TranslatorInterface $translator,
        Authenticator $authenticator,
        TagServiceProvider $tagServiceProvider,
        iterable $transactionMethods,
        PayoutTransactionMethod $payoutTransactionMethod
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->translator = $translator;
        $this->authenticator = $authenticator;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->transactionMethods = $transactionMethods;
        $this->payoutTransactionMethod = $payoutTransactionMethod;
    }

    public function transactions(Request $request, int $fromTransactionId)
    {
        $user = $this->authenticator->getUser();
        /** @var TransactionRepository $transactionRepository */
        $transactionRepository = $this->repositoryProvider->get(Transaction::class);
        $transactions = $transactionRepository
            ->findTransactionsByUserAndFromTransactionId($user->id, $fromTransactionId);
        $result = [
            'transactions' => [],
            'lastTransactionIdInList' => 0,
        ];
        foreach ($transactions as $transaction) {
            /** @var TransactionMethodInterface|null $transactionMethod */
            $transactionMethod = $this->tagServiceProvider->get($this->transactionMethods, $transaction->method);
            if (null !== $transactionMethod) {
                if (!$transactionMethod->isShowInWallet()) {
                    continue;
                }
                $description = $transactionMethod->compileDescription($transaction);
                $state = $transactionMethod->getState($transaction);
                $date = $transactionMethod->getDate($transaction);
                $id = $transactionMethod->getId($transaction);
            } else {
                $description = $transaction->method;
                $state = $transaction->isExecuted()
                    ? TransactionMethodInterface::STATE_EXECUTED
                    : TransactionMethodInterface::STATE_PROCESS;
                $date = $transaction->createdTs;
                $id = $transaction->id;
            }
            $result['transactions'][] = [
                'id' => $id,
                'description' => $description,
                'amount' => $transaction->amount,
                'currencySign' => $this->translator->trans("currency.$transaction->currencyId.sign", [], 'payment'),
                'state' => $state,
                'date' => $date->format(VueViewCompiler::TIMEZONEJS_DATE_FORMAT),
            ];
            $result['lastTransactionIdInList'] = $transaction->id;
        }

        return new JsonResponse($result);
    }
}
