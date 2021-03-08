<?php namespace App\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class VuetifyCheckboxTransformer implements DataTransformerInterface
{
    private $trueValue;

    public function __construct(string $trueValue)
    {
        $this->trueValue = $trueValue;
    }

    public function transform($value)
    {
        return $value;
    }

    public function reverseTransform($value)
    {
        return $value === 'true';
    }
}
