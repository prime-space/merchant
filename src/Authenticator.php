<?php namespace App;

use App\Controller\IndexController;
use App\Entity\PayoutMethod;
use App\Entity\User;
use App\Entity\UserSession;
use App\Exception\BlockedException;
use App\Exception\CannotSignInException;
use App\Exception\ConfirmEmailSentException;
use App\Exception\ConfirmEmailException;
use App\Exception\DisabledException;
use App\Exception\IpNotAllowedException;
use App\Exception\NotFoundException;
use App\Exception\TokenNotMatchException;
use App\Repository\UserRepository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;
use Symfony\Component\Translation\TranslatorInterface;
use Twig_Error;
use Twig_Environment;
use Ewll\DBBundle\DB\Client as DbClient;
use Exception;

class Authenticator
{
    private $user;
    private $repositoryProvider;
    private $host;
    private $translator;
    private $templating;
    private $router;
    private $mailer;
    private $defaultDbClient;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        string $host,
        TranslatorInterface $translator,
        Twig_Environment $templating,
        Router $router,
        Mailer $mailer,
        DbClient $defaultDbClient
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->host = $host;
        $this->translator = $translator;
        $this->templating = $templating;
        $this->router = $router;
        $this->mailer = $mailer;
        $this->defaultDbClient = $defaultDbClient;
    }

    /**
     * @throws ConfirmEmailException
     * @throws ConfirmEmailSentException
     * @throws CannotSignInException
     */
    public function signIn($email, $pass)
    {
        $hash = $this->encodePassword($pass);

        /** @var User $user */
        $user = $this->repositoryProvider->get(User::class)->findOneBy(['email' => $email, 'pass' => $hash]);

        if (null === $user) {
            return false;
        }
        if (!$user->isEmailConfirmed) {
            if ($user->emailConfirmationCode === null) {
                $user->emailConfirmationCode = hash('sha256', $email.$user->id);
                $link = $this->router->generate(
                    IndexController::CONFIRM_EMAIL_ROUTE_NAME,
                    ['code' => $user->emailConfirmationCode],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                try {
                    $this->defaultDbClient->beginTransaction();
                    $this->repositoryProvider->get(User::class)->update($user, ['emailConfirmationCode']);
                    $this->mailer->createForUser(
                        $user->id,
                        Mailer::LETTER_NAME_CONFIRMATION,
                        ['link' => $link]
                    );
                    $this->defaultDbClient->commit();
                } catch (Exception $e) {
                    $this->defaultDbClient->rollback();
                    throw new CannotSignInException();
                }
                throw new ConfirmEmailSentException();
            } else {
                throw new ConfirmEmailException();
            }
        }

        //@TODO unique crypt
        //@TODO lastAction updating
        $time = time();
        $crypt = hash('sha256', $time.$email);
        $token = hash('sha256', $email.$time);
        $userSession = UserSession::create($user->id, $crypt, $token);
        $this->repositoryProvider->get(UserSession::class)->create($userSession);

        $this->setSessionCookie($crypt, 86400*10);

        return true;
    }

    public function isSigned(Request $request)
    {
        $crypt = $request->cookies->get('s');

        if (null === $crypt) {
            return false;
        }

        /** @var UserRepository $userRepository */
        $userRepository = $this->repositoryProvider->get(User::class);
        /** @var UserSession|null $userSession */
        $userSession = $this->repositoryProvider->get(UserSession::class)->findOneBy(['crypt' => $crypt]);
        if ($userSession !== null) {
            $this->user = $userRepository->findById($userSession->userId);
            $this->user->token = $userSession->token;
        }

        return null !== $this->user;
    }

    public function signOut()
    {
        //@TODO drop db session
        $this->setSessionCookie('', -3600);
    }

    /**
     * @throws BlockedException
     * @throws DisabledException
     * @throws IpNotAllowedException
     * @throws NotFoundException
     * @throws TokenNotMatchException
     */
    public function authApi(int $userId, string $ip, string $auth = null): void
    {
        /** @var User $user */
        $user = $this->repositoryProvider->get(User::class)->findById($userId);

        if (null === $user) {
            throw new NotFoundException();
        } elseif ($user->isBlocked) {
            throw new BlockedException();
        } elseif (!$user->isApiEnabled) {
            throw new DisabledException();
        } elseif (!in_array($ip, $user->apiIps, true)) {
            throw new IpNotAllowedException();
        } elseif (null === $auth || $auth !== "Bearer {$user->apiSecret}") {
            throw new TokenNotMatchException();
        }

        $this->user = $user;
    }

    public function getUser(): ?User
    {
        if (null === $this->user) {
            throw new RuntimeException('No user');
        }

        return $this->user;
    }

    private function setSessionCookie($value, $duration)
    {
        SetCookie('s', $value, time()+$duration, '/', $this->host, true, true);
    }

    public function signUp($email, $pass)
    {
        $hash = $this->encodePassword($pass);
        $excludedPayoutMethods = $this->repositoryProvider
            ->get(PayoutMethod::class)->findBy(['defaultExcluded' => 1]);
        $excludedPayoutMethodIds = array_column($excludedPayoutMethods, 'id');
        $user = User::create($email, $hash, User::LK_MODE_MERCHANT, $excludedPayoutMethodIds);
        $this->repositoryProvider->get(User::class)->create($user);
    }

    public function isFreeEmail($email)
    {
        $user = $this->repositoryProvider->get(User::class)->findOneBy(['email' => $email]);

        return $user === null;
    }

    public function encodePassword(string $password): string
    {
        $encodedPass = hash('sha256', $password);

        return $encodedPass;
    }
    
    public function isPasswordCorrect(string $password): bool
    {
        $user = $this->getUser();
        $hash = $this->encodePassword($password);
        
        return $hash === $user->pass;
    }

    public function changePassword(string $newPassword): void
    {
        $user = $this->getUser();
        $hash = $this->encodePassword($newPassword);
        $user->pass = $hash;
        $this->repositoryProvider->get(User::class)->update($user);
    }

    public function doNotAskPass(): bool
    {
        $user = $this->getUser();
        if ($user->doNotAskPassUntilTs === null) {
            return false;
        }

        return time() < date_timestamp_get($user->doNotAskPassUntilTs);
    }
}
