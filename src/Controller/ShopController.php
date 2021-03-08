<?php namespace App\Controller;

use App\AdminApi;
use App\Authenticator;
use App\Constraints\PostbackUrl;
use App\Constraints\ShopDomainMatch;
use App\Constraints\ShopUrlChange;
use App\Entity\Currency;
use App\Entity\PaymentMethod;
use App\Entity\Shop;
use App\Entity\User;
use App\Exception\AdminApiRequestException;
use App\Form\Extension\Core\Type\VuetifyCheckboxType;
use App\PaymentDayStatisticCounter;
use App\VueViewCompiler;
use DateTime;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;
use Ewll\DBBundle\DB\Client as DbClient;

class ShopController extends Controller implements SignControllerInterface
{
    const SHOP_FORM_FIELD_NAME_URL = 'url';

    private $repositoryProvider;
    private $authenticator;
    private $translator;
    private $adminApi;
    private $defaultDbClient;
    private $paymentDayStatisticCounter;
    private $vueViewCompiler;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider,
        TranslatorInterface $translator,
        AdminApi $adminApi,
        DbClient $defaultDbClient,
        PaymentDayStatisticCounter $paymentDayStatisticCounter,
        VueViewCompiler $vueViewCompiler
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
        $this->translator = $translator;
        $this->adminApi = $adminApi;
        $this->defaultDbClient = $defaultDbClient;
        $this->paymentDayStatisticCounter = $paymentDayStatisticCounter;
        $this->vueViewCompiler = $vueViewCompiler;
    }

    public function shop(Request $request, int $id = null)
    {
        $secretConstraints = [new Regex([
            'message' => '64 символа (латинские буквы, цифры) в нижнем регистре',
            'pattern' => '/^[a-z0-9]{64}$/'
        ])];
        if (null === $id) {
            $secretConstraints[] = new NotBlank(['message' => 'Не должно быть пустым']);
        }

        $formBuilder = $this->createFormBuilder()
            ->add('name', TextType::class, ['constraints' => [new NotBlank(['message' => 'Не должно быть пустым'])]])
            ->add(self::SHOP_FORM_FIELD_NAME_URL, TextType::class, ['constraints' => [
                new NotBlank(['message' => 'Не должно быть пустым']),
                new Url(['message' => 'Неверный формат']),
                new ShopUrlChange(),
            ]])
            ->add('description', TextType::class, ['constraints' => [
                new NotBlank(['message' => 'Не должно быть пустым'])
            ]])
            ->add('secret', TextType::class, ['constraints' => $secretConstraints])
            ->add('successUrl', TextType::class, ['constraints' => [
                new NotBlank(['message' => 'Не должно быть пустым']),
                new Url(['message' => 'Неверный формат']),
                new ShopDomainMatch(),
            ]])
            ->add('failUrl', TextType::class, ['constraints' => [
                new NotBlank(['message' => 'Не должно быть пустым']),
                new Url(['message' => 'Неверный формат']),
                new ShopDomainMatch(),
            ]])
            ->add('resultUrl', TextType::class, ['constraints' => [
                new NotBlank(['message' => 'Не должно быть пустым']),
                new Url(['message' => 'Неверный формат']),
                new ShopDomainMatch(),
            ]])
            ->add('isFeeByClient', VuetifyCheckboxType::class)
            ->add('isTestMode', VuetifyCheckboxType::class)
            ->add('isAllowedToRedefineUrl', VuetifyCheckboxType::class)
        ;

        $form = $formBuilder->getForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            return new JsonResponse([], 400);
        }
        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errors], 400);
        }

        $data = $form->getData();

        $shopRepository = $this->repositoryProvider->get(Shop::class);
        $userId = $this->authenticator->getUser()->id;
        if (null === $id) {
            $defaultExcludedPaymentMethods = $this->repositoryProvider->get(PaymentMethod::class)
                ->findBy(['isDefaultExcluded' => 1], 'id');
            $defaultExcludedPaymentMethodIds = array_keys($defaultExcludedPaymentMethods);
            $shop = Shop::create(
                $userId,
                $data['name'],
                $data[self::SHOP_FORM_FIELD_NAME_URL],
                $data['description'],
                $data['secret'],
                $data['successUrl'],
                $data['failUrl'],
                $data['resultUrl'],
                $data['isTestMode'],
                $data['isFeeByClient'],
                $data['isAllowedToRedefineUrl'],
                $defaultExcludedPaymentMethodIds
            );

            $shopRepository->create($shop);
        } else {
            /** @var Shop|null $shop */
            $shop = $shopRepository->findOneBy(['id' => $id, 'userId' => $userId]);
            if (null === $shop) {
                return new JsonResponse([], 404);
            }

            $shop->name = $data['name'];
            $shop->url = $data[self::SHOP_FORM_FIELD_NAME_URL];
            $shop->description = $data['description'];
            if (!empty($data['secret'])) {
                $shop->secret = $data['secret'];
            }
            $shop->successUrl = $data['successUrl'];
            $shop->failUrl = $data['failUrl'];
            $shop->resultUrl = $data['resultUrl'];
            $shop->isTestMode = $data['isTestMode'];
            $shop->isFeeByClient = $data['isFeeByClient'];
            $shop->isAllowedToRedefineUrl = $data['isAllowedToRedefineUrl'];

            $shopRepository->update($shop);
        }

        return new JsonResponse([]);
    }

    public function shops()
    {
        $userId = $this->authenticator->getUser()->id;
        $shops = $this->repositoryProvider->get(Shop::class)->findBy(['userId' => $userId]);
        $nowTime = new DateTime();
        $resetTime = new DateTime('tomorrow midnight');
        $resetInterval = $resetTime->getTimestamp() - $nowTime->getTimestamp();
        $data = [
            'shops' => [],
            'resetInterval' => $resetInterval,
        ];
        if (count($shops) > 0) {
            $paymentDayStatisticsIndexedByShopId = $this->paymentDayStatisticCounter
                ->getPaymentDayStatisticsIndexedByShopIdForToday(array_column($shops, 'id'));
            $currencyId = Currency::CURRENCY_RUB_ID;
            /** @var Shop $shop */
            foreach ($shops as $shop) {
                $paymentDayStatistic = $paymentDayStatisticsIndexedByShopId[$shop->id];
                $data['shops'][] = $shop->compileAdminView($this->translator, $paymentDayStatistic, $currencyId);
            }
        }

        return new JsonResponse($data);
    }

    public function shopInfo(int $id)
    {
        /** @var User $user */
        $user = $this->authenticator->getUser();
        /** @var Shop|null $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($id);
        if ($shop === null) {
            return new JsonResponse([], 404);
        }
        if ($shop->userId !== $user->id) {
            return new JsonResponse([], 403);
        }
        $paymentDayStatistic = $this->paymentDayStatisticCounter->getPaymentDayStatisticByShopIdForToday($shop->id);
        $currencyId = Currency::CURRENCY_RUB_ID;
        $shopView = $shop->compileAdminView($this->translator, $paymentDayStatistic, $currencyId);

        return new JsonResponse($shopView);
    }

    public function toChecking(int $id)
    {
        $shopRepository = $this->repositoryProvider->get(Shop::class);
        $userId = $this->authenticator->getUser()->id;
        /** @var Shop|null $shop */
        $shop = $shopRepository->findOneBy(['id' => $id, 'userId' => $userId]);
        if (null === $shop) {
            return new JsonResponse([], 404);
        }

        if (Shop::STATUS_ID_NEW === $shop->statusId) {
            $shop->statusId = Shop::STATUS_ID_ON_VERIFICATION;
            $this->defaultDbClient->beginTransaction();
            try {
                $shopRepository->update($shop);
                $this->adminApi->shopToChecking($shop->id);
                $this->defaultDbClient->commit();
            } catch (AdminApiRequestException $e) {
                $this->defaultDbClient->rollback();

                return new JsonResponse([], 500);
            }
        }

        return new JsonResponse([]);
    }

    public function paymentMethods(Request $request, int $id)
    {
        $excludedMethodsByUser = [];
        $shopRepository = $this->repositoryProvider->get(Shop::class);
        $userId = $this->authenticator->getUser()->id;
        /** @var Shop|null $shop */
        $shop = $shopRepository->findOneBy(['id' => $id, 'userId' => $userId]);
        if (null === $shop) {
            return new JsonResponse(['Shop not found'], 404);
        }
        $formBuilder = $this->createFormBuilder();
        $paymentMethods = $this->repositoryProvider->get(PaymentMethod::class)->findAll();
        foreach ($paymentMethods as $method) {
            $formBuilder->add("methodId_{$method->id}", VuetifyCheckboxType::class);
        }

        $form = $formBuilder->getForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            return new JsonResponse([], 400);
        }
        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errors], 400);
        }

        $data = $form->getData();
        foreach ($paymentMethods as $paymentMethod) {
            $formDataKey = "methodId_{$paymentMethod->id}";
            if (array_key_exists($formDataKey, $data) && $data[$formDataKey] === false) {
                $excludedMethodsByUser[] = $paymentMethod->id;
            }
        }

        $shop->excludedMethodsByUser = $excludedMethodsByUser;
        $shopRepository->update($shop);

        return new JsonResponse([]);
    }

    public function postback(Request $request, int $id)
    {
        $shopRepository = $this->repositoryProvider->get(Shop::class);
        $userId = $this->authenticator->getUser()->id;
        /** @var Shop|null $shop */
        $shop = $shopRepository->findOneBy(['id' => $id, 'userId' => $userId]);
        if (null === $shop) {
            return new JsonResponse(['Shop not found'], 404);
        }
        $postbackUrlConstraints = [
            new PostbackUrl(['message' => 'Неверный формат']),
            new NotNull(),
        ];
        $isPostbackEnabled = $request->request->get('form')['isPostbackEnabled'] ?? null;
        if (VuetifyCheckboxType::RAW_VALUE_TRUE === $isPostbackEnabled) {
            $postbackUrlConstraints[] = new NotBlank(['message' => 'Не должно быть пустым']);
        }
        $formBuilder = $this->createFormBuilder()
            ->add('isPostbackEnabled', VuetifyCheckboxType::class)
            ->add('postbackUrl', TextType::class, ['constraints' => $postbackUrlConstraints]);

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

        $shop->isPostbackEnabled = $data['isPostbackEnabled'];
        $shop->postbackUrl = $data['postbackUrl'];
        $shopRepository->update($shop);

        return new JsonResponse([]);
    }
}
