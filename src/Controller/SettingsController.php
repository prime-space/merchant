<?php namespace App\Controller;

use App\Authenticator;
use App\Constraints\Password;
use App\Dictionary\TimezoneDictionary;
use App\Entity\User;
use App\Exception\FormValidationException;
use App\Form\Extension\Core\Type\VueComboboxType;
use App\Form\Extension\Core\Type\VuetifyCheckboxType;
use App\VueViewCompiler;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class SettingsController extends Controller implements SignControllerInterface
{
    private $repositoryProvider;
    private $translator;
    private $authenticator;
    private $vueViewCompiler;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        TranslatorInterface $translator,
        Authenticator $authenticator,
        VueViewCompiler $vueViewCompiler
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->translator = $translator;
        $this->authenticator = $authenticator;
        $this->vueViewCompiler = $vueViewCompiler;
    }

    public function password(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->add('oldPassword', TextType::class, ['constraints' => [
                new NotBlank(['message' => 'fill-field']),
                new Password()
            ]])
            ->add('newPassword', TextType::class, ['constraints' => [
                new NotBlank(['message' => 'fill-field']),
            ]]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            return new JsonResponse([], 400);
        }

        if ($form->isValid()) {
            $data = $form->getData();
            $newPassword = $data['newPassword'];
            $this->authenticator->changePassword($newPassword);
        }
        $errors = $this->vueViewCompiler->formErrorsViewCompile($form->getErrors(true));
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => $errors], 400);
        }

        return new JsonResponse([]);
    }

    public function api(Request $request)
    {
        $user = $this->authenticator->getUser();
        $secretConstraints = [new Regex([
            'message' => '64 символа (латинские буквы, цифры) в нижнем регистре',
            'pattern' => '/^[a-z0-9]{64}$/'
        ])];
        if (empty($user->apiSecret)) {
            $secretConstraints[] = new NotBlank(['message' => 'Не должно быть пустым']);
        }
        $formBuilder = $this->createFormBuilder()
            ->add('apiIps', VueComboboxType::class, [
                'allow_add' => true,
                'entry_type' => TextType::class,
                'entry_options' => [
                    'constraints' => [
                        new NotBlank(['message' => 'fill-field']),
                        new Ip(['message' => 'invalid-ips']),
                    ],
                ]
            ])
            ->add('apiSecret', TextType::class, ['constraints' => $secretConstraints])
            ->add('password', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'fill-field']),
                    new Password(),
                ]])
            ->add('isApiEnabled', VuetifyCheckboxType::class);

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
            $user->apiIps = array_unique($data['apiIps']);
            $user->isApiEnabled = $data['isApiEnabled'];
            if (!empty($data['apiSecret'])) {
                $user->apiSecret = $data['apiSecret'];
            }
            $this->repositoryProvider->get(User::class)->update($user);
        } catch (FormValidationException $e) {
            $errors = $this->vueViewCompiler->formErrorsViewCompile($form->getErrors(true));
            if (count($errors) > 0) {
                return new JsonResponse(['errors' => $errors], 400);
            }
        }

        return new JsonResponse([]);
    }

    public function timezone(Request $request)
    {
        $formBuilder = $this->createFormBuilder()
            ->add('timezone', ChoiceType::class, [
                'choices' => TimezoneDictionary::getTimezonesValues(),
                'constraints' => [
                    new NotBlank(['message' => 'fill-field']),
                ]
            ]);

        $form = $formBuilder->getForm();
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            return new JsonResponse([], 400);
        }
        if ($form->isValid()) {
            $data = $form->getData();
            $user = $this->authenticator->getUser();
            if ($user !== null) {
                $user->timezone = $data['timezone'];
                $this->repositoryProvider->get(User::class)->update($user);
            }
        }

        $errors = $this->vueViewCompiler->formErrorsViewCompile($form->getErrors(true));
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => $errors], 400);
        }

        return new JsonResponse([]);
    }

    public function timezones()
    {
        $user = $this->authenticator->getUser();
        // @TODO form view
        $timezones = TimezoneDictionary::compileJsTimezonesView($this->translator);

        return new JsonResponse(['userTimezone' => $user->timezone, 'timezones' => $timezones]);
    }
}
