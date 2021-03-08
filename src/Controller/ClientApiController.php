<?php namespace App\Controller;

use App\Authenticator;
use App\ClientApi;
use App\Exception\BlockedException;
use App\Exception\ClientApiRequestException;
use App\Exception\DisabledException;
use App\Exception\IpNotAllowedException;
use App\Exception\NotFoundException;
use App\Exception\TokenNotMatchException;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ClientApiController extends Controller
{
    private $clientApi;
    private $repositoryProvider;
    private $authenticator;

    public function __construct(
        Authenticator $authenticator,
        ClientApi $clientApi,
        RepositoryProvider $repositoryProvider
    ) {
        $this->clientApi = $clientApi;
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
    }

    public function action(Request $request, int $userId, string $method)
    {
        try {
            $this->authenticator->authApi($userId, $request->getClientIp(), $request->headers->get('authorization'));
        } catch (NotFoundException $e) {
            return new JsonResponse([], 401);
        } catch (BlockedException $e) {
            return new JsonResponse(['Blocked'], 423);
        } catch (DisabledException $e) {
            return new JsonResponse([], 423);
        } catch (IpNotAllowedException $e) {
            return new JsonResponse([], 403);
        } catch (TokenNotMatchException $e) {
            return new JsonResponse([], 401);
        }

        $function = [$this->clientApi, "{$method}Method"];
        if (!is_callable($function)) {
            return new JsonResponse([], 405);
        }

        $this->clientApi->setRequest($request);

        try {
            $result = call_user_func($function, $request->request);

            return new JsonResponse($result);
        } catch (ClientApiRequestException $e) {
            return new JsonResponse($e->getErrors(), 400);
        }
    }
}
