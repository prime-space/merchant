<?php namespace App\Controller;

use App\Constraints\Accuracy;
use App\Constraints\Captcha;
use App\Constraints\Currency;
use App\Constraints\PaymentSign;
use App\Entity\Masked;
use App\Exception\CannotBuildLinkUrlException;
use App\Exception\CannotCompileContinuePageContextException;
use App\Exception\CannotCompileFormDataException;
use App\Exception\CannotCompileSpecialHtmlException;
use App\Exception\CannotSetLimitExceededEmailSentFlag;
use App\Exception\MakeFormException;
use App\Exception\MaskedAuthException;
use App\Exception\MaskedNotFoundException;
use App\Exception\NotFoundException;
use App\Exception\SelfFormHandlingException;
use App\Exception\SkipCheckingResultRequestException;
use App\PaymentDayStatisticCounter;
use App\CurrencyConverter;
use App\Entity\Currency as EntityCurrency;
use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentMethodGroup;
use App\Entity\PaymentShot;
use App\Entity\PaymentSystem;
use App\Entity\Shop;
use App\Entity\User;
use App\Exception\CannotInitPaymentException;
use App\Exception\CheckingResultRequestException;
use App\Exception\PaymentException;
use App\Exception\PaymentMethodNotAvailableException;
use App\Exception\PaymentMethodNotFoundException;
use App\FeeFetcher;
use App\Mailer;
use App\MessageBroker;
use App\PaymentAccountFetcher;
use App\PaymentSystemManager\AbstractPaymentSystemManager;
use App\PaymentSystemManager\FormData;
use App\PaymentSystemManager\InputRequestResultInterface;
use App\PaymentSystemManager\MpayCardManager;
use App\PaymentSystemManager\PaymentOverForm;
use App\PaymentSystemManager\PaymentOverLink;
use App\PaymentSystemManager\PaymentOverSelfForm;
use App\PaymentSystemManager\PaymentOverSpecialHtml;
use App\PaymentSystemManager\PaymentOverSpecialWaitingPage;
use App\PaymentSystemManager\PaymentSystemManagerInterface;
use App\PaymentSystemManager\PreInitPaymentInterface;
use App\PaymentSystemManager\SpecialWaitingPage;
use App\PaymentSystemManager\WaitingPageData;
use App\PaymentSystemManager\YandexManager;
use App\PostbackManager;
use App\Repository\PaymentMethodRepository;
use App\TagServiceProvider\TagServiceProvider;
use App\VueViewCompiler;
use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

class MerchantController extends Controller
{
    const RETURN_ROUTE = 'merchant.return';
    const PAYMENT_BY_HASH_ROUTE = 'merchant.payment-by-hash';
    const PAYMENT_BY_HASH_ERROR_ROUTE = 'merchant.payment-by-hash.error';

    const PAGE_NAME_METHODS = 'methods';
    const PAGE_NAME_WAITING = 'waiting';
    const PAGE_NAME_DEAD = 'dead';
    const PAGE_NAME_WRONG = 'wrong';
    const PAGE_NAME_REDIRECT = 'redirect';
    const PAGE_NAME_CONTINUE = 'continue';

    private $logger;
    private $formFactory;
    private $repositoryProvider;
    private $translator;
    private $paymentAccountFetcher;
    private $tagServiceProvider;
    private $paymentSystemManagers;
    private $messageBroker;
    private $currencyConverter;
    private $defaultDbClient;
    private $feeFetcher;
    private $vueViewCompiler;
    private $mailer;
    private $emailAddressClaims;
    private $paymentDayStatisticCounter;
    private $postbackManager;
    private $yandexManager;

    public function __construct(
        Logger $logger,
        FormFactory $formFactory,
        RepositoryProvider $repositoryProvider,
        TranslatorInterface $translator,
        PaymentAccountFetcher $paymentAccountFetcher,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers,
        MessageBroker $messageBroker,
        CurrencyConverter $currencyConverter,
        DbClient $defaultDbClient,
        FeeFetcher $feeFetcher,
        VueViewCompiler $vueViewCompiler,
        Mailer $mailer,
        string $emailAddressClaims,
        PaymentDayStatisticCounter $paymentDayStatisticCounter,
        PostbackManager $postbackManager,
        YandexManager $yandexManager
    ) {
        $this->logger = $logger;
        $this->formFactory = $formFactory;
        $this->repositoryProvider = $repositoryProvider;
        $this->translator = $translator;
        $this->paymentAccountFetcher = $paymentAccountFetcher;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->messageBroker = $messageBroker;
        $this->currencyConverter = $currencyConverter;
        $this->defaultDbClient = $defaultDbClient;
        $this->feeFetcher = $feeFetcher;
        $this->vueViewCompiler = $vueViewCompiler;
        $this->mailer = $mailer;
        $this->emailAddressClaims = $emailAddressClaims;
        $this->paymentDayStatisticCounter = $paymentDayStatisticCounter;
        $this->postbackManager = $postbackManager;
        $this->yandexManager = $yandexManager;
    }

