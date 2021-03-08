<?php namespace App\Constraints;

use App\Entity\Shop;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PaymentSignValidator extends ConstraintValidator
{
    private $repositoryProvider;
    private $requestStack;

    public function __construct(RepositoryProvider $repositoryProvider, RequestStack $requestStack)
    {
        $this->repositoryProvider = $repositoryProvider;
        $this->requestStack = $requestStack;
    }
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof PaymentSign) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\PaymentSign');
        }

        $request = $this->requestStack->getCurrentRequest();

        $data = $request->request->all();
        unset($data['pay']);
        $shop = $this->repositoryProvider->get(Shop::class)->findById((int)$data['shop']);

        if (null !== $value && null !== $shop) {
            $sign = $data['sign'];
            unset($data['sign']);
            ksort($data, SORT_STRING);
            $compsign = hash('sha256', sprintf('%s:%s', implode(':', $data), $shop->secret));
            if ($sign !== $compsign) {
                $this->context->buildViolation($constraint->message)->addViolation();
            }
        }
    }
}
