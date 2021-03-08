<?php namespace App\Constraints;

use App\CaptchaProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CaptchaValidator extends ConstraintValidator
{
    private $captchaProvider;

    public function __construct(CaptchaProvider $captcha)
    {
        $this->captchaProvider = $captcha;
    }
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Captcha) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Captcha');
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!$this->captchaProvider->isCaptchaValid($value)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
