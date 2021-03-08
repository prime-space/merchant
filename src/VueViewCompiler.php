<?php namespace App;

use App\Form\Extension\Core\Type\VueComboboxType;
use Symfony\Component\Form\FormErrorIterator;

class VueViewCompiler extends ViewCompiler
{
    const MOMENT_DATE_FORMAT = 'Y-m-d H:i';
    const TIMEZONEJS_DATE_FORMAT = 'Y/m/d H:i';

    public function formErrorsViewCompile(FormErrorIterator $errors): array
    {
        $view = [];
        foreach ($errors as $error) {
            $parent = $error->getOrigin()->getParent();
            $parentClass = $parent !== null ? get_class($parent->getConfig()->getType()->getInnerType()) : null;
            if ($parentClass === VueComboboxType::class) {
                $parentName = $parent->getName();
                $view[$parentName] = $error->getMessage();
            } else {
                $view[$error->getOrigin()->getName()] = $error->getMessage();
            }
        }

        return $view;
    }
}
