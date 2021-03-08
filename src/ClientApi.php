<?php namespace App;

use App\Constraints\Accuracy;
use App\Constraints\Currency;
use App\Entity\PaymentSystem;
use App\Entity\Payout;
use App\Entity\PayoutMethod;
use App\Entity\PayoutSet;
use App\Entity\User;
use App\Exception\AccountNotFoundException;
use App\Exception\ClientApiBlockedException;
use App\Exception\ClientApiRequestException;
use App\Exception\FormValidationException;
use App\Exception\InsufficientFundsException;
use App\PaymentSystemManager\PaymentSystemManagerInterface;
use App\PaymentSystemManager\PayoutInterface;
use App\Repository\PayoutRepository;
use App\Repository\PayoutSetRepository;
use App\TagServiceProvider\TagServiceProvider;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ClientApi
{
    private $repositoryProvider;
    private $accountant;
    private $tagServiceProvider;
    private $paymentSystemManagers;
    /** @var Request */
    private $request;
    private $formFactory;
    private $logger;
    private $authenticator;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        Accountant $accountant,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers,
        FormFactoryInterface $formFactory,
        Logger $logger,
        Authenticator $authenticator
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->accountant = $accountant;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->formFactory = $formFactory;
        $this->logger = $logger;
        $this->authenticator = $authenticator;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @throws ClientApiRequestException
     */
    public function payoutMethod(ParameterBag $post): array
    {
        /*DEBUG*/$timeStart = microtime(true);
        $form = $this->accountant->getApiPayoutForm();

        $form->handleRequest($this->request);
        if (!$form->isSubmitted()) {
            throw new ClientApiRequestException(['Empty data']);
        }
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $data = $form->getData();
            $internalUsersId = $data[Accountant::PAYOUT_FROM_FIELD_NAME_ID];
            try {
                $payoutSet = $this->accountant->payout(
                    $data[Accountant::PAYOUT_FROM_FIELD_NAME_METHOD],
                    $this->authenticator->getUser(),
                    $data[Accountant::PAYOUT_FROM_FIELD_NAME_AMOUNT],
                    $data[Accountant::PAYOUT_FROM_FIELD_NAME_ACCOUNT],
                    $data[Accountant::PAYOUT_FROM_FIELD_NAME_RECEIVER],
                    $internalUsersId
                );
            } catch (InsufficientFundsException $e) {
                $this->addFormError($form, Accountant::PAYOUT_FROM_FIELD_NAME_AMOUNT, 'Insufficient funds');
                throw new FormValidationException();
            }

            /*DEBUG*/$timeDuration = microtime(true) - $timeStart;
            /*DEBUG*/$level = $timeDuration > 5 ? 'warning' : 'info';
            /*DEBUG*/$this->logger->$level("Duration '$timeDuration'", [
                'internalUsersId' => $internalUsersId,
                'payoutSetId' => $payoutSet->id
            ]);

            return ['operationId' => $payoutSet->id];
        } catch (FormValidationException $e) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }
            throw new ClientApiRequestException($errors);

        }
    }

    /** @throws ClientApiRequestException */
    public function payoutsMethod(ParameterBag $post): array
    {
        $formBuilder = $this->formFactory->createNamedBuilder(null)
            ->add('fromOperationId', IntegerType::class, [
                'constraints' => [new NotBlank()]
            ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($this->request);
        if (!$form->isSubmitted()) {
            throw new ClientApiRequestException(['Empty data']);
        }
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $data = $form->getData();

            /** @var PayoutSetRepository $payoutSetRepository */
            $payoutSetRepository = $this->repositoryProvider->get(PayoutSet::class);
            $userId = $this->authenticator->getUser()->id;
            $payoutSets = $payoutSetRepository->findByUserIdFromIdLimited($userId, $data['fromOperationId']);
            $views = [];
            foreach ($payoutSets as $payoutSet) {
                $views[] = $payoutSet->compileClientApiInfoView();
            }

            return ['operations' => $views];
        } catch (FormValidationException $e) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }
            throw new ClientApiRequestException($errors);

        }
    }

    /** @throws ClientApiRequestException */
    public function accountsMethod(ParameterBag $post): array
    {
        $user = $this->authenticator->getUser();
        $accountViews = $this->accountant->compileAccountsView($user);

        return $accountViews;
    }

    private function addFormError(FormInterface $form, string $fieldName, string $errorMessage)
    {
        $error = new FormError($errorMessage);
        $error->setOrigin($form->get($fieldName));
        $form->addError($error);
    }
}
