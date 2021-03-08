<?php namespace App;

use App\Constraints\Accuracy;
use App\Entity\Account;
use App\Entity\Currency;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\PaymentSystem;
use App\Entity\Payout;
use App\Entity\PayoutMethod;
use App\Entity\PayoutSet;
use App\Entity\Shop;
use App\Entity\Statistic;
use App\Entity\SystemAddBalance;
use App\Entity\Ticket;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\AdminApiDataValidationException;
use App\Exception\AdminApiEmbeddedFormValidationException;
use App\Exception\AdminApiException;
use App\Exception\AdminApiRequestException;
use App\Exception\CannotInitPaymentRefundException;
use App\Exception\FormValidationException;
use App\Exception\NotFoundException;
use App\Form\Extension\Core\Type\ApiBooleanType;
use App\PaymentSystemManager\PaymentSystemManagerInterface;
use App\PaymentSystemManager\WhiteBalancingInterface;
use App\Repository\PaymentAccountRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\PaymentRepository;
use App\Repository\PayoutMethodRepository;
use App\Repository\PayoutRepository;
use App\Repository\PayoutSetRepository;
use App\Repository\ShopRepository;
use App\Repository\StatisticRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\TagServiceProvider\TagServiceProvider;
use DateTime;
use DateTimeZone;
use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Exception\NoAffectedRowsException;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use LogicException;
use Monolog\Logger;
use RuntimeException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class AdminApi
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    const DAILY_LIMIT_MAX = 1000000000;//1 kkk

    private $repositoryProvider;
    private $guzzleClient;
    private $adminDomain;
    private $secret;
    private $logger;
    private $translator;
    private $formFactory;
    private $apiViewCompiler;
    private $defaultDbClient;
    private $paymentSystemManagers;
    private $tagServiceProvider;
    private $messageBroker;
    private $feeFetcher;
    private $mailer;
    private $domain;
    private $paymentDayStatisticCounter;
    private $chartDataCompiler;
    private $accountant;
    private $paymentRefunder;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        Client $guzzleClient,
        string $adminDomain,
        string $secret,
        Logger $logger,
        TranslatorInterface $translator,
        FormFactoryInterface $formFactory,
        ApiViewCompiler $apiViewCompiler,
        DbClient $defaultDbClient,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers,
        MessageBroker $messageBroker,
        FeeFetcher $feeFetcher,
        Mailer $mailer,
        string $domain,
        PaymentDayStatisticCounter $paymentDayStatisticCounter,
        ChartDataCompiler $chartDataCompiler,
        Accountant $accountant,
        PaymentRefunder $paymentRefunder
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->guzzleClient = $guzzleClient;
        $this->adminDomain = $adminDomain;
        $this->secret = $secret;
        $this->logger = $logger;
        $this->translator = $translator;
        $this->formFactory = $formFactory;
        $this->apiViewCompiler = $apiViewCompiler;
        $this->defaultDbClient = $defaultDbClient;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->messageBroker = $messageBroker;
        $this->feeFetcher = $feeFetcher;
        $this->mailer = $mailer;
        $this->domain = $domain;
        $this->paymentDayStatisticCounter = $paymentDayStatisticCounter;
        $this->chartDataCompiler = $chartDataCompiler;
        $this->accountant = $accountant;
        $this->paymentRefunder = $paymentRefunder;
    }

    public function syncAccountMethod(ParameterBag $post, ParameterBag $get): array
    {
        /** @var PaymentAccountRepository $paymentAccountRepository */
        $paymentAccountRepository = $this->repositoryProvider->get(PaymentAccount::class);
        /** @var PaymentAccount|null $paymentAccount */
        $paymentAccount = $paymentAccountRepository->findById($post->getInt('id'));
        if (null === $paymentAccount) {
            $paymentAccount = PaymentAccount::create(
                $post->get('paymentSystemId'),
                $post->get('name'),
                json_decode($post->get('config'), true),
                $post->get('weight'),
                explode(',', $post->get('enabled')),
                json_decode($post->get('assignedIds'), true),
                $post->get('isWhite'),
                $post->get('isActive')
            );
            $paymentAccount->id = $post->getInt('id');
            $paymentAccountRepository->create($paymentAccount, false);
        } else {
            $paymentAccount->paymentSystemId = $post->getInt('paymentSystemId');
            $paymentAccount->name = $post->get('name');
            $paymentAccount->config = json_decode($post->get('config'), true);
            $paymentAccount->weight = $post->getInt('weight');
            $paymentAccount->enabled = explode(',', $post->get('enabled'));
            $paymentAccount->assignedIds = json_decode($post->get('assignedIds'), true);
            $paymentAccount->isWhite = $post->getBoolean('isWhite');
            $paymentAccount->isActive = $post->getBoolean('isActive');

            $paymentAccountRepository->update($paymentAccount);
        }

        return [];
    }

    /** @throws NotFoundException */
    public function pushAccountBalancesMethod(ParameterBag $post, ParameterBag $get): array
    {
        /** @var PaymentSystem[] $paymentSystems */
        $paymentSystems = $this->repositoryProvider->get(PaymentSystem::class)->findAll('id');
        /** @var PaymentAccountRepository $paymentAccountRepository */
        $paymentAccountRepository = $this->repositoryProvider->get(PaymentAccount::class);
        $balances = json_decode($post->get('balances'), true);
        $this->defaultDbClient->beginTransaction();
        try {
            $paymentAccountRepository->resetAllBalances();
            foreach ($balances as $item) {
                /** @var PaymentAccount $paymentAccount */
                $paymentAccount = $paymentAccountRepository->findById($item['id']);
                if (null !== $paymentAccount) {
                    //@TODO validate balance
                    $paymentAccount->balance = $item['balance'];
                    $paymentAccountRepository->update($paymentAccount, ['balance']);

                    if (isset($paymentSystems[$paymentAccount->paymentSystemId])) {
                        /** @var PaymentSystemManagerInterface $paymentSystemManager */
                        $paymentSystemManager = $this->tagServiceProvider->get(
                            $this->paymentSystemManagers,
                            $paymentSystems[$paymentAccount->paymentSystemId]->name
                        );
                        if ($paymentSystemManager instanceof WhiteBalancingInterface
                            && !$paymentAccount->isWhite
                            && $paymentAccount->isActive
                            && $paymentSystemManager->isBalanceOverBalancingPoint($paymentAccount)
                        ) {
                            $this->messageBroker->createMessage(
                                MessageBroker::QUEUE_WHITE_BALANCING_NAME,
                                ['id' => $paymentAccount->id],
                                15
                            );
                        }
                    }
                }
            }
            $this->defaultDbClient->commit();
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();
            $this->logger->critical('Cannot update balances', ['error' => $e->getMessage()]);
        }

        return [];
    }

    public function getAccountStatMethod(ParameterBag $post, ParameterBag $get): array
    {
        /** @var PaymentAccountRepository $paymentAccountRepository */
        $paymentAccountRepository = $this->repositoryProvider->get(PaymentAccount::class);

        $data = [
            'using' => $paymentAccountRepository->getUsingStat(),
            'turnover' => $paymentAccountRepository->getTurnover(),
        ];

        return $data;
    }

    /** @throws AdminApiRequestException */
    public function createTicket(Ticket $ticket, string $message): void
    {
        $url = "https://{$this->adminDomain}/api/ticket";
        try {
            $this->guzzleClient->post($url, [
                'timeout' => 5,
                'connect_timeout' => 5,
                'headers' => [
                    'Authorization' => "Bearer $this->secret",
                ],
                'form_params' => [
                    'id' => $ticket->id,
                    'userId' => $ticket->userId,
                    'subject' => $ticket->subject,
                    'message' => $message
                ],
            ]);
        } catch (RequestException $e) {
            $this->logger->critical('Create ticket error', [
                'id' => $ticket->id,
                'url' => $url,
                'message' => $e->getMessage()
            ]);

            throw new AdminApiRequestException();
        }
    }

    public function getTicketMessages(int $ticketId): array
    {
        $url = "https://{$this->adminDomain}/api/ticket/{$ticketId}";
        try {
            $response = $this->guzzleClient->get($url, [
                'timeout' => 5,
                'connect_timeout' => 5,
                'headers' => [
                    'Authorization' => "Bearer $this->secret",
                ],
            ]);
            $messagesVueViews = [];
            $messages = json_decode($response->getBody()->getContents(), true);
            foreach ($messages as $message) {
                $messagesVueViews[] = [
                    'id' => $message['id'],
                    'text' => $message['text'],
                    'author' => $message['answerUserName'] === null
                        ? $this->translator->trans('ticket.message.author-client-name', [], 'admin')
                        : $message['answerUserName'],
                    'createdTs' => $message['createdTs'],
                    'isAnswer' => $message['answerUserName'] === null
                ];
            }

            return $messagesVueViews;
        } catch (RequestException $e) {
            $this->logger->critical('Error retrieving ticket messages', [
                'ticketId' => $ticketId,
                'url' => $url,
                'message' => $e->getMessage()
            ]);

            return [];
        }
    }

    /** @throws AdminApiRequestException */
    public function createMessage(string $ticketId, string $text): void
    {
        $url = "https://{$this->adminDomain}/api/ticket/{$ticketId}/message";
        try {
            $this->guzzleClient->post($url, [
                'timeout' => 5,
                'connect_timeout' => 5,
                'headers' => [
                    'Authorization' => "Bearer $this->secret",
                ],
                'form_params' => [
                    'text' => $text,
                ],
            ]);
        } catch (RequestException $e) {
            $this->logger->critical('Create message error', [
                'ticketId' => $ticketId,
                'url' => $url,
                'message' => $e->getMessage()
            ]);

            throw new AdminApiRequestException();
        }
    }

    public function newMessageMethod(ParameterBag $post, ParameterBag $get): array
    {
        $ticketId = $post->get('ticketId');
        /** @var Ticket $ticket */
        $ticket = $this->repositoryProvider->get(Ticket::class)->findOneBy(['id' => $ticketId]);
        if ($ticket === null) {
            throw new LogicException("Ticket with id $ticketId does not exist");
        }
        /** @var User $user */
        $user = $this->repositoryProvider->get(User::class)->findById($ticket->userId);
        $ticket->lastMessageTs = new DateTime();
        if ($ticket->hasUnreadMessage === false) {
            $ticket->hasUnreadMessage = true;
            $link = "https://{$this->domain}/private#/ticket/{$ticket->id}";
            $this->mailer->createForUser(
                $user->id,
                Mailer::LETTER_NAME_TICKET_ANSWER,
                ['link' => $link]
            );
        }
        $this->repositoryProvider->get(Ticket::class)->update($ticket);

        return [];
    }

    /** @throws AdminApiRequestException */
    public function shopToChecking(int $shopId): void
    {
        $url = "https://{$this->adminDomain}/api/shop/{$shopId}/toChecking";
        try {
            $this->guzzleClient->post($url, [
                'timeout' => 5,
                'connect_timeout' => 5,
                'headers' => [
                    'Authorization' => "Bearer $this->secret",
                ],
            ]);
        } catch (RequestException $e) {
            $this->logger->critical('Shop to checking error', [
                'shopId' => $shopId,
                'url' => $url,
                'message' => $e->getMessage()
            ]);

            throw new AdminApiRequestException();
        }
    }

    /** @throws NotFoundException */
    public function shopMethod(int $shopId, ParameterBag $post, ParameterBag $get): array
    {
        /** @var Shop|null $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findOneBy(['id' => $shopId]);
        if (null === $shop) {
            throw new NotFoundException();
        }
        /** @var PaymentMethodRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->repositoryProvider->get(PaymentMethod::class);
        $paymentMethods = $paymentMethodRepository->findAll();
        $paymentMethodsDayStat = $paymentMethodRepository->countDayStatByShop($shopId);
        $paymentDayStatistic = $this->paymentDayStatisticCounter->getPaymentDayStatisticByShopIdForToday($shop->id);
        $currencyName = Currency::NAME_RUB;
        $view = $shop->compileAdminApiPageView(
            $paymentMethods,
            $paymentMethodsDayStat,
            $this->translator,
            $this->feeFetcher,
            $paymentDayStatistic,
            $currencyName
        );

        return $view;
    }

    /**
     * @throws NotFoundException
     * @throws AdminApiDataValidationException
     */
    public function shopPersonalPaymentMethodSettingsMethod(int $shopId, ParameterBag $post, ParameterBag $get): array
    {
        $shopRepository = $this->repositoryProvider->get(Shop::class);
        /** @var Shop|null $shop */
        $shop = $shopRepository->findOneBy(['id' => $shopId]);
        if ($shop === null) {
            throw new NotFoundException();
        }
        $formBuilder = $this->formFactory->createBuilder()
            ->add('isEnabled', ApiBooleanType::class)
            ->add('hasPersonalFee', ApiBooleanType::class)
            ->add('fee', PercentType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill field']),
                    new Accuracy(2),
                    new GreaterThanOrEqual(0),
                ],
                'type' => 'integer',
            ])
            ->add('paymentMethodId', IntegerType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill field']),
                ]
            ]);
        $form = $formBuilder->getForm();
        $data = $post->get('form');
        $form->submit($data);

        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $formData = $form->getData();
            /** @var PaymentMethod|null $paymentMethod */
            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById(
                $formData['paymentMethodId']
            );
            if ($paymentMethod === null) {
                throw new NotFoundException();
            }
            $excludedMethodsByAdmin = $shop->excludedMethodsByAdmin;
            $paymentMethodIdIndex = array_search($paymentMethod->id, $excludedMethodsByAdmin, true);
            if ($formData['isEnabled']) {
                if ($paymentMethodIdIndex !== false) {
                    unset($excludedMethodsByAdmin[$paymentMethodIdIndex]);
                }
            } else {
                if ($paymentMethodIdIndex === false) {
                    $excludedMethodsByAdmin[] = $paymentMethod->id;
                }
            }
            $shop->excludedMethodsByAdmin = array_values($excludedMethodsByAdmin);
            $personalPaymentFees = $shop->personalPaymentFees;
            if ($formData['hasPersonalFee']) {
                $personalPaymentFees[$paymentMethod->id] = $formData['fee'];
            } else {
                unset($personalPaymentFees[$paymentMethod->id]);
            }
            $shop->personalPaymentFees = $personalPaymentFees;
            $shopRepository->update($shop);

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    /**
     * @throws NotFoundException
     * @throws AdminApiDataValidationException
     */
    public function userPersonalPayoutMethodSettingsMethod(int $userId, ParameterBag $post, ParameterBag $get): array
    {
        $userRepository = $this->repositoryProvider->get(User::class);
        /** @var User|null $user */
        $user = $userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException();
        }
        $formBuilder = $this->formFactory->createBuilder()
            ->add('isEnabled', ApiBooleanType::class)
            ->add('hasPersonalFee', ApiBooleanType::class)
            ->add('fee', PercentType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill field']),
                    new Accuracy(2),
                    new GreaterThanOrEqual(0),
                ],
                'type' => 'integer',
            ])
            ->add('payoutMethodId', IntegerType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill field']),
                ]
            ]);
        $form = $formBuilder->getForm();
        $data = $post->get('form');
        $form->submit($data);

        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $formData = $form->getData();
            /** @var PayoutMethod|null $payoutMethod */
            $payoutMethod = $this->repositoryProvider->get(PayoutMethod::class)->findById(
                $formData['payoutMethodId']
            );
            if ($payoutMethod === null) {
                throw new NotFoundException();
            }
            $excludedPayoutMethods = $user->excludedPayoutMethods;
            $payoutMethodIdIndex = array_search($payoutMethod->id, $excludedPayoutMethods, true);
            if ($formData['isEnabled']) {
                if ($payoutMethodIdIndex !== false) {
                    unset($excludedPayoutMethods[$payoutMethodIdIndex]);
                }
            } else {
                if ($payoutMethodIdIndex === false) {
                    $excludedPayoutMethods[] = $payoutMethod->id;
                }
            }
            $user->excludedPayoutMethods = array_values($excludedPayoutMethods);
            $personalPayoutFees = $user->personalPayoutFees;
            if ($formData['hasPersonalFee']) {
                $personalPayoutFees[$payoutMethod->id] = $formData['fee'];
            } else {
                unset($personalPayoutFees[$payoutMethod->id]);
            }
            $user->personalPayoutFees = $personalPayoutFees;
            $userRepository->update($user);

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    /**
     * @throws NotFoundException
     * @throws AdminApiException
     */
    public function changeShopStatusMethod(int $shopId, ParameterBag $post, ParameterBag $get): array
    {
        $shopRepository = $this->repositoryProvider->get(Shop::class);
        /** @var Shop|null $shop */
        $shop = $shopRepository->findOneBy(['id' => $shopId]);
        if ($shop === null) {
            throw new NotFoundException();
        }
        $status = $post->getInt('status');
        $considerationStatuses = Shop::CONSIDERATION_STATUSES;
        if (!in_array($status, $considerationStatuses, true)) {
            throw new AdminApiException();
        }
        $shop->statusId = $status;
        $shopRepository->update($shop);

        return [];
    }

    public function findMethod(ParameterBag $post, ParameterBag $get): array
    {
        $entityClasses = [
            User::class,
            Shop::class,
            Payment::class,
            Ticket::class,
            PaymentShot::class,
        ];
        $entityViews = [];
        $usersPaymentsViews = [];
        $query = $post->get('query');
        $filteredId = filter_var($query, FILTER_VALIDATE_INT) ?: null;
        if ($filteredId === null) {
            /** @var UserRepository $userRepository */
            $userRepository = $this->repositoryProvider->get(User::class);
            /** @var PaymentRepository $paymentRepository */
            $paymentRepository = $this->repositoryProvider->get(Payment::class);
            $users = $userRepository->findByEmailLikeLimited($query);
            $payments = $paymentRepository->findByEmailLikeLimited($query);
            /** @var User $user */
            foreach ($users as $user) {
                $userView = $user->compileAdminApiFinderView();
                $shortClassName = $this->getShortClassName(User::class);
                $usersPaymentsViews = $this->addEntityFindView($usersPaymentsViews, $shortClassName, $userView);
            }
            /** @var Payment $payment */
            foreach ($payments as $payment) {
                /** @var Currency $currency */
                $currency = $this->repositoryProvider->get(Currency::class)->findById(
                    $payment->currency
                );
                $paymentView = $payment->compileAdminApiFinderView($currency);
                $shortClassName = $this->getShortClassName(Payment::class);
                $usersPaymentsViews = $this->addEntityFindView($usersPaymentsViews, $shortClassName, $paymentView);
            }

            return $usersPaymentsViews;
        } else {
            foreach ($entityClasses as $entityClass) {
                $entities = [];
                $entities[] = $this->repositoryProvider->get($entityClass)->findById($filteredId);
                if ($entityClass === Payment::class) {
                    $payments = $this->repositoryProvider->get($entityClass)->findBy(['payment' => $filteredId]);
                    $entities = array_unique(array_merge($entities, $payments), SORT_REGULAR);
                }
                foreach ($entities as $entity) {
                    if ($entity !== null) {
                        $shortClassName = $this->getShortClassName($entityClass);
                        if ($entityClass === PaymentShot::class) {
                            /** @var Payment $payment */
                            $payment = $this->repositoryProvider->get(Payment::class)->findById($entity->paymentId);
                            if ($payment->id !== $filteredId) {
                                /** @var Currency $currency */
                                $currency = $this->repositoryProvider->get(Currency::class)->findById(
                                    $payment->currency
                                );
                                $entityView = $payment->compileAdminApiFinderView($currency);
                                $shortClassName = $this->getShortClassName(Payment::class);
                            } else {
                                continue;
                            }
                        } elseif ($entityClass === Payment::class) {
                            /** @var Currency $currency */
                            $currency = $this->repositoryProvider->get(Currency::class)->findById($entity->currency);
                            $entityView = $entity->compileAdminApiFinderView($currency);
                        } elseif ($entityClass === Shop::class) {
                            $entityView = $entity->compileAdminApiFinderView($this->translator);
                        } else {
                            $entityView = $entity->compileAdminApiFinderView();
                        }
                        $entityViews = $this->addEntityFindView($entityViews, $shortClassName, $entityView);
                    }
                }
            }

            return $entityViews;
        }
    }

    public function queuesMethod(ParameterBag $post, ParameterBag $get): array
    {
        $queueViews = [];
        $queueNames = MessageBroker::QUEUE_NAMES;
        foreach ($queueNames as $queueName) {
            $queueViews[] = $this->messageBroker->getQueueInfo($queueName);
        }

        return $queueViews;
    }

    private function addEntityFindView(array $entityViews, string $shortClassName, array $entityView): array
    {
        $entityViews[$shortClassName]['views'][] = $entityView;

        return $entityViews;
    }

    private function getShortClassName(string $className): string
    {
        $shortClassName = substr(strrchr($className, "\\"), 1);


        return $shortClassName;
    }

    /** @throws NotFoundException */
    public function userMethod(int $userId, ParameterBag $get): array
    {
        /** @var User $user */
        $user = $this->repositoryProvider->get(User::class)->findOneBy(['id' => $userId]);
        if ($user === null) {
            throw new NotFoundException();
        }
        $shops = $this->repositoryProvider->get(Shop::class)->findBy(['userId' => $userId]);
        $accounts = $this->repositoryProvider->get(Account::class)->findBy(['userId' => $userId]);
        $currencies = $this->repositoryProvider->get(Currency::class)->findByRelativeIndexed($accounts);
        $payoutMethods = $this->repositoryProvider->get(PayoutMethod::class)->findAll();
        $view = $user->compileAdminApiView($shops, $accounts, $currencies, $payoutMethods, $this->feeFetcher);

        return $view;
    }

    /** @throws NotFoundException */
    public function paymentMethod(int $paymentId, ParameterBag $get): array
    {
        /** @var Payment|null $payment */
        $payment = $this->repositoryProvider->get(Payment::class)->findById($paymentId);
        if ($payment === null) {
            throw new NotFoundException();
        }
        /** @var Shop $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);
        /** @var Currency $currency */
        $currency = $this->repositoryProvider->get(Currency::class)->findById($payment->currency);
        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById($payment->paymentMethodId);
        /** @var Notification[] $notifications */
        $notifications = $this->repositoryProvider->get(Notification::class)->findBy(['paymentId' => $payment->id]);
        $user = $this->repositoryProvider->get(User::class)->findById($shop->userId);
        $view = $payment
            ->compileAdminApiView($this->translator, $currency, $shop, $paymentMethod, $notifications, $user);

        return $view;
    }

    /**
     * @throws NotFoundException
     * @throws AdminApiRequestException
     */
    public function paymentRefundMethod(int $paymentId): array
    {
        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->repositoryProvider->get(Payment::class);
        /** @var Payment|null $payment */
        $payment = $paymentRepository->findById($paymentId);
        if ($payment === null) {
            throw new NotFoundException();
        }

        try {
            $this->paymentRefunder->initRefund($payment);
        } catch (CannotInitPaymentRefundException $e) {
            throw new AdminApiRequestException();
        }

        return [];
    }

    /** @throws AdminApiRequestException */
    public function paymentAccountBalances(): array
    {
        try {
            $balances = $this->request('get', '/paymentAccount/balances');

            return $balances;
        } catch (AdminApiRequestException $e) {
            $this->logger->critical('Get payment account balances error', [
                'message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function paymentMethodsMethod(ParameterBag $post, ParameterBag $get): array
    {
        $views = [];
        $paymentMethods = $this->repositoryProvider->get(PaymentMethod::class)->findAll();
        $paymentSystems = $this->repositoryProvider->get(PaymentSystem::class)->findByRelativeIndexed($paymentMethods);
        $currencies = $this->repositoryProvider->get(Currency::class)->findByRelativeIndexed($paymentMethods);
        /** @var PaymentMethod $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            $paymentSystem = $paymentSystems[$paymentMethod->paymentSystemId];
            $currency = $currencies[$paymentMethod->currencyId];
            $views[] = $paymentMethod->compileAdminApiView($paymentSystem, $currency);
        }

        return $views;
    }

    /**
     * @throws NotFoundException
     * @throws AdminApiDataValidationException
     */
    public function paymentMethodSettingsMethod(ParameterBag $post, ParameterBag $get): array
    {
        $formBuilder = $this->formFactory->createBuilder()
            ->add('fee', PercentType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill field']),
                    new Accuracy(2),
                ],
                'type' => 'integer',
            ])
            ->add('isEnabled', CheckboxType::class)
            ->add('paymentMethodId', IntegerType::class, ['constraints' => [
                new NotBlank(['message' => 'fill field']),
            ]]);

        $form = $formBuilder->getForm();
        $formData = $post->get('form');
        if ($formData['isEnabled'] === '0') {
            $formData['isEnabled'] = null;
        }
        $form->submit($formData);
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $formData = $form->getData();
            /** @var PaymentMethod|null $paymentMethod */
            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById(
                $formData['paymentMethodId']
            );
            if ($paymentMethod === null) {
                throw new NotFoundException();
            }
            $paymentMethod->fee = $formData['fee'];
            $paymentMethod->enabled = $formData['isEnabled'];
            $this->repositoryProvider->get(PaymentMethod::class)->update($paymentMethod);

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    public function payoutMethodsMethod(ParameterBag $post, ParameterBag $get): array
    {
        $views = [];
        $payoutMethods = $this->repositoryProvider->get(PayoutMethod::class)->findAll();
        $paymentSystems = $this->repositoryProvider->get(PaymentSystem::class)->findByRelativeIndexed($payoutMethods);
        $currencies = $this->repositoryProvider->get(Currency::class)->findByRelativeIndexed($payoutMethods);
        /** @var PayoutMethod $payoutMethod */
        foreach ($payoutMethods as $payoutMethod) {
            $paymentSystem = $paymentSystems[$payoutMethod->paymentSystemId];
            $currency = $currencies[$payoutMethod->currencyId];
            $views[] = $payoutMethod->compileAdminApiView($paymentSystem, $currency);
        }

        return $views;
    }

    public function payoutMethodsWaitingMethod(ParameterBag $post, ParameterBag $get): array
    {
        /** @var PayoutMethodRepository $payoutMethodRepository */
        $payoutMethodRepository = $this->repositoryProvider->get(PayoutMethod::class);
        $payoutMethodsWithWaitingViewsIndexedByMethod = $payoutMethodRepository->findWithWaitingIndexedByMethod();

        return $payoutMethodsWithWaitingViewsIndexedByMethod;
    }

    /**
     * @throws NotFoundException
     * @throws AdminApiDataValidationException
     */
    public function payoutMethodSettingsMethod(ParameterBag $post, ParameterBag $get): array
    {
        $formBuilder = $this->formFactory->createBuilder()
            ->add('fee', PercentType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill field']),
                    new Accuracy(2),
                ],
                'type' => 'integer',
            ])
            ->add('isEnabled', CheckboxType::class)
            ->add('isDefaultExcluded', CheckboxType::class)
            ->add('payoutMethodId', IntegerType::class, ['constraints' => [
                new NotBlank(['message' => 'fill field']),
            ]]);
        $form = $formBuilder->getForm();
        $formData = $post->get('form');
        if ($formData['isEnabled'] === '0') {
            $formData['isEnabled'] = null;
        }
        if ($formData['isDefaultExcluded'] === '0') {
            $formData['isDefaultExcluded'] = null;
        }
        $form->submit($formData);
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $formData = $form->getData();
            /** @var PayoutMethod|null $payoutMethod */
            $payoutMethod = $this->repositoryProvider->get(PayoutMethod::class)->findById(
                $formData['payoutMethodId']
            );
            if ($payoutMethod === null) {
                throw new NotFoundException();
            }
            $payoutMethod->fee = $formData['fee'];
            $payoutMethod->isEnabled = $formData['isEnabled'];
            $payoutMethod->defaultExcluded = $formData['isDefaultExcluded'];
            $this->repositoryProvider->get(PayoutMethod::class)->update($payoutMethod);

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    /**
     * @throws NotFoundException
     * @throws AdminApiDataValidationException
     */
    public function changeUserStatusMethod(int $userId, ParameterBag $post, ParameterBag $get): array
    {
        $userRepository = $this->repositoryProvider->get(User::class);
        /** @var User|null $user */
        $user = $userRepository->findById($userId);
        if ($user === null) {
            throw new NotFoundException();
        }
        $formBuilder = $this->formFactory->createBuilder()
            ->add('isBlocked', ApiBooleanType::class);
        $form = $formBuilder->getForm();
        $data = $post->get('form');
        $form->submit($data);

        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $formData = $form->getData();
            $user->isBlocked = $formData['isBlocked'];
            $userRepository->update($user, ['isBlocked']);

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    /**
     * @throws AdminApiDataValidationException
     * @throws NotFoundException
     */
    public function shopDailyLimitMethod(int $shopId, ParameterBag $post, ParameterBag $get): array
    {
        $formBuilder = $this->formFactory->createBuilder()
            ->add('dailyLimit', PercentType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill field']),
                    new Accuracy(2),
                    new GreaterThanOrEqual(0),
                    new LessThanOrEqual(self::DAILY_LIMIT_MAX),
                ],
                'type' => 'integer',
            ]);
        $form = $formBuilder->getForm();
        $formData = $post->get('form');
        $form->submit($formData);
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $formData = $form->getData();
            $shopRepository = $this->repositoryProvider->get(Shop::class);
            /** @var Shop $shop */
            $shop = $shopRepository->findById($shopId);
            if ($shop === null) {
                throw new NotFoundException();
            }
            $shop->paymentDayLimit = $formData['dailyLimit'];
            $shopRepository->update($shop, ['paymentDayLimit']);

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    public function statisticMethod(ParameterBag $post, ParameterBag $get): array
    {
        $dateTime = new DateTime();
        $timezoneOffset = $dateTime->format('P');
        $userTimezone = new DateTimeZone($timezoneOffset);
        $lastTwelveMonthsDataRange = $this->chartDataCompiler->generateDataRangeWithZerosFormatted(
            ChartDataCompiler::INTERVAL_MONTH,
            $userTimezone
        );
        $lastThirtyDaysDataRange = $this->chartDataCompiler->generateDataRangeWithZerosFormatted(
            ChartDataCompiler::INTERVAL_DAY,
            $userTimezone
        );
        /** @var StatisticRepository $statisticRepository */
        $statisticRepository = $this->repositoryProvider->get(Statistic::class);
        //@TODO cache
        $resultByMonths = $statisticRepository->findForChartByMonthsOrDays(true);
        $resultByDays = $statisticRepository->findForChartByMonthsOrDays();
        $data = $this->chartDataCompiler->compileView(
            $resultByMonths,
            $lastTwelveMonthsDataRange,
            $resultByDays,
            $lastThirtyDaysDataRange,
            ['amount', 'pointAmount']
        );

        return $data;
    }


    /**
     * @throws NotFoundException
     */
    public function shopStatisticsMethod(int $shopId, ParameterBag $post, ParameterBag $get): array
    {
        /** @var Shop|null $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($shopId);
        if (null === $shop) {
            throw new NotFoundException();
        }
        $user = $this->repositoryProvider->get(User::class)->findById($shop->userId);
        $data = $this->chartDataCompiler->compilePaymentChartData($user, $shopId);

        return $data;
    }

    /**
     * @throws AdminApiDataValidationException
     * @throws AdminApiEmbeddedFormValidationException
     * @throws Exception
     */
    public function listingAddMethod(ParameterBag $post, ParameterBag $get): array
    {
        $queryFormBuilder = $this->formFactory->createBuilder()
            ->add('listing', TextType::class, ['constraints' => [
                new NotBlank(),
            ]]);
        $queryForm = $queryFormBuilder->getForm();
        $queryForm->submit($get->all());
        try {
            if (!$queryForm->isValid()) {
                throw new FormValidationException();
            }
            $queryFormData = $queryForm->getData();
            if ($queryFormData['listing'] === 'account') {
                $form = $this->getSystemAddBalanceForm();
                $form->submit($post->get('form'));
                if (!$form->isValid()) {
                    throw new AdminApiEmbeddedFormValidationException($form->getErrors(true));
                }
                $formData = $form->getData();
                /** @var Account|null $account */
                $account = $this->repositoryProvider->get(Account::class)->findById((int) $formData['accountId']);
                if (null === $account) {//@todo Form error
                    throw new AdminApiDataValidationException();
                }

                $this->accountant->systemAdd($account, $formData['amount'], $formData['comment']);
            } else {
                throw new AdminApiDataValidationException();
            }

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    /** @throws AdminApiDataValidationException */
    public function listingDataMethod(ParameterBag $post, ParameterBag $get): array
    {
        //@TODO REFACTORING
        $queryFormBuilder = $this->formFactory->createBuilder()
            ->add('listing', TextType::class, ['constraints' => [
                new NotBlank(),
            ]])
            ->add('rowsPerPage', IntegerType::class, ['constraints' => [
                new NotBlank(),
            ]])
            ->add('pageId', IntegerType::class, ['constraints' => [
                new NotBlank(),
            ]]);
        $queryForm = $queryFormBuilder->getForm();
        $queryForm->submit($get->all());
        try {
            if (!$queryForm->isValid()) {
                throw new FormValidationException($queryForm);
            }
            $queryFormData = $queryForm->getData();

            $add = null;
            $views = [];
            if ($queryFormData['listing'] === 'shops') {
                $headers = [
                    ['type' => 'text', 'text' => '#', 'value' => 'id', 'sortable' => false],
                    ['type' => 'url', 'text' => 'URL', 'value' => 'url', 'sortable' => false],
                    ['type' => 'text', 'text' => 'User ID', 'value' => 'userId', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Day Limit', 'value' => 'limit', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Status', 'value' => 'status', 'sortable' => false],
                    ['type' => 'actions', 'value' => 'actions', 'sortable' => false],
                ];
                /** @var ShopRepository $shopRepository */
                $shopRepository = $this->repositoryProvider->get(Shop::class);
                $items = $shopRepository
                    ->findAllForAdminApiWithPagination($queryFormData['pageId'], $queryFormData['rowsPerPage']);
                $total = $shopRepository->getFoundRows();
                $currencyId = Currency::CURRENCY_RUB_ID;
                foreach ($items as $item) {
                    $limit = sprintf(
                        '%s / %s %s',
                        $this->formatMoney($item['paymentDayLimitAmount'] ?? 0),
                        $this->formatMoney($item['paymentDayLimit']),
                        $this->translator->trans("currency.{$currencyId}.sign", [], 'payment')
                    );
                    $actions = [
                        ['icon' => 'store', 'type' => 'entity', 'entity' => 'shop', 'entityId' => $item['id']],
                        ['icon' => 'wheelchair-accessibility', 'type' => 'entity', 'entity' => 'user', 'entityId' => $item['userId']],
                    ];
                    $views[] = [
                        'id' => $item['id'],
                        'url' => $item['url'],
                        'userId' => $item['userId'],
                        'limit' => $limit,
                        'status' => $this->translator->trans("shop.status.{$item['statusId']}", [], 'admin'),
                        'actions' => $actions,
                    ];
                }
            } elseif ($queryFormData['listing'] === 'payouts') {
                $headers = [
                    ['type' => 'text', 'text' => '#', 'value' => 'id', 'sortable' => false],
                    ['type' => 'text', 'text' => 'User Id', 'value' => 'userId', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Internal Id', 'value' => 'internalUsersId', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Method', 'value' => 'payoutMethodName', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Receiver', 'value' => 'receiver', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Transferred/Total', 'value' => 'amount', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Fee', 'value' => 'fee', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Parts', 'tooltip' => 'Success/Processed/Total', 'value' => 'chunks', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Status', 'value' => 'status', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Created', 'value' => 'created', 'sortable' => false],
                    ['type' => 'actions', 'value' => 'actions', 'sortable' => false],
                ];
                /** @var PayoutSetRepository $payoutSetRepository */
                $payoutSetRepository = $this->repositoryProvider->get(PayoutSet::class);
                /** @var PayoutSet[] $payoutSets */
                $payoutSets = $payoutSetRepository
                    ->findByFilterWithPaginationOrderByStatus($queryFormData['pageId'], $queryFormData['rowsPerPage']);
                $total = $payoutSetRepository->getFoundRows();
                /** @var Account[] $userAccountIndexedById */
                $userAccountIndexedById = $this->repositoryProvider->get(Account::class)
                    ->findByRelativeIndexed($payoutSets);
                /** @var PayoutMethod[] $payoutMethods */
                $payoutMethods = $this->repositoryProvider->get(PayoutMethod::class)->findAll('id');
                foreach ($payoutSets as $payoutSet) {
                    $views[] = $payoutSet
                        ->compileAdminApiView($this->translator, $userAccountIndexedById, $payoutMethods);
                }
            } elseif ($queryFormData['listing'] === 'account') {
                $headers = [
                    ['type' => 'text', 'text' => '#', 'value' => 'id', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Method', 'value' => 'method', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Method ID', 'value' => 'methodId', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Amount', 'value' => 'amount', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Balance', 'value' => 'balance', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Comment', 'value' => 'comment', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Created', 'value' => 'created', 'sortable' => false],
                    ['type' => 'actions', 'value' => 'actions', 'sortable' => false],
                ];
                $formBuilder = $this->formFactory->createBuilder()
                    ->add('accountId', IntegerType::class, ['constraints' => [
                        new NotBlank(),
                    ]]);
                $form = $formBuilder->getForm();
                $form->submit($post->get('form'));
                if (!$form->isValid()) {
                    throw new FormValidationException($form);
                }
                $formData = $form->getData();
                /** @var Account|null $account */
                $account = $this->repositoryProvider->get(Account::class)->findById((int) $formData['accountId']);
                if (null === $account) {
                    throw new AdminApiDataValidationException();
                }
                /** @var TransactionRepository $transactionRepository */
                $transactionRepository = $this->repositoryProvider->get(Transaction::class);
                $transactions = $transactionRepository->findByUserAndCurrencyWithPagination(
                    $account->userId,
                    $account->currencyId,
                    $queryFormData['pageId'],
                    $queryFormData['rowsPerPage']
                );
                $total = $transactionRepository->getFoundRows();
                foreach ($transactions as $transaction) {
                    $currencySign = $this->translator->trans("currency.$transaction->currencyId.sign", [], 'payment');
                    $balance = $transaction->balance !== null
                        ? $this->formatMoney($transaction->balance).$currencySign
                        : '';
                    $userId = $transaction->userId;
                    $actions = [
                        ['icon' => 'wheelchair-accessibility', 'type' => 'entity', 'entity' => 'user', 'entityId' => $userId],
                    ];
                    $comment = '';
                    if ($transaction->method === Accountant::METHOD_SYSTEM) {
                        /** @var SystemAddBalance $systemAddBalance */
                        $systemAddBalance = $this->repositoryProvider->get(SystemAddBalance::class)
                            ->findById($transaction->methodId);
                        $comment = $systemAddBalance->comment;
                    }
                    $views[] = [
                        'id' => $transaction->id,
                        'method' => $transaction->method,
                        'methodId' => $transaction->methodId,
                        'amount' => $this->formatMoney($transaction->amount).$currencySign,
                        'balance' => $balance,
                        'comment' => $comment,
                        'created' => $transaction->createdTs->format(VueViewCompiler::TIMEZONEJS_DATE_FORMAT),
                        'actions' => $actions,
                    ];
                }
                $addForm = $this->getSystemAddBalanceForm($account);
                $addFormFields = [];
                foreach ($addForm->all() as $field) {
                    $config = $field->getConfig();
                    $addFormFields[] = [
                        'name' => $config->getName(),
                        'type' => $config->getType()->getBlockPrefix(),
                        'default' => $config->getData()
                    ];
                }
                $add = [
                    'fields' => $addFormFields
                ];
            } elseif ($queryFormData['listing'] === 'payment') {
                $headers = [
                    ['type' => 'text', 'text' => '#', 'value' => 'id', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Description', 'value' => 'description', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Amount', 'value' => 'amount', 'sortable' => false],
                    ['type' => 'text', 'text' => 'Created', 'value' => 'created', 'sortable' => false],
                    ['type' => 'actions', 'value' => 'actions', 'sortable' => false],
                ];
                $formBuilder = $this->formFactory->createBuilder()
                    ->add('shopId', IntegerType::class, ['constraints' => [
                        new NotBlank(),
                    ]]);
                $form = $formBuilder->getForm();
                $form->submit($post->get('form'));
                if (!$form->isValid()) {
                    throw new FormValidationException();
                }
                $formData = $form->getData();
                /** @var Shop|null $shop */
                $shop = $this->repositoryProvider->get(Shop::class)->findById((int) $formData['shopId']);
                if (null === $shop) {
                    throw new AdminApiDataValidationException();
                }
                $filters = [
                    'statusId' => Payment::STATUS_ID_SUCCESS,
                    'shopId' => $shop->id,
                ];
                /** @var PaymentRepository $paymentRepository */
                $paymentRepository = $this->repositoryProvider->get(Payment::class);
                $payments = $paymentRepository
                    ->findByFilterWithPagination($queryFormData['pageId'], $queryFormData['rowsPerPage'], $filters);
                $total = $paymentRepository->getFoundRows();
                foreach ($payments as $payment) {
                    $views[] = $payment->compileAdminApiListView();
                }
            } else {
                throw new AdminApiDataValidationException();
            }

            return [
                'headers' => $headers,
                'items' => $views,
                'total' => $total,
                'add' => $add,
            ];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException($e->getErrors());
        }
    }

    public function deactivatePaymentAccount(int $accountId): void
    {
        try {
            $this->request('get', "/paymentAccount/{$accountId}/deactivate");
        } catch (AdminApiRequestException $e) {
            $this->logger->critical('Deactivate payment account request failed', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * @throws AdminApiDataValidationException
     * @throws NotFoundException
     */
    public function changeEmailMethod(int $paymentId, ParameterBag $post, ParameterBag $get): array
    {
        $formBuilder = $this->formFactory->createBuilder()
            ->add('email', TextType::class, ['constraints' => [new Email(), new NotBlank()]]);
        $form = $formBuilder->getForm();
        $formData = $post->get('form');
        $form->submit($formData);
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $formData = $form->getData();
            /** @var PaymentRepository $paymentRepository */
            $paymentRepository = $this->repositoryProvider->get(Payment::class);
            /** @var Payment|null $payment */
            $payment = $paymentRepository->findById($paymentId);
            if ($payment === null) {
                throw new NotFoundException();
            }
            $payment->email = $formData['email'];
            $paymentRepository->update($payment, ['email']);

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    /**
     * @throws AdminApiDataValidationException
     * @throws NotFoundException
     */
    public function shopDomainSchemeChangeMethod(int $shopId, ParameterBag $post, ParameterBag $get): array
    {
        $formBuilder = $this->formFactory->createBuilder()
            ->add('scheme', ChoiceType::class, [
                'choices' => ['http', 'https'],
                'constraints' => [
                    new NotBlank(['message' => 'fill field']),
                ],
            ])
            ->add('domain', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill field']),
                    new Regex(
                        [
                            'pattern' => '/^(?!\-)(?:[a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/',
                            'message' => 'This value is not a valid Domain.',
                        ]
                    ),
                ],
            ]);
        $form = $formBuilder->getForm();
        $formData = $post->get('form');
        $form->submit($formData);
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $formData = $form->getData();
            /** @var ShopRepository $paymentRepository */
            $shopRepository = $this->repositoryProvider->get(Shop::class);
            /** @var Shop|null $shop */
            $shop = $shopRepository->findById($shopId);
            if ($shop === null) {
                throw new NotFoundException();
            }
            $shop->changeDomainSchemeAllUrls($formData['domain'], $formData['scheme']);
            $shopRepository->update($shop, ['url', 'resultUrl',  'successUrl', 'failUrl']);

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    /**
     * @throws NotFoundException
     */
    public function paymentShotsMethod(int $paymentId, ParameterBag $post, ParameterBag $get): array
    {
        /** @var Payment|null $payment */
        $payment = $this->repositoryProvider->get(Payment::class)->findById($paymentId);
        if (null === $payment) {
            throw new NotFoundException();
        }
        /** @var PaymentShot[] $paymentShots */
        $paymentShots = $this->repositoryProvider->get(PaymentShot::class)->findBy(['paymentId' => $paymentId]);
        $paymentMethods = $this->repositoryProvider->get(PaymentMethod::class)->findByRelativeIndexed($paymentShots);
        $views = [];
        foreach ($paymentShots as $paymentShot) {
            $views[] = $paymentShot->compileAdminApiPaymentPageView(
                $paymentMethods[$paymentShot->paymentMethodId]
            );
        }

        return $views;
    }

    /** @throws AdminApiRequestException */
    private function request(string $method, string $urlPart, array $params = []): array
    {
        $url = "https://{$this->adminDomain}/api{$urlPart}";
        try {
            $request = $this->guzzleClient->request($method, $url, [
                'timeout' => 5,
                'connect_timeout' => 5,
                'headers' => [
                    'Authorization' => "Bearer $this->secret",
                ],
                'form_params' => $params,
            ]);
            $result = json_decode($request->getBody()->getContents(), true);

            return $result;
        } catch (RequestException $e) {
            throw new AdminApiRequestException($e->getMessage());
        }
    }

    /**
     * @throws AdminApiDataValidationException
     * @throws NotFoundException
     * @throws AdminApiException
     */
    public function executePaymentMethod(int $paymentId, ParameterBag $post, ParameterBag $get): array
    {
        $formBuilder = $this->formFactory->createBuilder()
            ->add('paymentShotId', IntegerType::class, ['constraints' => [new NotNull()]]);
        $form = $formBuilder->getForm();
        $formData = $post->get('form');
        $form->submit($formData);
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $formData = $form->getData();
            /** @var Payment $payment */
            $payment = $this->repositoryProvider->get(Payment::class)->findById($paymentId);
            if ($payment === null) {
                throw new NotFoundException();
            }
            if ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
                throw new AdminApiException();
            }
            $paymentShot = $this->repositoryProvider->get(PaymentShot::class)->findOneBy([
                'id' => $formData['paymentShotId'],
                'paymentId' => $paymentId,
            ]);
            if ($paymentShot === null) {
                throw new NotFoundException();
            }
            $this->messageBroker->createMessage(MessageBroker::QUEUE_EXEC_PAYMENT_NAME, [
                'paymentShotId' => $paymentShot->id
            ]);

            return [];
        } catch (FormValidationException $e) {
            throw new AdminApiDataValidationException();
        }
    }

    private function formatMoney($value)
    {
        return number_format($value, 2, '.', ',');
    }

    private function getSystemAddBalanceForm(Account $account = null): FormInterface
    {
        $formBuilder = $this->formFactory->createBuilder()
            ->add('accountId', HiddenType::class, ['constraints' => [
                new NotBlank(),
            ], 'data' => $account->id ?? null])
            ->add('amount', NumberType::class, ['constraints' => [
                new NotBlank(),
                new GreaterThanOrEqual(-1000000),
                new LessThanOrEqual(1000000),
                new NotEqualTo(0),
                new Accuracy(2),
            ]])
            ->add('comment', TextType::class, ['constraints' => [
                new NotBlank(),
            ]]);
        $form = $formBuilder->getForm();

        return $form;
    }
}
