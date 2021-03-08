<?php namespace App\EventSubscriber;

use App\Authenticator;
use App\Controller\SignControllerInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class SignSubscriber implements EventSubscriberInterface
{
    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        if ($controller[0] instanceof SignControllerInterface) {
            if (!$this->authenticator->isSigned($event->getRequest())) {
                throw new UnauthorizedHttpException('What is it?');
            }
            $request = $event->getRequest();
            if (
                $request->getMethod() === 'POST'
                && $request->request->get('_token') !== $this->authenticator->getUser()->token
            ) {
                throw new AccessDeniedHttpException();
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => 'onKernelController',
        );
    }
}
