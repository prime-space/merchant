<?php namespace App\Constraints;

use App\Entity\Currency as CurrencyEntity;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CurrencyValidator extends ConstraintValidator
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Currency) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Currency');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_int($value)) {
            throw new RuntimeException('Expect integer');
        }

        /** @var CurrencyEntity $currency */
        $currency = $this->repositoryProvider->get(CurrencyEntity::class)->findById($value);

        //@TODO
        if (null === $currency || $currency->id !== CurrencyEntity::ID_RUB) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
