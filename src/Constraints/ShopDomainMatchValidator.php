<?php namespace App\Constraints;

use App\Controller\ShopController;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ShopDomainMatchValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ShopDomainMatch) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\ShopDomainMatch');
        }

        if (null === $value || '' === $value) {
            return;
        }
        $valueHost = parse_url($value, PHP_URL_HOST);
        if (null === $valueHost || false === $valueHost) {
            return;
        }

        $url = $this->context->getRoot()->get(ShopController::SHOP_FORM_FIELD_NAME_URL)->getData();
        $urlHost = parse_url($url, PHP_URL_HOST);
        if (null === $urlHost || false === $urlHost) {
            return;
        }

        if ($valueHost !== $urlHost) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }
    }
}
