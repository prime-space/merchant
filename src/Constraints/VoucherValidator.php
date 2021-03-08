<?php namespace App\Constraints;

use App\Entity\Voucher as VoucherEntity;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class VoucherValidator extends ConstraintValidator
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Voucher) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\Voucher');
        }

        if (null === $value || '' === $value) {
            return;
        }

        /** @var VoucherEntity|null $voucher */
        $voucher = $this->repositoryProvider->get(VoucherEntity::class)->findOneBy(['key' => $value]);
        if (null === $voucher) {
            $this->context->buildViolation($constraint->messages['notFound'])->addViolation();
        } elseif ($voucher->statusId !== VoucherEntity::STATUS_ID_NEW) {
            $this->context->buildViolation($constraint->messages['used'])->addViolation();
        }
    }
}
