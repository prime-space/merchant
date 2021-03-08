<?php namespace App\Constraints;

use App\Authenticator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UserBlockValidator extends ConstraintValidator
{
    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof UserBlock) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\UserBlock');
        }

        if ($this->authenticator->getUser()->isBlocked) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();

            return;
        }
    }
}