    public function payment(Request $request)
    {
        $jsConfig = [];
        try {
            $userVarKeys = $this->getUserVarKeysFromRequest($request);
            $formData = $this->handleFormByRequest($request, $userVarKeys);
            $shop = $this->getShopByFormData($formData, Masked::ID_DEFAULT);
            $payment = $this->getPaymentByFormData($shop, $formData);
            $isNewPayment = null === $payment;
            $payment = $this
                ->handlePaymentWithFormData($shop, $formData, $userVarKeys, $request->getClientIp(), $payment);
            if ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
                return $this->redirect("/payment/{$payment->hash}");
            }
            $jsConfig['hash'] = $payment->hash;

            $paymentMethod = $this->getPaymentMethodFromFormData($formData, $shop);
            $isNewPaymentWithMethod = null !== $paymentMethod && $isNewPayment;
            $isUpdatePaymentMethod = null !== $paymentMethod && $payment->paymentMethodId !== $paymentMethod->id;

            if ($isNewPaymentWithMethod || $isUpdatePaymentMethod) {
                $jsConfig['startPage'] = self::PAGE_NAME_CONTINUE;
                $jsConfig['continuePageData'] = $this->compileContinuePageContext($payment, $paymentMethod, $shop);

                return $this
                    ->render('payment/main.html.twig', $this->compileMainPageContext($jsConfig, $payment, $shop));
            } else {
                return $this->redirect("/payment/{$payment->hash}");
            }
        } catch (PaymentMethodNotAvailableException
                |PaymentMethodNotFoundException
                |CannotCompileContinuePageContextException $e
        ) {
            return $this->redirect("/payment/{$payment->hash}");
        } catch (PaymentException $e) {
            $jsConfig['startPage'] = self::PAGE_NAME_DEAD;
            $jsConfig['error'] = $e->getMessage();

            return $this->render('payment/dead.html.twig', ['jsConfig' => addslashes(json_encode($jsConfig, JSON_HEX_QUOT|JSON_HEX_APOS))]);
        }
    }

    public function paymentByHash(Request $request, string $hash)
    {
        $jsConfig = ['hash' => $hash];
        try {
            try {
                $payment = $this->findPaymentByHash($hash);
            } catch (NotFoundException $e) {
                throw new PaymentException($this->translator->trans('not-found', [], 'payment'));
            }

            /** @var Shop $shop */
            $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

            if ($shop->maskedId !== Masked::ID_DEFAULT) {
                $redirectToMaskedResponse = $this->compileRedirectToMaskedResponse($payment->hash, $shop->maskedId);

                return $redirectToMaskedResponse;
            }

            if ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
                $jsConfig['startPage'] = self::PAGE_NAME_REDIRECT;
                $jsConfig['redirectFormData'] = $payment->compileRedirectFormData();
            } elseif (null === $payment->paymentMethodId) {
                $jsConfig['startPage'] =  self::PAGE_NAME_METHODS;
            } else {
                $jsConfig['startPage'] = self::PAGE_NAME_WAITING;
            }

            return $this->render('payment/main.html.twig', $this->compileMainPageContext($jsConfig, $payment, $shop));

        } catch (PaymentException $e) {
            $jsConfig['startPage'] = self::PAGE_NAME_DEAD;
            $jsConfig['error'] = $e->getMessage();

            return $this->render('payment/dead.html.twig', [
                'jsConfig' => addslashes(json_encode($jsConfig, JSON_HEX_QUOT|JSON_HEX_APOS)),
            ]);
        }
    }

    public function maskedPayment(Request $request)
    {
        try {
            $masked = $this->getMaskedByRequest($request);
        } catch (MaskedAuthException $e) {
            return new JsonResponse([], 401);
        } catch (MaskedNotFoundException $e) {
            return new JsonResponse([], 403);
        }
        try {
            $userVarKeys = $this->getUserVarKeysFromRequest($request);
            $formData = $this->handleFormByRequest($request, $userVarKeys);
            //$formData['via'] = PaymentMethod::METHOD_NAME_CARD_RUB_DIR;
            $shop = $this->getShopByFormData($formData, $masked->id);
            $payment = $this->getPaymentByFormData($shop, $formData);
            $clientIp = $request->headers->get('masked-client-ip');
            if (null === $clientIp) {
                throw new RuntimeException('Missing masked-client-ip header');
            }
            $payment = $this
                ->handlePaymentWithFormData($shop, $formData, $userVarKeys, $clientIp, $payment);
            //$paymentMethod = $this->getPaymentMethodFromFormData($formData, $shop);
            //$continueData = $this->compileContinuePageContext($payment, $paymentMethod, $shop);
            //$context = $this->compileMainPageContext(['startPage' => self::PAGE_NAME_METHODS], $payment, $shop);
            $context['hash'] = $this->compileMaskedHash($payment->hash);

            return new JsonResponse(['data' => $context]);
        } catch (PaymentMethodNotAvailableException
                 |PaymentMethodNotFoundException
                 |CannotCompileContinuePageContextException $e
        ) {
            return new JsonResponse(['error' => 'Ошибка. Попробуйте еще раз'], 400);
        } catch (PaymentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function maskedPaymentStatus(Request $request, string $hash)
    {
        try {
            $masked = $this->getMaskedByRequest($request);
        } catch (MaskedAuthException $e) {
            return new JsonResponse([], 401);
        } catch (MaskedNotFoundException $e) {
            return new JsonResponse([], 403);
        }

        try {
            $payment = $this->findPaymentByHash($hash);
        } catch (NotFoundException $e) {
            return new JsonResponse([], 404);
        }

        /** @var PaymentShot|null $paymentShot */
        $paymentShot = $this->repositoryProvider->get(PaymentShot::class)->findOneBy(['paymentId' => $payment->id]);
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

        if ($paymentShot === null) {
            /** @var Shop|null $shop */
            $response = [
                'action' => 'selectMethod',
                'data' => $this->compileMainPageContext(['startPage' => self::PAGE_NAME_METHODS], $payment, $shop),
            ];
        } elseif ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
            $response = [
                'action' => 'formSubmit',
                'formData' => $payment->compileRedirectFormData(),
                'data' => $this->compileMainPageContext(['startPage' => self::PAGE_NAME_REDIRECT], $payment, $shop),
            ];
        } else {
            $response = [
                'action' => 'waiting',
                'data' => $this->compileMainPageContext(['startPage' => self::PAGE_NAME_WAITING], $payment, $shop),
            ];
        }

        return new JsonResponse($response);
    }

    public function maskedPaymentMobile(Request $request, string $hash)
    {
        try {
            $masked = $this->getMaskedByRequest($request);
        } catch (MaskedAuthException $e) {
            return new JsonResponse([], 401);
        } catch (MaskedNotFoundException $e) {
            return new JsonResponse([], 403);
        }

        try {
            $payment = $this->findPaymentByHash($hash);
        } catch (NotFoundException $e) {
            return new JsonResponse([], 404);
        }

        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

        $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)
            ->findById(PaymentMethod::METHOD_MPAY_BEELINE_ID);
        try {
            if (null === $paymentMethod) {
                throw new PaymentException('method-not-found', 404);
            }
            if (!$this->isMethodAvailable($paymentMethod, $shop)) {
                throw new PaymentException('method-not-available', 400);
            }
            try {
                $context = $this->compileContinuePageContext($payment, $paymentMethod, $shop);
            } catch (PaymentMethodNotAvailableException|CannotCompileContinuePageContextException $e) {
                throw new PaymentException('method-not-available', 400);
            }

            $paymentSystemManager = $this->getPaymentSystemManager($paymentMethod->paymentSystemId);
            if (!$paymentSystemManager instanceof PaymentOverSelfForm) {
                throw new RuntimeException('Only PaymentOverSelfForm');
            }
            $paymentShot = $this->findPaymentShot($payment->id, $paymentMethod->id);
            if (null === $paymentMethod) {
                throw new RuntimeException('PaymentShot must be exists');
            }
            /** @var PaymentAccount $paymentAccount */
            $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
                ->findById($paymentShot->paymentAccountId);
            $description = $this->compilePaymentDescription($payment, $paymentMethod->id);
            try {
                $handleData = ['number' => $request->get('number')];
                $handleSelfFormResult = $paymentSystemManager
                    ->handleSelfForm($payment, $paymentShot, $paymentAccount, $handleData, $description);
            } catch (SelfFormHandlingException $e) {
                $paymentSystemManager->getLogger()->error('SelfFormHandlingException '.$e->getMessage());
                throw new PaymentException('something-went-wrong', 500);
            }
            $responseData = [];
            if (null === $handleSelfFormResult) {
                $waitingPageData = $paymentSystemManager instanceof SpecialWaitingPage
                    ? $paymentSystemManager->getWaitingPageData($paymentShot)
                    : new WaitingPageData(WaitingPageData::TYPE_COMMON);
                $responseData['waitingPageData'] = $waitingPageData->toArray();
            } else {
                $responseData = $this
                    ->compileContinueCommonContext($payment, $paymentMethod, $paymentSystemManager, 'form');
                $responseData['form'] = $this->compileActionTypeForm($handleSelfFormResult);
            }

            return new JsonResponse($responseData);
        } catch (PaymentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        }
    }

    public function maskedPaymentCard(Request $request, string $hash)
    {
        try {
            $masked = $this->getMaskedByRequest($request);
        } catch (MaskedAuthException $e) {
            return new JsonResponse([], 401);
        } catch (MaskedNotFoundException $e) {
            return new JsonResponse([], 403);
        }

        try {
            $payment = $this->findPaymentByHash($hash);
        } catch (NotFoundException $e) {
            return new JsonResponse([], 404);
        }

        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

        $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)
            ->findById(PaymentMethod::METHOD_YANDEX_CARD_ID);
        try {
            if (null === $paymentMethod) {
                throw new PaymentException('method-not-found', 404);
            }
            if (!$this->isMethodAvailable($paymentMethod, $shop)) {
                throw new PaymentException('method-not-available 1', 400);
            }
            try {
                $this->compileContinuePageContext($payment, $paymentMethod, $shop);
            } catch (PaymentMethodNotAvailableException|CannotCompileContinuePageContextException $e) {
                throw new PaymentException('method-not-available 2 '.$e->getMessage(), 400);
            }

            $paymentSystemManager = $this->getPaymentSystemManager($paymentMethod->paymentSystemId);
            if (!$paymentSystemManager instanceof YandexManager) {
                throw new RuntimeException('Only YandexManager');
            }
            $paymentShot = $this->findPaymentShot($payment->id, $paymentMethod->id);
            if (null === $paymentMethod) {
                throw new RuntimeException('PaymentShot must be exists');
            }
            /** @var PaymentAccount $paymentAccount */
            $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
                ->findById($paymentShot->paymentAccountId);
            $description = $this->compilePaymentDescription($payment, $paymentMethod->id);
            try {
                $formData = $paymentSystemManager
                    ->mineCardNativeForm($paymentAccount, $payment, $paymentShot, $description);
            } catch (CannotCompileFormDataException $e) {
                throw new PaymentException('CannotCompileFormDataException: '.$e->getMessage(), 400);
            }
            $responseData = $formData->toArray();

            return new JsonResponse($responseData);
        } catch (PaymentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        }
    }

    public function maskedPaymentRetry(Request $request, string $hash)
    {
        try {
            $masked = $this->getMaskedByRequest($request);
        } catch (MaskedAuthException $e) {
            return new JsonResponse([], 401);
        } catch (MaskedNotFoundException $e) {
            return new JsonResponse([], 403);
        }

        try {
            $payment = $this->findPaymentByHash($hash);
        } catch (NotFoundException $e) {
            return new JsonResponse([], 404);
        }

        if ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
            $response = [
                'action' => 'formSubmit',
                'formData' => $payment->compileRedirectFormData(),
            ];
        } else {
            /** @var PaymentMethod $paymentMethod */
            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById($payment->paymentMethodId);
            $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);
            try {
                $continueData = $this->compileContinuePageContext($payment, $paymentMethod, $shop);
                $response = [
                    'action' => 'continue',
                    'data' => $continueData,
                ];
            } catch (PaymentMethodNotAvailableException
                    |CannotCompileContinuePageContextException $e
            ) {
                return new JsonResponse(['error' => 'Ошибка. Попробуйте еще раз'], 400);
            }
        }

        return new JsonResponse($response);
    }

    public function maskedPaymentAlternative(Request $request, string $hash)
    {
        try {
            $this->getMaskedByRequest($request);
        } catch (MaskedAuthException $e) {
            return new JsonResponse([], 401);
        } catch (MaskedNotFoundException $e) {
            return new JsonResponse([], 403);
        }

        try {
            $payment = $this->findPaymentByHash($hash);
        } catch (NotFoundException $e) {
            return new JsonResponse([], 404);
        }

        if ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
            $response = [
                'formData' => $payment->compileRedirectFormData(),
            ];
        } else {
            /** @var PaymentMethod $paymentMethod */
            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)
                ->findById(PaymentMethod::METHOD_MPAY_CARD_ID);
            $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);
            try {
                $this->compileContinuePageContext($payment, $paymentMethod, $shop);
                /** @var PaymentSystem $paymentSystem */
                $paymentSystem = $this->repositoryProvider->get(PaymentSystem::class)
                    ->findById($paymentMethod->paymentSystemId);
                /** @var MpayCardManager $mpayCardManager */
                $mpayCardManager = $this->tagServiceProvider
                    ->get($this->paymentSystemManagers, $paymentSystem->name);
                $paymentShot = $this->findPaymentShot($payment->id, $paymentMethod->id);
                /** @var PaymentAccount $paymentAccount */
                $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
                    ->findById($paymentShot->paymentAccountId);
                $description = $this->compilePaymentDescription($payment, $paymentMethod->id);
                $formData = $mpayCardManager->makePaymentForm(
                    $payment,
                    $paymentShot,
                    $paymentAccount,
                    $description,
                    $request->request->get('number'),
                    $request->request->get('holder'),
                    $request->request->get('month'),
                    $request->request->get('year'),
                    $request->request->get('cvc')
                );

                return new JsonResponse([
                    'action' => $formData->getUrl(),
                    'method' => $formData->getMethod(),
                    'fields' => $formData->getFields(),
                ]);
            } catch (PaymentMethodNotAvailableException
                    |CannotCompileContinuePageContextException
                    |MakeFormException $e
            ) {
                return new JsonResponse(['error' => 'Ошибка. Попробуйте еще раз'], 400);
            }
        }

        return new JsonResponse($response);
    }

    public function exchangerPayment(Request $request, string $hash)
    {
        $auth = $request->headers->get('authorization', '');
        if (1 !== preg_match('/^Bearer\s(.*):(.*)$/', $auth, $matches)) {
            return new JsonResponse([], 401);
        }
        $exchangerAccountId = (int)($matches[1] ?? 0);
        $exchangerAccountSecret = $matches[2] ?? '';
        /** @var PaymentAccount|null $exchangerAccount */
        $exchangerAccount = $this->repositoryProvider->get(PaymentAccount::class)->findById($exchangerAccountId);
        if (null === $exchangerAccount || $exchangerAccountSecret !== $exchangerAccount->config['secret']) {
            return new JsonResponse([], 401);
        }

        try {
            $payment = $this->findPaymentByHash($hash);
        } catch (NotFoundException $e) {
            return new JsonResponse([], 404);
        }
        if ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
            return new JsonResponse(['action' => 'redirect', 'url' => $payment->successUrl]);
        }
        if ($payment->paymentMethodId !== PaymentMethod::METHOD_EXCHANGER_ID) {
            throw new RuntimeException(
                "Only exchanger method available here. Got {$payment->paymentMethodId}. PaymentId #$payment->id"
            );
        }
        $paymentShot = $this->findPaymentShot($payment->id, $payment->paymentMethodId);
        if (null === $paymentShot) {
            return new JsonResponse([], 404);
        }
        $paymentMethodYandexCard = $this->repositoryProvider->get(PaymentMethod::class)
            ->findById(PaymentMethod::METHOD_YANDEX_CARD_ID);
        // !!! subPaymentAccountId
        $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
            ->findById($paymentShot->subPaymentAccountId);
        $description = $this->compilePaymentDescription($payment, $paymentShot->paymentMethodId);
        try {
            //Потенциальные проблемы - передаем paymentShot от Exchanger, а ожидаем от Yandex
            $formData = $this->yandexManager
                ->getFormData($paymentMethodYandexCard, $paymentAccount, $payment, $paymentShot, $description);
        } catch (CannotCompileFormDataException $e) {
            return new JsonResponse([], 500);
        }
        $data = [
            'action' => 'continue',
            'form' => $this->compileActionTypeForm($formData),
            'amount' => bcadd($paymentShot->amount, '0', 2),
        ];

        return new JsonResponse($data);
    }

    /**
     * @throws MaskedAuthException
     * @throws MaskedNotFoundException
     */
    private function getMaskedByRequest(Request $request): Masked
    {
        $auth = $request->headers->get('authorization', '');
        if (1 !== preg_match('/^Bearer\s(.*)$/', $auth, $matches)) {
            throw new MaskedAuthException();
        }
        $maskedKey = $matches[1];
        /** @var Masked|null $masked */
        $masked = $this->repositoryProvider->get(Masked::class)->findOneBy(['key' => $maskedKey]);
        if (null === $masked) {
            throw new MaskedNotFoundException();
        }

        return $masked;
    }

    public function paymentByHashError(Request $request, string $hash)
    {
        $jsConfig = ['hash' => $hash];
        try {
            $jsConfig['startPage'] = self::PAGE_NAME_WRONG;
            try {
                $payment = $this->findPaymentByHash($hash);
            } catch (NotFoundException $e) {
                throw new PaymentException($this->translator->trans('not-found', [], 'payment'));
            }

            if ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
                return $this->redirect("/payment/{$payment->hash}");
            }

            /** @var Shop $shop */
            $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

            if ($shop->maskedId !== Masked::ID_DEFAULT) {
                $redirectToMaskedResponse = $this
                    ->compileRedirectToMaskedResponse($payment->hash, $shop->maskedId, 'error');

                return $redirectToMaskedResponse;
            }

            $jsConfig['error'] = $this->translator->trans('something-went-wrong', [], 'payment');

            return $this->render('payment/main.html.twig', $this->compileMainPageContext($jsConfig, $payment, $shop));
        } catch (PaymentException $e) {
            $jsConfig['startPage'] = self::PAGE_NAME_DEAD;
            $jsConfig['error'] = $e->getMessage();

            return $this->render('payment/dead.html.twig', [
                'jsConfig' => addslashes(json_encode($jsConfig, JSON_HEX_QUOT|JSON_HEX_APOS))
            ]);
        }
    }

    public function selectMethod(Request $request, string $hash, int $paymentMethodId)
    {
        try {
            try {
                $payment = $this->findPaymentByHash($hash);
            } catch (NotFoundException $e) {
                throw new PaymentException('not-found', 404);
            }

            $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById($paymentMethodId);
            if (null === $paymentMethod) {
                throw new PaymentException('method-not-found', 404);
            }
            if (!$this->isMethodAvailable($paymentMethod, $shop)) {
                throw new PaymentException('method-not-available', 400);
            }
            try {
                $context = $this->compileContinuePageContext($payment, $paymentMethod, $shop);
            } catch (PaymentMethodNotAvailableException|CannotCompileContinuePageContextException $e) {
                throw new PaymentException('method-not-available', 400);
            }

            return new JsonResponse($context);
        } catch (PaymentException $e) {
            $data = [
                'error' => $this->translator->trans($e->getMessage(), [], 'payment'),
                'isPaymentFound' => isset($payment),
            ];

            return new JsonResponse($data, $e->getCode());
        }
    }

    public function selfForm(Request $request, $hash, int $paymentMethodId)
    {
        try {
            try {
                $payment = $this->findPaymentByHash($hash);
            } catch (NotFoundException $e) {
                throw new PaymentException('not-found', 404);
            }
            /** @var PaymentMethod|null $paymentMethod */
            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById($paymentMethodId);
            if (null === $paymentMethod) {
                throw new PaymentException('method-not-found', 404);
            }
            $paymentSystemManager = $this->getPaymentSystemManager($paymentMethod->paymentSystemId);
            if (!$paymentSystemManager instanceof PaymentOverSelfForm) {
                throw new RuntimeException('Only PaymentOverSelfForm');
            }
            $paymentShot = $this->findPaymentShot($payment->id, $paymentMethod->id);
            if (null === $paymentMethod) {
                throw new RuntimeException('PaymentShot must be exists');
            }
            /** @var PaymentAccount $paymentAccount */
            $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
                ->findById($paymentShot->paymentAccountId);
            $description = $this->compilePaymentDescription($payment, $paymentMethodId);
            try {
                $handleSelfFormResult = $paymentSystemManager
                    ->handleSelfForm($payment, $paymentShot, $paymentAccount, $request->request->all(), $description);
            } catch (SelfFormHandlingException $e) {
                $paymentSystemManager->getLogger()->error('SelfFormHandlingException '.$e->getMessage());
                throw new PaymentException('something-went-wrong', 500);
            }
            $responseData = [];
            if (null === $handleSelfFormResult) {
                $waitingPageData = $paymentSystemManager instanceof SpecialWaitingPage
                    ? $paymentSystemManager->getWaitingPageData($paymentShot)
                    : new WaitingPageData(WaitingPageData::TYPE_COMMON);
                $responseData['waitingPageData'] = $waitingPageData->toArray();
            } else {
                $responseData = $this->compileContinueCommonContext($payment, $paymentMethod, $paymentSystemManager, 'form');
                $responseData['form'] = $this->compileActionTypeForm($handleSelfFormResult);
            }

            return new JsonResponse($responseData);
        } catch (PaymentException $e) {
            $data = [
                'error' => $this->translator->trans($e->getMessage(), [], 'payment'),
                'isPaymentFound' => isset($payment),
            ];

            return new JsonResponse($data, $e->getCode());
        }
    }

    public function setEmail(Request $request, string $hash)
    {
        try {
            try {
                $payment = $this->findPaymentByHash($hash);
            } catch (NotFoundException $e) {
                throw new PaymentException($this->translator->trans('not-found', [], 'payment'), 404);
            }

            $formBuilder = $this->formFactory->createNamedBuilder(null, FormType::class)
                ->add('email', TextType::class, ['constraints' => [new NotBlank(), new Email()]]);
            $form = $formBuilder->getForm();
            $form->handleRequest($request);
            if (!$form->isSubmitted()) {
                throw new PaymentException(
                    $this->translator->trans('validation.missing-parameters', [], 'payment'),
                    400
                );
            }
            if (!$form->isValid()) {
                throw new PaymentException($this->compileInvalidFormMessage($form), 400);
            }
            $data = $form->getData();
            $payment->email = $data['email'];
            $this->repositoryProvider->get(Payment::class)->update($payment, ['email']);

            return new JsonResponse([]);
        } catch (PaymentException $e) {
            $data = [
                'error' => $e->getMessage(),
            ];

            return new JsonResponse($data, $e->getCode());
        }
    }

    public function status(Request $request, string $hash)
    {
        try {
            try {
                $payment = $this->findPaymentByHash($hash);
            } catch (NotFoundException $e) {
                throw new PaymentException('not-found', 404);
            }

            $waitingPageData = new WaitingPageData(WaitingPageData::TYPE_COMMON);
            $context = [
                'statusId' => $payment->statusId,
                'waitingPageData' => $waitingPageData->toArray(),
            ];
            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById($payment->paymentMethodId);
            $paymentSystemManager = $this->getPaymentSystemManager($paymentMethod->paymentSystemId);
            if ($paymentSystemManager instanceof SpecialWaitingPage) {
                $paymentShot = $this->findPaymentShot($payment->id, $paymentMethod->id);
                $context['waitingPageData'] = $paymentSystemManager->getWaitingPageData($paymentShot)->toArray();
            }
            if ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
                $context['formData'] = $payment->compileRedirectFormData();
            }

            return new JsonResponse($context);
        } catch (PaymentException $e) {
            $data = [
                'error' => $this->translator->trans($e->getMessage(), [], 'payment'),
            ];

            return new JsonResponse($data, $e->getCode());
        }
    }

    public function return(Request $request, string $paymentSystemName, string $method)
    {
        throw new RuntimeException(
            "Deprecated using return page. paymentSystemName '$paymentSystemName', method '$method'"
        );
    }

    public function help(Request $request)
    {
        $formBuilder = $this->formFactory->createNamedBuilder(null)
            ->add('email', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'fill-field']), new Email()]
            ])
            ->add('description', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'fill-field']), new Length(['min' => 5, 'max' => 1000])]
            ])
            ->add('captcha', TextType::class, [
                'label' => false,
                'constraints' => [new NotBlank(['message' => 'fill-field']), new Captcha()]
            ]);
        $form = $formBuilder->getForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            return new JsonResponse([], 400);
        }
        if (!$form->isValid()) {
            $errors = $this->vueViewCompiler->formErrorsViewCompile($form->getErrors(true));

            return new JsonResponse(['errors' => $errors], 400);
        }

        $data = $form->getData();

        $this->mailer->create(
            $this->emailAddressClaims,
            Mailer::LETTER_NAME_HELP,
            [
                'clientEmail' => $data['email'],
                'description' => $data['description'],
            ]
        );

        return new JsonResponse([]);
    }

    public function result(Request $request, string $paymentSystemName)
    {
        /** @var PaymentSystemManagerInterface|AbstractPaymentSystemManager $paymentSystemManager */
        $paymentSystemManager = $this->tagServiceProvider->get($this->paymentSystemManagers, $paymentSystemName);
        if (null === $paymentSystemManager || !$paymentSystemManager instanceof InputRequestResultInterface) {
            $message = "Payment system manager for '$paymentSystemName' not found";
            $this->logger->error($message, [$request->request->all(), $request->query->all()]);
            return new Response($message, 404);
        }

        $method = $request->getRealMethod();
        if ($method === 'GET') {
            $data = $request->query->all();
        } else {
            $data = $request->request->all();
            if (count($data) === 0) {
                $data = json_decode($request->getContent(), true);
            }
            if (null === $data || count($data) === 0) {
                $paymentSystemManager->logger->info('Result. Checking availability detected');
                return new Response('');
            }
        }
        $paymentSystemManager->logger->info("Result '$method'", $data);

        $paymentShotId = $paymentSystemManager->getPaymentShotIdFromResultRequest($request);
        /** @var PaymentShot $paymentShot */
        $paymentShot = $this->repositoryProvider->get(PaymentShot::class)->findById($paymentShotId);
        if (null === $paymentShot) {
            $message = "Payment shot #$paymentShotId not found";
            $paymentSystemManager->logger->error($message);
            return new Response($message, 404);
        }
        /** @var Payment $payment */
        $payment = $this->repositoryProvider->get(Payment::class)->findById($paymentShot->paymentId);
        /** @var PaymentAccount $paymentAccount */
        $paymentAccount =
            $this->repositoryProvider->get(PaymentAccount::class)->findById($paymentShot->paymentAccountId);
        try {
            $paymentSystemManager->checkResultRequest($request, $payment, $paymentAccount, $paymentShot);
        } catch (CheckingResultRequestException $e) {
            $paymentSystemManager->logger->error("#{$payment->id} {$e->getMessage()}");

            return new Response('', 400);
        } catch (SkipCheckingResultRequestException $e) {
            $paymentSystemManager->logger->info("Skipping #{$payment->id} {$e->getMessage()}");

            return new Response($paymentSystemManager->getInputResultRequestSuccessMessage($paymentShot));
        }
        $paymentSystemManager->logger->info("#{$payment->id} success checked");

        $this->messageBroker->createMessage(MessageBroker::QUEUE_EXEC_PAYMENT_NAME, [
            'paymentShotId' => $paymentShot->id
        ]);
        $paymentSystemManager->logger->info("#{$payment->id} moved to execution");

        return new Response($paymentSystemManager->getInputResultRequestSuccessMessage($paymentShot));
    }

    public function shopPaymentMethods(Request $request, $shopId)
    {
        try {
            /** @var Shop|null $shop */
            $shop = $this->repositoryProvider->get(Shop::class)->findById($shopId);
            if (null === $shop) {
                throw new NotFoundException();
            }

            $methods = $this->getAvailablePaymentMethodsByShop($shop);
            $views = [];
            foreach ($methods as $method) {
                $views[] = $method->compileShopPaymentMethodsView();
            }

            return new JsonResponse($views);
        } catch (NotFoundException $e) {
            return new JsonResponse([], 404);
        }
    }

    /** @throws PaymentException */
    private function handleFormByRequest(Request $request, array $userVarKeys): array
    {
        $form = $this->createPaymentForm($userVarKeys);
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            throw new PaymentException($this->translator->trans('validation.missing-parameters', [], 'payment'));
        }
        if (!$form->isValid()) {
            throw new PaymentException($this->compileInvalidFormMessage($form));
        }
        $data = $form->getData();

        return $data;
    }

    private function getPaymentByFormData(Shop $shop, array $data): ?Payment
    {
        /** @var Payment|null $payment */
        $payment = $this->repositoryProvider->get(Payment::class)->findOneBy(
            ['shopId' => $shop->id, 'payment' => $data['payment']]
        );

        return $payment;
    }

    /** @throws PaymentException */
    private function getShopByFormData(array $data, int $maskedId): Shop
    {
        /** @var Shop|null $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($data['shop']);

        if (null === $shop) {
            throw new PaymentException($this->translator->trans('validation.shop.not-found', [], 'payment'));
        } elseif ($shop->statusId === Shop::STATUS_ID_DECLINED) {
            throw new PaymentException($this->translator->trans('validation.shop.not-allowed', [], 'payment'));
        } elseif ($shop->maskedId !== $maskedId) {
            throw new PaymentException($this->translator->trans('validation.shop.masked-not-match', [], 'payment'));
        }

        return $shop;
    }

    /** @throws PaymentException */
    private function handlePaymentWithFormData(
        Shop $shop,
        array $data,
        array $userVarKeys,
        string $ip,
        Payment $payment = null
    ): Payment {
        $userVars = [];
        foreach ($userVarKeys as $userVarKey) {
            $userVars[$userVarKey] = $data[$userVarKey];
        }

        $isUrlRedefine = isset($data['success']) || isset($data['fail']);
        if (!$shop->isAllowedToRedefineUrl && $isUrlRedefine) {
            throw new PaymentException(
                $this->translator->trans('validation.shop.not-allowed-redefine-url', [], 'payment')
            );
        }

        $isNewPayment = null === $payment;
        if ($isNewPayment) {
            $paymentDayStatistic = $this->paymentDayStatisticCounter
                ->getPaymentDayStatisticByShopIdForToday($shop->id);

            if ($this->paymentDayStatisticCounter->isDailyLimitExceeded(
                $paymentDayStatistic,
                $shop,
                $data['amount'],
                $data['currency']
            )) {
                if (!$paymentDayStatistic->isLimitExceededEmailSent) {
                    $paymentDayStatistic->isLimitExceededEmailSent = true;
                    try {
                        $this->paymentDayStatisticCounter
                            ->setLimitExceededEmailSentFlag($paymentDayStatistic);
                        /** @var User $user */
                        $user = $this->repositoryProvider->get(User::class)->findById($shop->userId);
                        $this->mailer->createForUser($user->id, Mailer::LETTER_NAME_PAYMENT_DAY_LIMIT_EXCEEDED, [
                            'amount' => $data['amount'],
                            'shopName' => $shop->name,
                        ]);
                    } catch (CannotSetLimitExceededEmailSentFlag $e) {
                        $this->logger->error(
                            "Cannot set isLimitExceededEmailSent. PaymentDayStatistic #{$paymentDayStatistic->id}"
                        );
                    }
                }
                throw new PaymentException($this->translator->trans('error.daily-limit-exceeded', [], 'payment'));
            }
            $payment = $this->factoryPayment($shop, $data, $userVars, $ip);
            if ($this->postbackManager->isNeedToSend($payment)) {
                $this->postbackManager->putToQueue($payment, PostbackManager::EVENT_PAYMENT_CREATED);
            }
        } else {
            $this->renewPayment($shop, $payment, $data, $userVars);
        }

        return $payment;
    }

    /** @throws NotFoundException */
    private function findPaymentByHash(string $hash): Payment
    {
        $paymentRepository = $this->repositoryProvider->get(Payment::class);
        /** @var Payment $payment */
        $payment = $payment = $paymentRepository->findOneBy(['hash' => $hash]);
        if (null === $payment) {
            throw new NotFoundException();
        }

        return $payment;
    }

    /** @throws PaymentException */
    private function compileMainPageContext(array $jsConfig, Payment $payment, Shop $shop): array
    {
        $currencyShortLatin = $this->translator->trans("currency.$payment->currency.short-latin", [], 'payment');
        $waitingPageData = new WaitingPageData(WaitingPageData::TYPE_COMMON);
        $availableMethodsWithoutExcluded = $this->getAvailablePaymentMethodsByShop($shop);
        $jsConfig['waitingPageData'] = $waitingPageData->toArray();
        $jsConfig['payment'] = $payment->paymentPageView();
        $jsConfig['methods'] = $this->compilePaymentMethodsForJsConfig($availableMethodsWithoutExcluded);

        if (in_array($jsConfig['startPage'], [self::PAGE_NAME_WAITING, self::PAGE_NAME_CONTINUE], true)) {
            /** @var PaymentMethod $paymentMethod */
            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById($payment->paymentMethodId);
            $paymentSystemManager = $this->getPaymentSystemManager($paymentMethod->paymentSystemId);
            if ($paymentSystemManager instanceof SpecialWaitingPage) {
                $paymentShot = $this->findPaymentShot($payment->id, $paymentMethod->id);
                $jsConfig['waitingPageData'] = $paymentSystemManager->getWaitingPageData($paymentShot)->toArray();
            }
        }

        return [
            'jsConfig' => addslashes(json_encode($jsConfig, JSON_HEX_QUOT|JSON_HEX_APOS)),
            'waitingPageData' => $jsConfig['waitingPageData'],
            'methods' => $this->compilePaymentMethodsForPaymentPage($availableMethodsWithoutExcluded),
            'shop' => $shop,
            'payment' => $payment->paymentPageView(),
            'currencyShortLatin' => $currencyShortLatin,
        ];
    }

    /**
     * @throws PaymentMethodNotFoundException
     * @throws PaymentMethodNotAvailableException
     */
    private function getPaymentMethodFromFormData(array $formData, Shop $shop): ?PaymentMethod
    {
        if (!empty($formData['via'])) {
            /** @var PaymentMethod $paymentMethod */
            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findOneBy(
                ['code' => $formData['via']]
            );
            if (null === $paymentMethod) {
                throw new PaymentMethodNotFoundException();
            }
            if (!$this->isMethodAvailable($paymentMethod, $shop)) {
                throw new PaymentMethodNotAvailableException();
            }

            //@TODO не учитывается наличие аккаунта
            return $paymentMethod;
        }

        return null;
    }

    private function isMethodAvailable(PaymentMethod $paymentMethod, Shop $shop): bool
    {
        if (!$paymentMethod->enabled) {
            return false;
        }
        if (in_array($paymentMethod->id, $this->getExcludedMethodsIds($shop), true)) {
            return false;
        }
        if ($paymentMethod->id === PaymentMethod::METHOD_TEST_ID && !$shop->isTestMode) {
            return false;
        }
        if ($shop->statusId !== Shop::STATUS_ID_OK && $paymentMethod->id !== PaymentMethod::METHOD_TEST_ID) {
            return false;
        }

        return true;
    }

    private function compileContinueCommonContext(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentSystemManagerInterface $paymentSystemManager,
        string $actionType
    ): array {
        $waitingPageData = new WaitingPageData(WaitingPageData::TYPE_COMMON);
        $context = [
            'methodId' => $paymentMethod->id,
            'methodName' => $paymentMethod->name,
            'methodImg' => $paymentMethod->img,
            'askEmail' => empty($payment->email),
            'amount' => $payment->amount,
            'waitingPageData' => $waitingPageData->toArray(),
            'isNeedToWaitingPage' => $paymentSystemManager->isNeedToWaitingPage(),
            'actionType' => $actionType,
        ];

        return $context;
    }

    /**
     * @throws PaymentMethodNotAvailableException
     * @throws CannotCompileContinuePageContextException
     */
    private function compileContinuePageContext(Payment $payment, PaymentMethod $paymentMethod, Shop $shop): array
    {
        $payment->paymentMethodId = $paymentMethod->id;
        $description = $this->compilePaymentDescription($payment, $paymentMethod->id);
        $paymentShot = $this->findPaymentShot($payment->id, $payment->paymentMethodId);
        $paymentAccount = $this->paymentAccountFetcher->findForPayment($paymentMethod, $shop, $paymentShot);
        $paymentSystemManager = $this->getPaymentSystemManager($paymentAccount->paymentSystemId);
        if (null === $paymentShot) {
            $paymentShot = $this->createPaymentShot(
                $paymentSystemManager,
                $payment,
                $paymentAccount,
                $paymentMethod,
                $shop,
                $description
            );
        }
        $this->repositoryProvider->get(Payment::class)->update($payment, ['paymentMethodId']);
        if ($paymentSystemManager instanceof PaymentOverForm) {
            try {
                $formData = $paymentSystemManager->getFormData(
                    $paymentMethod,
                    $paymentAccount,
                    $payment,
                    $paymentShot,
                    $description
                );
            } catch (CannotCompileFormDataException $e) {
                if (!empty($e->getMessage())) {
                    $paymentSystemManager->getLogger()
                        ->error('CannotCompileFormDataException '.$e->getMessage(), $e->getContext());
                }

                throw new CannotCompileContinuePageContextException(__LINE__);
            }

            $context = $this
                ->compileContinueCommonContext($payment, $paymentMethod, $paymentSystemManager, 'form');
            $context['form'] = $this->compileActionTypeForm($formData);
        } elseif ($paymentSystemManager instanceof PaymentOverLink) {
            $context = $this
                ->compileContinueCommonContext($payment, $paymentMethod, $paymentSystemManager, 'link');
            try {
                $context['linkUrl'] = $paymentSystemManager
                    ->getLinkUrl($payment, $paymentMethod, $paymentAccount, $paymentShot, $description);
            } catch (CannotBuildLinkUrlException $e) {
                $paymentSystemManager->getLogger()
                    ->error('CannotBuildLinkUrlException '.$e->getMessage());

                throw new CannotCompileContinuePageContextException(__LINE__);
            }
        } elseif ($paymentSystemManager instanceof PaymentOverSpecialWaitingPage) {
            $context = $this
                ->compileContinueCommonContext($payment, $paymentMethod, $paymentSystemManager, 'specialWaitingPage');
            $context['waitingPageData'] = $paymentSystemManager->getWaitingPageData($paymentShot)->toArray();
        } elseif ($paymentSystemManager instanceof PaymentOverSpecialHtml) {
            $context = $this
                ->compileContinueCommonContext($payment, $paymentMethod, $paymentSystemManager, 'specialHtml');
            try {
                $context['html'] = $paymentSystemManager->getSpecialHtml(
                    $paymentMethod,
                    $paymentAccount,
                    $payment,
                    $paymentShot,
                    $description
                );
            } catch (CannotCompileSpecialHtmlException $e) {
                $paymentSystemManager->getLogger()
                    ->error('CannotCompileSpecialHtmlException '.$e->getMessage(), $e->getContext());

                throw new CannotCompileContinuePageContextException(__LINE__);
            }
        } elseif ($paymentSystemManager instanceof PaymentOverSelfForm) {
            $context = $this
                ->compileContinueCommonContext($payment, $paymentMethod, $paymentSystemManager, 'selfForm');
            $context['selfFormType'] = $paymentSystemManager->getSelfFormType();
        } else {
            throw new RuntimeException('Unknown payment system manager type');
        }

        return $context;
    }

    private function compileActionTypeForm(FormData $formData): array
    {
        $form = [
            'method' => $formData->getMethod(),
            'action' => $formData->getUrl(),
            'fields' => $formData->getFields(),
            'customFormName' => $formData->getCustomFormName(),
            'buttonLabel' => $this->translator->trans('redirect.btn', [], 'payment'),
        ];

        return $form;
    }

    private function createPaymentForm(array $userVarKeys): FormInterface
    {
        $options = ['attr' => ['class' => 'payment-form', 'id' => 'form']];
        $formBuilder = $this->formFactory->createNamedBuilder(null, FormType::class, null, $options)
            ->add('pay', SubmitType::class, [
                'label' => $this->translator->trans('pay-button', [], 'payment'),
            ])
            ->add('shop', IntegerType::class, ['constraints' => [
                new NotBlank,
            ]])
            ->add('payment', IntegerType::class, ['constraints' => [
                new NotBlank,
                new GreaterThan(0),
            ]])
            ->add('amount', NumberType::class, ['constraints' => [
                new NotBlank,
                new GreaterThan(0),
                new LessThanOrEqual(1000000000),
                new Accuracy(2),
            ]])
            ->add('currency', IntegerType::class, ['constraints' => [
                new NotBlank,
                new Currency,
            ]])
            ->add('via', TextType::class, ['required' => false])
            ->add('sub_id', TextType::class, ['required' => false, 'constraints' => [
                    new Regex(['pattern' => '/^[a-z0-9]{1,32}$/i'])
            ]])
            ->add('success', TextType::class, ['required' => false, 'constraints' => [
                new Url,
            ]])
            ->add('fail', TextType::class, ['required' => false, 'constraints' => [
                new Url,
            ]])
            ->add('description', TextType::class, ['constraints' => [
                new NotBlank,
                new Length(['min' => 0, 'max' => 128])
            ]])
            ->add('email', TextType::class)
            ->add('sign', TextType::class, ['constraints' => [
                new NotBlank,
                new PaymentSign,
            ]]);

        foreach ($userVarKeys as $userVarKey) {
            $formBuilder->add($userVarKey, TextType::class, ['constraints' => [
                new NotBlank,
                new Length(['min' => 1, 'max' => '256']),
            ]]);
        }

        $form = $formBuilder->getForm();

        return $form;
    }

    private function compileInvalidFormMessage(FormInterface $form): string
    {
        $error = $form->getErrors(true)[0];
        $parameterName = $error->getOrigin()->getName();
        $exceptionMessage = $error->getMessage();
        $message = '' === $parameterName
            ? $exceptionMessage
            : sprintf(
                '%s %s - %s',
                $this->translator->trans('parameter', [], 'validators'),
                $parameterName,
                $exceptionMessage
            );

        return $message;
    }

    private function factoryPayment(Shop $shop, array $data, array $userVars, string $ip): Payment
    {
        $email = $this->getEmailByFormData($data);
        $successUrl = $data['success'] ?? $shop->successUrl;
        $failUrl = $data['fail'] ?? $shop->failUrl;
        $hash = hash('sha256', sprintf('%s%s%s', $shop->id, $data['payment'], microtime()));
        $sub_id = $data['sub_id'] ?? '';
        $payment = Payment::create(
            $shop->id,
            $data['payment'],
            $sub_id,
            $data['amount'],
            $shop->isFeeByClient,
            $data['currency'],
            $email,
            $hash,
            $successUrl,
            $failUrl,
            $data['description'],
            $userVars,
            $ip
        );
        $this->repositoryProvider->get(Payment::class)->create($payment);

        return $payment;
    }

    /**
     * @throws PaymentException
     */
    private function renewPayment(Shop $shop, Payment $payment, array $data, array $userVars)
    {
        $successUrl = $data['success'] ?? $shop->successUrl;
        $failUrl = $data['fail'] ?? $shop->failUrl;
        $forbiddenToChangeParameters = ['amount', 'currency'];
        foreach ($forbiddenToChangeParameters as $forbiddenToChangeParameter) {
            $value = $data[$forbiddenToChangeParameter];
            if ('amount' === $forbiddenToChangeParameter) {
                $value = number_format($value, 2, '.', '');
            }
            if ($payment->$forbiddenToChangeParameter !== $value) {
                $parameters = ['%parameterList%' => implode(', ', $forbiddenToChangeParameters)];
                $error = $this->translator->trans('validation.payment.forbidden-to-change', $parameters, 'payment');

                throw new PaymentException($error);
            }
        }
        $payment->email = $this->getEmailByFormData($data);
        $payment->successUrl = $successUrl;
        $payment->failUrl = $failUrl;
        $payment->description = $data['description'];
        $payment->sub_id = $data['sub_id'];
        $payment->userVars = $userVars;
        $this->repositoryProvider->get(Payment::class)
            ->update($payment, ['successUrl', 'failUrl', 'description', 'paymentMethodId', 'userVars']);
    }

    /** @return PaymentMethod[] */
    private function getAvailablePaymentMethodsByShop(Shop $shop): array
    {
        /** @var PaymentMethod[] $paymentMethods */
        $paymentMethods = [];
        /** @var PaymentMethodRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->repositoryProvider->get(PaymentMethod::class);

        if ($shop->isTestMode) {
            $paymentMethods[] = $paymentMethodRepository->findById(PaymentMethod::METHOD_TEST_ID);
        }

        if ($shop->statusId === Shop::STATUS_ID_OK) {
            /** @var PaymentMethod[] $availableMethods */
            $availableMethods = $paymentMethodRepository->getAvailableMethods();
            $excludedMethodsIds = $this->getExcludedMethodsIds($shop);

            //TODO не учитываются аккаунты чужих магазинов
            foreach ($availableMethods as $method) {
                if (!in_array($method->id, $excludedMethodsIds, true)) {
                    $paymentMethods[] = $method;
                }
            }
        }

        return $paymentMethods;
    }

    private function compilePaymentMethodsForJsConfig(array $availableMethodsWithoutExcluded): array
    {
        $methods = [];
        foreach ($availableMethodsWithoutExcluded as $method) {
            $methods[$method->id] = [
                'id' => $method->id,
                'name' => $method->name,
                'img' => $method->img,
                'alternativeId' => $method->alternativeId
            ];
        }

        return $methods;
    }

    private function compilePaymentMethodsForPaymentPage(array $availableMethodsWithoutExcluded): array
    {
        $paymentMethodGroupRepository = $this->repositoryProvider->get(PaymentMethodGroup::class);

        $groupIds = [];
        foreach ($availableMethodsWithoutExcluded as $method) {
            if (null !== $method->groupId) {
                $groupIds[] = $method->groupId;
            }
        }
        $countGroupIds = array_count_values($groupIds);

        $methods = [];
        $groupMap = [];
        foreach ($availableMethodsWithoutExcluded as $method) {
//            if ($this->isAlternativeMethod($availableMethodsWithoutExcluded, $method)) {
//                continue;
//            }
            $currencyShortLatin = $this->translator
                ->trans("currency.$method->currencyViewId.short-latin", [], 'payment');
            $isGroup = null !== $method->groupId && $countGroupIds[$method->groupId] > 1;
            $view = [
                'id' => $method->id,
                'isGroup' => false,
                'name' => $method->name,
                'currencyShortLatin' => $currencyShortLatin,
                'img' => $method->img,
                'alternativeId' => $method->alternativeId
            ];
            if ($isGroup) {
                $methodPosition = $groupMap[$method->groupId] ?? null;
                if (null === $methodPosition) {
                    /** @var PaymentMethodGroup $group */
                    $group = $paymentMethodGroupRepository->findById($method->groupId);
                    $methodPosition = count($methods);
                    $groupMap[$method->groupId] = $methodPosition;
                    $methods[$methodPosition] = [
                        'id' => $method->id,
                        'isGroup' => true,
                        'name' => $this->translator->trans("method-group.$method->groupId", [], 'payment'),
                        'img' => $group->img,
                        'methods' => [],
                    ];
                }
                $methods[$methodPosition]['methods'][] = $view;
            } else {
                $methods[] = $view;
            }
        }

        if (count($methods) === 0) {
            throw new PaymentException($this->translator->trans('validation.shop.no-one-method', [], 'payment'));
        }

        return $methods;
    }

    /**
     * @param PaymentMethod[] $methods
     * @param PaymentMethod $method
     * @return bool
     */
    private function isAlternativeMethod(array $methods, PaymentMethod $method): bool
    {
        foreach ($methods as $item) {
            if ($item->alternativeId === $method->id) {
                return true;
            }
        }

        return false;
    }

    /** @throws PaymentMethodNotAvailableException */
    private function createPaymentShot(
        PaymentSystemManagerInterface $paymentSystemManager,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentMethod $paymentMethod,
        Shop $shop,
        string $description
    ): PaymentShot {
        bcscale(EntityCurrency::MAX_SCALE);

        $paymentShotRepository = $this->repositoryProvider->get(PaymentShot::class);

        /** @var EntityCurrency $currency */
        $currency = $this->repositoryProvider->get(Currency::class)->findById($paymentMethod->currencyId);

        $fee = $this->feeFetcher->fetchPaymentFee($paymentMethod, $shop);
        $feeAmount = $this->feeFetcher->calcFeeAmount($payment->amount, $fee);
        $amount = $payment->isFeeByClient ? bcadd($payment->amount, $feeAmount) : $payment->amount;
        $amount = $this->currencyConverter->convert($payment->currency, $currency->id, $amount, $currency->scale);
        if (-1 === bccomp($amount, $paymentMethod->minimumAmount)) {
            $amount = $paymentMethod->minimumAmount;
        }
        $amount = bcadd($amount, '0', $currency->scale);
        $paymentShot = PaymentShot::create(
            $payment->id,
            $payment->paymentMethodId,
            $paymentAccount->id,
            $amount,
            $feeAmount
        );
        try {
            $this->defaultDbClient->beginTransaction();
            $paymentShotRepository->create($paymentShot);
            if ($paymentSystemManager instanceof PreInitPaymentInterface) {
                $initData = $paymentSystemManager->preInitPayment(
                    $payment,
                    $paymentMethod,
                    $paymentAccount,
                    $paymentShot,
                    $description
                );
                if (count($initData) > 0) {
                    $paymentShot->initData = $initData;
                    $paymentShotRepository->update($paymentShot, ['initData']);
                }
            }
            $this->defaultDbClient->commit();
        } catch (CannotInitPaymentException $e) {
            $this->defaultDbClient->rollback();
            $context = $e->getContext();
            $context['paymentId'] = $payment->id;
            $paymentSystemManager->getLogger()->error("CannotInitPaymentException: {$e->getMessage()}", $context);

            throw new PaymentMethodNotAvailableException(__FILE__.__LINE__);
        }

        return $paymentShot;
    }

    private function getPaymentSystemManager(int $paymentSystemId): PaymentSystemManagerInterface
    {
        $paymentSystemRepository = $this->repositoryProvider->get(PaymentSystem::class);
        $paymentSystem = $paymentSystemRepository->findById($paymentSystemId);
        /** @var PaymentSystemManagerInterface $paymentSystemManager */
        $paymentSystemManager = $this->tagServiceProvider
            ->get($this->paymentSystemManagers, $paymentSystem->name);

        return $paymentSystemManager;
    }

    private function getExcludedMethodsIds(Shop $shop): array
    {
        $ids = array_unique(array_merge($shop->excludedMethodsByAdmin, $shop->excludedMethodsByUser));

        return $ids;
    }

    private function getUserVarKeysFromRequest(Request $request): array
    {
        $userVarKeys = [];
        foreach ($request->request->all() as $postParamKey => $postParamValue) {
            if (preg_match('/^uv_[a-z0-9]+$/i', $postParamKey)) {
                $userVarKeys[] = $postParamKey;
            }
        }

        return $userVarKeys;
    }

    private function getEmailByFormData(array $data): string
    {
        $email = '';
        if (isset($data['email'])) {
            $validator = Validation::createValidator();
            $violations = $validator->validate($data['email'], [new NotBlank(), new Email()]);

            if (0 === count($violations)) {
                $email = $data['email'];
            }
        }

        return $email;
    }

    private function findPaymentShot($paymentId, $paymentMethodId): ?PaymentShot
    {
        /** @var PaymentShot|null $paymentShot */
        $paymentShot = $this->repositoryProvider->get(PaymentShot::class)->findOneBy(
            ['paymentId' => $paymentId, 'paymentMethodId' => $paymentMethodId]
        );

        return $paymentShot;
    }

    private function compilePaymentDescription(Payment $payment, int $paymentMethodId): string
    {
        $exchangerDescriptionPaymentMethodIds = [
            PaymentMethod::METHOD_YANDEX_ID,
            PaymentMethod::METHOD_YANDEX_CARD_ID,
            PaymentMethod::METHOD_EXCHANGER_ID,
        ];

        $placeholder = in_array($paymentMethodId, $exchangerDescriptionPaymentMethodIds, true)
            ? 'description-exchanger'
            : 'description';

        $description = $this->translator->trans(
            $placeholder,
            ['%paymentId%' => $payment->id, '%payment%' => $payment->payment],
            'payment'
        );

        return $description;
    }

    private function compileMaskedHash(string $hash): string
    {
        $splittedHash = str_split($hash, 4);
        $maskedHash = '';
        $map = [8, 7, 15, 0, 9, 6, 10, 5, 11, 4, 12, 3, 13, 2, 14, 1];
        foreach ($map as $key) {
            $maskedHash .= $splittedHash[$key];
        }

        return $maskedHash;
    }
    private function compileRedirectToMaskedResponse(string $hash, int $maskedId, string $page = null): RedirectResponse
    {
        /** @var Masked $masked */
        $masked = $this->repositoryProvider->get(Masked::class)->findById($maskedId);
        $maskedHash = $this->compileMaskedHash($hash);
        $url = "https://{$masked->domain}/payment/$maskedHash";
        if (null !== $page) {
            $url .= "/$page";
        }
        $response = $this->redirect($url);

        return $response;
    }
}
