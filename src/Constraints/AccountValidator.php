<?php namespace App\Constraints;

use App\Accountant;
use App\Authenticator;
use App\Entity\Account as AccountEntity;
use App\Entity\Currency as CurrencyEntity;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AccountValidator extends ConstraintValidator
{
    private $repositoryProvider;
    private $authenticator;

    public function __construct(RepositoryProvider $repositoryProvider, Authenticator $authenticator)
    {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
    }
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Account) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Account');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_int($value)) {
            throw new RuntimeException('Expect integer');
        }

        /** @var AccountEntity $account */
        $account = $this->repositoryProvider->get(AccountEntity::class)
            ->findOneBy(['id' => $value, 'userId' => $this->authenticator->getUser()->id]);

        if (null === $account || $account->currencyId !== CurrencyEntity::ID_RUB) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
