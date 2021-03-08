<?php namespace App\Controller;

use App\AdminApi;
use App\Exception\AdminApiDataValidationException;
use App\Exception\AdminApiEmbeddedFormValidationException;
use App\Exception\AdminApiException;
use App\Exception\NotFoundException;
use App\Exception\ControllerException;
use App\VueViewCompiler;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AdminApiController extends Controller
{
    private $adminApi;
    private $secret;
    private $vueViewCompiler;

    public function __construct(AdminApi $adminApi, string $secret, VueViewCompiler $vueViewCompiler)
    {
        $this->adminApi = $adminApi;
        $this->secret = $secret;
        $this->vueViewCompiler = $vueViewCompiler;
    }

    public function action(Request $request, string $method, int $id = null)
    {
        try {
            $auth = $request->headers->get('authorization');
            if (null === $auth || $auth !== "Bearer {$this->secret}") {
                throw new ControllerException(401);
            }

            $function = [$this->adminApi, "{$method}Method"];
            if (!is_callable($function)) {
                throw new ControllerException(405);
            }
            if ($id !== null) {
                $result = call_user_func($function, $id, $request->request, $request->query);
            } else {
                $result = call_user_func($function, $request->request, $request->query);
            }

            $response = new JsonResponse($result);
        } catch (ControllerException $e) {
            $response = new JsonResponse([], $e->getCode());
        } catch (NotFoundException $e) {
            $response = new JsonResponse([], 404);
        } catch (AdminApiException $e) {
            $response = new JsonResponse($e->getData());
        } catch (AdminApiDataValidationException $e) {
            $response = new JsonResponse(['errors' => $e->getErrors()], 400);
        } catch (AdminApiEmbeddedFormValidationException $e) {
            $errors = $this->vueViewCompiler->formErrorsViewCompile($e->getFormErrors());
            $response = new JsonResponse(['embeddedFormErrors' => $errors], 400);
        }

        return $response;
    }
}
