<?php namespace App\Controller;

use App\Authenticator;
use App\Constraints\Voucher as VoucherConstraint;
use App\Entity\Voucher;
use App\MessageBroker;
use App\VueViewCompiler;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\NotBlank;

class VoucherController extends Controller implements SignControllerInterface
{
    private $repositoryProvider;
    private $authenticator;
    private $vueViewCompiler;
    private $messageBroker;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        Authenticator $authenticator,
        VueViewCompiler $vueViewCompiler,
        MessageBroker $messageBroker
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
        $this->vueViewCompiler = $vueViewCompiler;
        $this->messageBroker = $messageBroker;
    }

    public function voucher(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->add('key', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill-field']),
                    new VoucherConstraint(),
                ]
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
        /** @var Voucher $voucher */
        $voucher = $this->repositoryProvider->get(Voucher::class)->findOneBy(['key' => $data['key']]);
        $user = $this->authenticator->getUser();

        $this->messageBroker->createMessage(MessageBroker::QUEUE_EXEC_VOUCHER, [
            'voucherId' => $voucher->id,
            'userId' => $user->id,
        ]);

        return new JsonResponse([]);
    }
}
