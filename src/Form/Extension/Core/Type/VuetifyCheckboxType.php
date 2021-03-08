<?php namespace App\Form\Extension\Core\Type;

use App\Form\Extension\Core\DataTransformer\VuetifyCheckboxTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

class VuetifyCheckboxType extends CheckboxType
{
    const RAW_VALUE_TRUE = 'true';
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setData(isset($options['data']) ? $options['data'] : false);
        $builder->addViewTransformer(new VuetifyCheckboxTransformer($options['value']));
    }
}
