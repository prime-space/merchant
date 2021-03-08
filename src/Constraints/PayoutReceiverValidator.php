<?php namespace App\Constraints;

use App\Accountant;
use App\Entity\PaymentSystem;
use App\Entity\PayoutMethod as PayoutMethodEntity;
use App\Exception\PayoutReceiverNotValidException;
use App\PaymentSystemManager\PaymentSystemManagerInterface;
use App\PaymentSystemManager\PayoutInterface;
use App\TagServiceProvider\TagServiceProvider;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PayoutReceiverValidator extends ConstraintValidator
{
    private $tagServiceProvider;
    private $paymentSystemManagers;
    private $repositoryProvider;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers
    ) {
        $this->tagServiceProvider = $tagServiceProvider;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->repositoryProvider = $repositoryProvider;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof PayoutReceiver) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\PayoutReceiver');
        }

        if (null === $value || '' === $value) {
            return;
        }

        $accountId = (int)$this->context->getRoot()->get(Accountant::PAYOUT_FROM_FIELD_NAME_ACCOUNT)->getData();
        $payoutMethodId = (int)$this->context->getRoot()->get(Accountant::PAYOUT_FROM_FIELD_NAME_METHOD)->getData();

        $payoutMethod = $this->repositoryProvider->get(PayoutMethodEntity::class)->findById($payoutMethodId);
        if (null === $payoutMethod) {
            return;
        }
        /** @var PaymentSystem $paymentSystem */
        $paymentSystem = $this->repositoryProvider->get(PaymentSystem::class)
            ->findById($payoutMethod->paymentSystemId);
        /** @var PaymentSystemManagerInterface $paymentSystemManager */
        $paymentSystemManager = $this->tagServiceProvider
            ->get($this->paymentSystemManagers, $paymentSystem->name);
        if (null === $paymentSystemManager || !$paymentSystemManager instanceof PayoutInterface) {
            return;
        }

        try {
            $paymentSystemManager->checkReceiver($value, $accountId);
        } catch (PayoutReceiverNotValidException $e) {
            $this->context
                ->buildViolation($e->getMessage())
                ->addViolation();

            return;
        }
    }
}
