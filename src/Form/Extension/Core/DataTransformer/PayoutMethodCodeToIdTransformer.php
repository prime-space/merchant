<?php namespace App\Form\Extension\Core\DataTransformer;

use App\Entity\PaymentMethod;
use App\Entity\PayoutMethod;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Component\Form\DataTransformerInterface;

class PayoutMethodCodeToIdTransformer implements DataTransformerInterface
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function transform($value)
    {
        return null;
    }

    public function reverseTransform($value)
    {
        if (empty($value)) {
            return $value;
        }
        /** @var PaymentMethod|null $payoutMethod */
        $payoutMethod = $this->repositoryProvider->get(PayoutMethod::class)->findOneBy(['code' => $value]);

        $payoutMethodId = null === $payoutMethod ? 0 : $payoutMethod->id;

        return $payoutMethodId;
    }
}
