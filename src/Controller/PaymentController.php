<?php namespace App\Controller;

use App\Authenticator;
use App\Entity\Notification;
use App\ChartDataCompiler;
use App\Entity\Currency;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\Shop;
use App\Exception\NotFoundException;
use App\PaymentRefunder;
use App\Repository\PaymentRepository;
use App\VueViewCompiler;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use LogicException;
use DateTimeZone;
use DateTime;

class PaymentController extends Controller implements SignControllerInterface
{
    private $repositoryProvider;
    private $authenticator;
    private $translator;
    private $chartDataCompiler;
    private $paymentRefunder;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider,
        TranslatorInterface $translator,
        ChartDataCompiler $chartDataCompiler,
        PaymentRefunder $paymentRefunder
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
        $this->translator = $translator;
        $this->chartDataCompiler = $chartDataCompiler;
        $this->paymentRefunder = $paymentRefunder;
    }

    public function payments(Request $request, int $shopId, int $limit, int $pageId)
    {
        $user = $this->authenticator->getUser();
        $userId = $user->id;
        $shop = $this->repositoryProvider->get(Shop::class)->findOneBy(['id' => $shopId, 'userId' => $userId]);

        if (null === $shop) {
            return new JsonResponse([], 404);
        }

        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->repositoryProvider->get(Payment::class);
        /** @var Payment[] $payments */
        $payments = $paymentRepository->findSuccessByShopWithPagination($shopId, $pageId, $limit);
        $total = $paymentRepository->getFoundRows();

        /** @var PaymentMethod[] $paymentMethodsIndexedById */
        $paymentMethodsIndexedById = $this->repositoryProvider->get(PaymentMethod::class)->findAll('id');

        $items = [];
        foreach ($payments as $payment) {
            $items[] = [
                'id' => $payment->id,
                'notificationStatusId' => $payment->notificationStatusId,
                'payment' => $payment->payment,
                'methodName' => $paymentMethodsIndexedById[$payment->paymentMethodId]->name,
                'createdTs' => $payment->createdTs->format(VueViewCompiler::TIMEZONEJS_DATE_FORMAT),
                'amount' => sprintf(
                    '%s %s',
                    $payment->amount,
                    $this->translator->trans("currency.{$payment->currency}.sign", [], 'payment')
                ),
            ];
            //$shop->statusName = $this->translator->trans("shop.status.{$shop->statusName}", [], 'admin');
        }

        return new JsonResponse([
            'payments' => $items,
            'total' => $total,
        ]);
    }

    public function payment(Request $request, int $paymentId)
    {
        $userId = $this->authenticator->getUser()->id;
        /** @var Payment|null $payment */
        $payment = $this->repositoryProvider->get(Payment::class)->findOneBy([
            'id' => $paymentId,
            'statusId' => Payment::STATUS_ID_SUCCESS,
        ]);
        if ($payment === null) {
            return new JsonResponse([], 404);
        }
        /** @var Shop $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);
        if ($shop->userId !== $userId) {
            return new JsonResponse([], 403);
        }
        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById($payment->paymentMethodId);
        $paymentShot = $this->repositoryProvider->get(PaymentShot::class)->findOneBy([
            'paymentId' => $paymentId,
            'paymentMethodId' => $paymentMethod->id
        ]);
        if ($paymentShot === null) {
            throw new LogicException("PaymentShot for success Payment #{$paymentId} does not exist");
        }

        $paymentView = $payment->compileAdminDetailView($this->translator, $paymentMethod, $paymentShot);
        $notificationsView = [];
        /** @var Notification[] $notifications */
        $notifications = $this->repositoryProvider->get(Notification::class)->findBy(['paymentId' => $paymentId]);
        foreach ($notifications as $notification) {
            $notificationsView[] = $notification->compileAdminView($this->translator);
        }

        return new JsonResponse([
            'payment' => $paymentView,
            'notifications' => $notificationsView,
        ]);
    }

    public function getPaymentStatisticChartData(int $shopId = null)
    {
        $user = $this->authenticator->getUser();
        try {
            $data = $this->chartDataCompiler->compilePaymentChartData($user, $shopId);
        } catch (NotFoundException $e) {
            return new JsonResponse([], 404);
        }

        return new JsonResponse($data);
    }
}
