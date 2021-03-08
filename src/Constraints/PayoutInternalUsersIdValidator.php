<?php namespace App\Constraints;

use App\Authenticator;
use App\Entity\PayoutSet;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PayoutInternalUsersIdValidator extends ConstraintValidator
{
    private $repositoryProvider;
    private $authenticator;

    public function __construct(Authenticator $authenticator, RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
        $this->authenticator = $authenticator;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof PayoutInternalUsersId) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\PayoutInternalUsersId');
        }

        if (null === $value || '' === $value) {
            return;
        }

        /** @var PayoutSet|null $payoutByInternalUsersId */
        $payoutByInternalUsersId = $this->repositoryProvider->get(PayoutSet::class)
            ->findOneBy(['userId' => $this->authenticator->getUser()->id, 'internalUsersId' => $value]);
        if (null !== $payoutByInternalUsersId) {
            $this->context->buildViolation($constraint->message, ['%operationId%' => $payoutByInternalUsersId->id])
                ->addViolation();

            return;
        }
    }
}
