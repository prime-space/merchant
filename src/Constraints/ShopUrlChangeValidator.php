<?php namespace App\Constraints;

use App\Entity\Shop;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ShopUrlChangeValidator extends ConstraintValidator
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
        if (!$constraint instanceof ShopUrlChange) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\ShopUrlChange');
        }

        if (null === $value || '' === $value) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $shopId = $request->attributes->getInt('id');
        /** @var Shop|null $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($shopId);
        if (null === $shop) {
            return;
        }

        if ($shop->url !== $value) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }
    }
}
