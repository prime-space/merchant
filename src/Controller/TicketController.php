<?php namespace App\Controller;

use App\AdminApi;
use App\Authenticator;
use App\Entity\Ticket;
use App\Exception\AdminApiRequestException;
use App\Exception\FormValidationException;
use App\Repository\TicketRepository;
use App\VueViewCompiler;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Ewll\DBBundle\DB\Client as DbClient;

class TicketController extends Controller implements SignControllerInterface
{
    private $repositoryProvider;
    private $authenticator;
    private $adminApi;
    private $defaultDbClient;
    private $translator;
    private $vueViewCompiler;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider,
        AdminApi $adminApi,
        DbClient $defaultDbClient,
        TranslatorInterface $translator,
        VueViewCompiler $vueViewCompiler
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
        $this->adminApi = $adminApi;
        $this->defaultDbClient = $defaultDbClient;
        $this->translator = $translator;
        $this->vueViewCompiler = $vueViewCompiler;
    }

    public function tickets()
    {
        $userId = $this->authenticator->getUser()->id;
        /** @var TicketRepository $ticketRepository */
        $ticketRepository = $this->repositoryProvider->get(Ticket::class);
        $tickets = $ticketRepository->getTicketsByUserIdOrderedByUnreadAndDate($userId);

        $views = [];
        /** @var Ticket $ticket */
        foreach ($tickets as $ticket) {
            $views[] = $ticket->compileView();
        }

        return new JsonResponse($views);
    }

    public function ticket(Request $request)
    {
        $errors = [];
        $formBuilder = $this->createFormBuilder()
            ->add('message', TextType::class, ['constraints' => [
                new NotBlank(['message' => 'fill-field'])
            ]])
            ->add('theme', TextType::class, ['constraints' => [
                new NotBlank(['message' => 'fill-field'])
            ]]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            return new JsonResponse([], 400);
        }
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $data = $form->getData();
            $ticketRepository = $this->repositoryProvider->get(Ticket::class);
            $userId = $this->authenticator->getUser()->id;

            $this->defaultDbClient->beginTransaction();
            try {
                $ticket = Ticket::create($userId, $data['theme']);
                $ticketRepository->create($ticket);
                $this->adminApi->createTicket($ticket, $data['message']);
                $this->defaultDbClient->commit();
            } catch (AdminApiRequestException $e) {
                $this->defaultDbClient->rollback();
                $errorMessage = $this->translator->trans('fail-create-ticket', [], 'ticket');
                $form->get('message')->addError(new FormError($errorMessage));

                throw new FormValidationException();
            }

            return new JsonResponse(['id' => $ticket->id]);

        } catch (FormValidationException $e) {
            //@todo use VueViewCompiler
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errors], 400);
        }
    }

    public function messages(int $ticketId)
    {
        /** @var Ticket $ticket */
        $ticket = $this->repositoryProvider->get(Ticket::class)->findOneBy(['id' => $ticketId]);
        if ($ticket === null) {
            return new JsonResponse([], 404);
        }
        $user = $this->authenticator->getUser();
        if ($ticket->userId !== $user->id) {
            return new JsonResponse([], 403);
        }
        $ticket->hasUnreadMessage = false;
        $this->repositoryProvider->get(Ticket::class)->update($ticket);
        $messages = $this->adminApi->getTicketMessages($ticketId);
        if (count($messages) === 0) {
            return new JsonResponse([], 500);
        }
        $data = [
            'messages' => $messages,
            'ticketSubject' => $ticket->subject
        ];

        return new JsonResponse($data);
    }

    public function message(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->add('message', TextType::class, ['constraints' => [
                new NotBlank(['message' => 'fill-field']),
            ]])
            ->add('ticketId', IntegerType::class, ['constraints' => [
                new NotBlank(['message' => 'fill-field']),
            ]]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            return new JsonResponse([], 400);
        }
        try {
            if (!$form->isValid()) {
                throw new FormValidationException();
            }
            $data = $form->getData();
            $userId = $this->authenticator->getUser()->id;
            $ticket = $this->repositoryProvider->get(Ticket::class)->findOneBy([
                'id' => $data['ticketId'],
                'userId' => $userId,
            ]);
            if ($ticket === null) {
                return new JsonResponse([], 404);
            }
            try {
                $this->adminApi->createMessage($data['ticketId'], $data['message']);
            } catch (AdminApiRequestException $e) {
                $errorMessage = $this->translator->trans('fail-create-message', [], 'ticket');
                $form->get('message')->addError(new FormError($errorMessage));
                throw new FormValidationException();
            }
            return new JsonResponse([]);
        } catch (FormValidationException $e) {
            $errors = $this->vueViewCompiler->formErrorsViewCompile($form->getErrors(true));

            return new JsonResponse(['errors' => $errors], 400);
        }
    }
}
