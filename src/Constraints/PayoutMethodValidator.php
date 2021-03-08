<?php namespace App\Constraints;

use App\Authenticator;
use App\Entity\PaymentSystem;
use App\PaymentSystemManager\PaymentSystemManagerInterface;
use App\PaymentSystemManager\PayoutInterface;
use App\TagServiceProvider\TagServiceProvider;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use App\Entity\PayoutMethod as PayoutMethodEntity;

class PayoutMethodValidator extends ConstraintValidator
{
    private $repositoryProvider;
    private $tagServiceProvider;
    private $paymentSystemManagers;
    private $authenticator;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers,
        Authenticator $authenticator
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->authenticator = $authenticator;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof PayoutMethod) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\PayoutMethod');
        }

        if (null === $value || '' === $value) {
            return;
        }

        /** @var PayoutMethodEntity|null $payoutMethod */
        $payoutMethod = $this->repositoryProvider->get(PayoutMethodEntity::class)->findById($value);
        if (null === $payoutMethod) {
            $this->context->buildViolation($constraint->messages['via'])->addViolation();

            return;
        }
        if (!$payoutMethod->isEnabled) {
            $this->context->buildViolation($constraint->messages['disabled'])->addViolation();

            return;
        }
        $isPayoutMethodExcluded = in_array($payoutMethod->id, $this->authenticator->getUser()->excludedPayoutMethods);
        if ($isPayoutMethodExcluded) {
            $this->context->buildViolation($constraint->messages['excluded'])->addViolation();

            return;
        }

        /** @var PaymentSystem $paymentSystem */
        $paymentSystem = $this->repositoryProvider->get(PaymentSystem::class)
            ->findById($payoutMethod->paymentSystemId);
        /** @var PaymentSystemManagerInterface $paymentSystemManager */
        $paymentSystemManager = $this->tagServiceProvider
            ->get($this->paymentSystemManagers, $paymentSystem->name);
        if (null === $paymentSystemManager || !$paymentSystemManager instanceof PayoutInterface) {
            $this->context->buildViolation($constraint->messages['not-realised'])->addViolation();

            return;
        }
    }
}
