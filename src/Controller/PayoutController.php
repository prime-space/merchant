<?php namespace App\Controller;

use App\Accountant;
use App\Authenticator;
use App\Constraints\PayoutMethod as PayoutMethodConstraint;
use App\Entity\Account;
use App\Entity\Payout;
use App\Entity\PayoutMethod;
use App\Entity\PayoutSet;
use App\Entity\User;
use App\Exception\FormValidationException;
use App\Exception\InsufficientFundsException;
use App\Form\Extension\Core\DataTransformer\PayoutMethodCodeToIdTransformer;
use App\Repository\PayoutRepository;
use App\Repository\PayoutSetRepository;
use App\TagServiceProvider\TagServiceProvider;
use App\VueViewCompiler;
use DateTime;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

class PayoutController extends Controller implements SignControllerInterface
{
    private $repositoryProvider;
    private $authenticator;
    private $translator;
    private $vueViewCompiler;
    private $tagServiceProvider;
    private $paymentSystemManagers;
    private $accountant;
    private $payoutMethodCodeToIdTransformer;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider,
        TranslatorInterface $translator,
        VueViewCompiler $vueViewCompiler,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers,
        Accountant $accountant,
        PayoutMethodCodeToIdTransformer $payoutMethodCodeToIdTransformer
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
        $this->translator = $translator;
        $this->vueViewCompiler = $vueViewCompiler;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->accountant = $accountant;
        $this->payoutMethodCodeToIdTransformer = $payoutMethodCodeToIdTransformer;
    }

    public function payouts(Request $request, int $limit, int $pageId)
    {
        $formBuilder = $this->createFormBuilder()
            ->add('id', IntegerType::class)
            ->add('internalUsersId', IntegerType::class)
            ->add('payoutMethod', TextType::class, ['constraints' => [new PayoutMethodConstraint()]])
            ->add('receiver', TextType::class);
//            ->add('statusId', IntegerType::class);
        $formBuilder->get('payoutMethod')
            ->addModelTransformer($this->payoutMethodCodeToIdTransformer);
        $form = $formBuilder->getForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            return new JsonResponse([], 400);
        }
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
        } catch (FormValidationException $e) {
            $errors = $this->vueViewCompiler->formErrorsViewCompile($form->getErrors(true));

            return new JsonResponse(['errors' => $errors], 400);
        }
        $data = array_filter($form->getData());
        $userId = $this->authenticator->getUser()->id;

        /** @var PayoutSetRepository $payoutSetRepository */
        $payoutSetRepository = $this->repositoryProvider->get(PayoutSet::class);

        $filterRows = ['userId' => $userId];
        if (isset($data['id'])) {
            $filterRows['id'] = $data['id'];
        }
        if (isset($data['internalUsersId'])) {
            $filterRows['internalUsersId'] = $data['internalUsersId'];
        }
        if (isset($data['payoutMethod'])) {
            $filterRows['payoutMethodId'] = $data['payoutMethod'];
        }
        if (isset($data['receiver'])) {
            $filterRows['receiver'] = $data['receiver'];
        }
//        if (isset($data['statusId'])) {
//            $filterRows['statusId'] = $data['statusId'];
//        }

        /** @var PayoutSet[] $payoutSets */
        $payoutSets = $payoutSetRepository
            ->findByFilterWithPaginationOrderByStatus($pageId, $limit, $filterRows);
        $total = $payoutSetRepository->getFoundRows();

        /** @var Account[] $userAccountIndexedById */
        $userAccountIndexedById = $this->repositoryProvider->get(Account::class)->findBy(
            ['userId' => $userId],
            'id'
        );
        /** @var PayoutMethod[] $payoutMethods */
        $payoutMethods = $this->repositoryProvider->get(PayoutMethod::class)->findAll('id');
        $payoutMethodsSelectView = [];
        foreach ($payoutMethods as $payoutMethod) {
            $isPayoutMethodExcluded = in_array(
                $payoutMethod->id,
                $this->authenticator->getUser()->excludedPayoutMethods
            );
            if ($payoutMethod->isEnabled && !$isPayoutMethodExcluded) {
                $payoutMethodsSelectView[] = $payoutMethod->compileVueSelectView($this->translator);
            }
        }

//        $statuses = Payout::STATUSES_FILTER;
//        $statusesVueSelect = [];
//        foreach ($statuses as $status) {
//            $statusesVueSelect[] = [
//                'text' => $this->translator->trans("status.{$status}", [], 'payout'),
//                'value' => $status,
//            ];
//        }
        $items = [];
        foreach ($payoutSets as $payoutSet) {
            $items[] = $payoutSet->compileTableView($this->translator, $userAccountIndexedById, $payoutMethods);
        }

        return new JsonResponse([
            'payouts' => $items,
            'total' => $total,
//            'statuses' => $statusesVueSelect,
            'payoutMethods' => $payoutMethodsSelectView,
        ]);
    }

    public function payout(Request $request)
    {
        try {
            $form = $this->accountant->getLKPayoutForm();

            $form->handleRequest($request);
            if (!$form->isSubmitted()) {
                return new JsonResponse([], 400);
            }
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $data = $form->getData();

            if ($data['rememberPassword'] === true) {
                $user = $this->authenticator->getUser();
                $user->doNotAskPassUntilTs = new DateTime('+10 minutes');
                $this->repositoryProvider->get(User::class)->update($user, ['doNotAskPassUntilTs']);
            }
            try {
                $payoutSet = $this->accountant->payout(
                    $data[Accountant::PAYOUT_FROM_FIELD_NAME_METHOD],
                    $this->authenticator->getUser(),
                    $data[Accountant::PAYOUT_FROM_FIELD_NAME_AMOUNT],
                    $data[Accountant::PAYOUT_FROM_FIELD_NAME_ACCOUNT],
                    $data[Accountant::PAYOUT_FROM_FIELD_NAME_RECEIVER]
                );
            } catch (InsufficientFundsException $e) {
                $errorMessage = $this->translateErrorMessage('account.insufficient-funds');
                $form->get(Accountant::PAYOUT_FROM_FIELD_NAME_AMOUNT)->addError(new FormError($errorMessage));

                throw new FormValidationException();
            }

            return new JsonResponse(['credit' => $payoutSet->credit]);
        } catch (FormValidationException $e) {
            $errors = $this->vueViewCompiler->formErrorsViewCompile($form->getErrors(true));

            return new JsonResponse(['errors' => $errors], 400);
        }
    }

    private function translateErrorMessage(string $errorText): string
    {
        $translatedErrorMessage = $this->translator->trans($errorText, [], 'validators');

        return $translatedErrorMessage;
    }
}
