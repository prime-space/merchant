<?php namespace App;

use App\Entity\Session;
use App\Repository\SessionRepository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\HttpFoundation\RequestStack;

class SessionRegistry
{
    /** @var SessionRepository */
    private $sessionRepository;
    /** @var null|Session */
    private $session;
    private $host;
    private $requestStack;

    public function __construct(RepositoryProvider $repositoryProvider, string $host, RequestStack $requestStack)
    {
        $this->sessionRepository = $repositoryProvider->get(Session::class);
        $this->host = $host;
        $this->requestStack = $requestStack;
    }

    // @TODO fix race conditions
    public function set(string $key, string $value): void
    {
        $session = $this->getSession();
        $session->params[$key] = $value;
        if ($session->id !== null) {
            $this->sessionRepository->update($session);
        } else {
            $this->sessionRepository->create($session);
        }
    }

    public function get(string $key): ?string
    {
        $session = $this->getSession();
        return $session->params[$key] ?? null;
    }

    public function delete(string $key): void
    {
        $session = $this->getSession();
        $params = $session->params;
        unset($params[$key]);
        $session->params = $params;
        $this->sessionRepository->update($session);
    }

    private function getSession(): Session
    {
        if (!isset($this->session)) {
            $sessionKey = $this->requestStack->getCurrentRequest()->cookies->get('ch');
            if ($sessionKey === null) {
                $this->createSession();
            } else {
                $session = $this->sessionRepository->findOneBy(['key' => $sessionKey]);
                if ($session !== null) {
                    $this->session = $session;
                } else {
                    $this->createSession();
                }
            }
        }

        return $this->session;
    }

    private function setCookie(string $value, int $duration): void
    {
        SetCookie('ch', $value, time() + $duration, '/', $this->host, true, true);
    }

    private function createSession(): void
    {
        $sessionKey = hash('sha256', uniqid('', true));
        $this->session = Session::create($sessionKey, []);
        $this->setCookie($sessionKey, Session::COOKIE_DURATION_SEC);
    }
}
