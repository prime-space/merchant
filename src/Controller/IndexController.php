<?php namespace App\Controller;

use App\Accountant;
use App\Authenticator;
use App\CaptchaProvider;
use App\Constraints\Captcha;
use App\Entity\Currency;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PayoutMethod;
use App\Entity\User;
use App\Exception\CannotCheckRecaptchaException;
use App\Exception\CannotSignInException;
use App\Exception\ConfirmEmailSentException;
use App\Exception\ConfirmEmailException;
use App\Exception\FormValidationException;
use App\Exception\ItIsNotHumanException;
use App\IpControlAttemptProvider;
use App\Recaptcha;
use App\PaymentRefunder;
use App\TelegramSender;
use App\VueViewCompiler;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class IndexController extends Controller
{
    const CONFIRM_EMAIL_ROUTE_NAME = 'confirmEmail';

    private $authenticator;
    private $accountant;
    private $repositoryProvider;
    private $captcha;
    private $ipControlAttemptProvider;
    private $translator;
    private $telegramSender;
    private $vueViewCompiler;
    private $appKey;
    private $siteName;
    private $domain;
    private $proxies;
    private $recaptcha;
    private $paymentRefunder;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider,
        Accountant $accountant,
        CaptchaProvider $captcha,
        IpControlAttemptProvider $ipControlAttemptProvider,
        TranslatorInterface $translator,
        TelegramSender $telegramSender,
        VueViewCompiler $vueViewCompiler,
        string $appKey,
        string $siteName,
        string $domain,
        array $proxies,
        Recaptcha $recaptcha,
        PaymentRefunder $paymentRefunder
    ) {
        $this->authenticator = $authenticator;
        $this->accountant = $accountant;
        $this->repositoryProvider = $repositoryProvider;
        $this->captcha = $captcha;
        $this->ipControlAttemptProvider = $ipControlAttemptProvider;
        $this->translator = $translator;
        $this->telegramSender = $telegramSender;
        $this->vueViewCompiler = $vueViewCompiler;
        $this->appKey = $appKey;
        $this->siteName = $siteName;
        $this->domain = $domain;
        $this->proxies = $proxies;
        $this->recaptcha = $recaptcha;
        $this->paymentRefunder = $paymentRefunder;
    }

    public function index(Request $request)
    {
//        return $this->redirect('/private');
//
        $form = $this->buildConnectForm();

        return $this->render("landing/{$this->appKey}/landing.html.twig", [
            'form' => $form->createView(),
            'domain' => $this->domain,
        ]);
    }

    public function refundInfo()
    {
        return $this->render('refundInfo.html.twig');
    }

    public function doc()
    {
        $jsConfig = [
            'domain' => $this->domain,
        ];

        return $this->render('doc.html.twig', [
            'jsConfig' => json_encode($jsConfig),
            'siteName' => $this->siteName,
            'year' => date('Y'),
        ]);
    }

    public function connect(Request $request)
    {
        $form = $this->buildConnectForm();
        $form->handleRequest($request);
        try {
            if ($form->isSubmitted()) {
                if (!$form->isValid()) {
                    throw new FormValidationException();
                }
                if ($this->ipControlAttemptProvider->isTooManyAttempts($request->getClientIp())) {
                    $form->addError(new FormError(
                        $this->translator->trans('try-later', [], 'validators')
                    ));
                    throw new FormValidationException();
                }
                $this->ipControlAttemptProvider->create($request->getClientIp());
                $data = $form->getData();
                $message = sprintf(
                    "New connection request:\n Email - %s\n Name - %s\n URL - %s",
                    $data['email'],
                    $data['fullName'],
                    $data['site']
                );
                $this->telegramSender->send($message);

                return new JsonResponse([]);
            }
        } catch (FormValidationException $e) {
            $errors = $this->vueViewCompiler->formErrorsViewCompile($form->getErrors(true));

            return new JsonResponse($errors, 400);
        }

        return new JsonResponse([], 400);
    }

    public function loginOrRegister(Request $request, string $action)
    {
        $clientIp = $request->getClientIp();
        $isRegistration = $action === 'reg';
        $formErrorIterator = null;
        $isSigned = $this->authenticator->isSigned($request);
        if ($isSigned) {
            return $this->redirect('/private');
        } else {
            $form = $this->buildSignForm($request);
            $form->handleRequest($request);
            if ($form->isSubmitted()) {
                $this->ipControlAttemptProvider->create($clientIp);
                if ($form->isValid()) {
                    $data = $form->getData();
                    $recaptcha = $data['recaptcha'] ?? '';
                    try {
                        if ($isRegistration) {
                            $isFreeEmail = $this->authenticator->isFreeEmail($data['email']);
                            if (!$isFreeEmail) {
                                $form->addError(new FormError('not-unique-email'));
                            } else {
                                $this->authenticator->signUp($data['email'], $data['pass']);
                                $this->authenticator->signIn($data['email'], $data['pass']);
                                $this->ipControlAttemptProvider->clear($clientIp);

                                return $this->redirect('/private');
                            }
                        } else {
                            $this->recaptcha->check($data['email'], $recaptcha, $clientIp);
                            $isSigned = $this->authenticator->signIn($data['email'], $data['pass']);
                            if ($isSigned) {
                                $this->ipControlAttemptProvider->clear($clientIp);
                                return $this->redirect('/private');
                            } else {
                                $form->addError(new FormError('invalid-login-or-user-name'));
                            }
                        }
                    } catch (ConfirmEmailSentException $e) {
                        $this->ipControlAttemptProvider->clear($clientIp);
                        $form->addError(new FormError('confirm-email-sent'));
                    } catch (ConfirmEmailException $e) {
                        $form->addError(new FormError('confirm-email'));
                    } catch (ItIsNotHumanException $e) {
                        $form->addError(new FormError('robot'));
                    } catch (CannotSignInException $e) {
                        $form->addError(new FormError('try-later'));
                    } catch (CannotCheckRecaptchaException $e) {
                        $form->addError(new FormError('try-later'));
                    }
                }
                $formErrorIterator = $form->getErrors(true);
            }
            $form = $this->buildSignForm($request, $formErrorIterator);
            $templateData['form'] = $form->createView();
            if ($form->has('captcha')) {
                $templateData['captchaUrl'] = '/captcha?' . uniqid();
            }
        }

        return $this->render("landing/{$this->appKey}/$action.html.twig", $templateData);
    }

    public function admin(Request $request)
    {
        return $this->redirect('/private');
    }

    public function private(Request $request)
    {
        $isSigned = $this->authenticator->isSigned($request);

        if (!$isSigned) {
            return $this->redirect('/login');
        }

        $user = $this->authenticator->getUser();
        //TODO Костыль для того, что бы создать аккаунт, если его нет
        $this->accountant->getPaymentAccountByUserIdAndCurrencyId($user->id, Currency::CURRENCY_RUB_ID);
        $accountsView = $this->accountant->compileAccountsView($user);
        $userSettings = $user->compileJsConfigView($this->authenticator);

        if ($user->lkMode === User::LK_MODE_MERCHANT) {
            $paymentMethods = $this->repositoryProvider->get(PaymentMethod::class)->findAll('id');
            $jsConfig = [
                'token' => $user->token,
                'accounts' => $accountsView,
                'paymentMethods' => $paymentMethods,
                'userSettings' => $userSettings,
                'paymentNotificationStatuses' => Payment::NOTIFICATION_STATUSES_JS_CONFIG,
            ];
            $data = [
                'userId' => $user->id,
                'jsConfig' => json_encode($jsConfig),
                'siteName' => $this->siteName,
                'year' => date('Y'),
                'token' => $user->token,
            ];
        } elseif ($user->lkMode === User::LK_MODE_PURSE) {
            /** @var PayoutMethod[] $payoutMethods */
            $payoutMethods = $this->repositoryProvider->get(PayoutMethod::class)->findBy(['isEnabled' => 1]);
            $payoutMethodViews = [];
            foreach ($payoutMethods as $payoutMethod) {
                if (!in_array($payoutMethod->id, $user->excludedPayoutMethods)) {
                    $payoutMethodViews[] = $payoutMethod->compileWalletView($this->translator);
                }
            }
            $jsConfig = [
                'year' => date('Y'),
                'siteName' => $this->siteName,
                'token' => $user->token,
                'accounts' => $accountsView,
                'userSettings' => $userSettings,
                'payoutMethods' => $payoutMethodViews,
            ];
            $data = [
                'jsConfig' => addslashes(json_encode($jsConfig, JSON_HEX_QUOT | JSON_HEX_APOS)),
            ];
        } else {
            throw new RuntimeException('Unknown lk mode');
        }


        return $this->render("lk/{$user->lkMode}.html.twig", $data);
    }

    public function signOut()
    {
        $this->authenticator->signOut();

        return $this->redirect('/login');
    }

    public function captcha()
    {
        $headers = [
            "Content-type" => "image/png",
        ];
        $content = $this->captcha->getImage();

        return new Response($content, 200, $headers);
    }

    private function buildSignForm(Request $request, FormErrorIterator $formErrorIterator = null): FormInterface
    {
        $formBuilder = $this->createFormBuilder(null, ['allow_extra_fields' => true])
            ->add('email', TextType::class, [
                'label' => false,
                'constraints' => [new NotBlank(['message' => 'fill-field']), new Email()]
            ])
            ->add('recaptcha', HiddenType::class)
            ->add('pass', PasswordType::class, [
                'label' => false,
                'constraints' => [new NotBlank(['message' => 'fill-field'])]
            ])
            ->add('signIn', SubmitType::class, ['label' => 'Войти'])
            ->add('signUp', SubmitType::class, ['label' => 'Зарегистрироваться']);
        if ($this->ipControlAttemptProvider->isTooManyAttempts($request->getClientIp())) {
            $formBuilder->add('captcha', TextType::class, [
                'label' => false,
                'constraints' => [new NotBlank(['message' => 'fill-field']), new Captcha()]
            ]);
        }
        $form = $formBuilder->getForm();
        if ($formErrorIterator !== null) {
            foreach ($formErrorIterator as $error) {
                $form->addError($error);
            }
        }

        return $form;
    }

    public function confirmEmail(Request $request, string $code)
    {
        /** @var User|null $user */
        $user = $this->repositoryProvider->get(User::class)->findOneBy(['emailConfirmationCode' => $code]);
        if ($user === null) {
            return new Response('', 404);
        }
        $user->isEmailConfirmed = true;
        $user->emailConfirmationCode = null;
        $this->repositoryProvider->get(User::class)->update($user, ['isEmailConfirmed', 'emailConfirmationCode']);

        return $this->render("emailConfirmed.html.twig");
    }

    private function buildConnectForm(): FormInterface
    {
        $formBuilder = $this->createFormBuilder()
            ->add('email', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill-field']),
                    new Email(),
                    new Length(['max' => 128]),
                ],
                'label' => false,
            ])
            ->add('fullName', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill-field']),
                    new Length(['max' => 128]),
                ],
                'label' => false,
            ])
            ->add('site', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill-field']),
                    new Length(['max' => 128]),
                    new Regex([
                        'message' => 'invalid-site',
                        'pattern' => '/^.*\..*$/',
                    ]),
                ],
                'label' => false,
            ]);

        $form = $formBuilder->getForm();

        return $form;
    }

    public function addingYandexAccount(Request $request)
    {
        require_once(__DIR__ . '/../PaymentSystemManager/Yandex/external_payment.php');
        require_once(__DIR__ . '/../PaymentSystemManager/Yandex/api.php');

        $clientId = $request->query->get('client_id');
        $code = $request->query->get('code');
        $three = $request->query->get('three');

        $data = ['step' => 'error'];

        if (null !== $clientId) {
            $url = \API::buildObtainTokenUrl(
                $clientId,
                "https://$this->domain/addingYandexAccount",
                ['account-info', 'payment-p2p', 'operation-details']
            );
            $data['step'] = 'getToken';
            $data['url'] = $url;
        }
        if (null !== $code) {
            $data['step'] = 'showForm';
            $data['code'] = $code;
            $data['url'] = "https://$this->domain/addingYandexAccount?three=1";
        }
        if (null !== $three) {
            $clientId = $request->request->get('client_id');
            $instanceId = \ExternalPayment::getInstanceId($clientId);
            $at = $request->request->get('at');
            $secret = $request->request->get('secret');
            $access_token_response = \API::getAccessToken($clientId, $at, "https://$this->domain/", $secret);
            if (property_exists($access_token_response, "error")) {
                $data['step'] = 'error';
            } else {
                $data['step'] = 'result';
                $data['instance_id'] = $instanceId->instance_id;
                $data['client_id'] = $clientId;
                $data['secret'] = $secret;
                $data['token'] = $access_token_response->access_token;
            }
        }

        return $this->render("addingYandexAccount.html.twig", $data);
    }

    public function gamemoneyPayoutStub(Request $request)
    {
        return new JsonResponse(['success' => 'true']);
    }
}
