<?php namespace App\Constraints;

use App\Authenticator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PasswordValidator extends ConstraintValidator
{
    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Password) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Password');
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!$this->authenticator->isPasswordCorrect($value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
