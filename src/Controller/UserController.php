<?php namespace App\Controller;

use App\Authenticator;
use App\Entity\User;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class UserController extends Controller
{
    private $repositoryProvider;
    private $authenticator;

    public function __construct(
        Authenticator $authenticator,
        RepositoryProvider $repositoryProvider
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
    }

    public function lkMode(Request $request, string $mode)
    {
        $isSigned = $this->authenticator->isSigned($request);

        if (!$isSigned) {
            return $this->redirect('/login');
        }
        $user = $this->authenticator->getUser();
        $user->lkMode = $mode;
        $this->repositoryProvider->get(User::class)->update($user, ['lkMode']);

        return $this->redirect('/private');
    }
}
