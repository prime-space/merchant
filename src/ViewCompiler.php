<?php namespace App;

use Symfony\Component\Form\FormErrorIterator;

abstract class ViewCompiler
{
    public function formErrorsViewCompile(FormErrorIterator $errors): array
    {
        $view = [];
        foreach ($errors as $error) {
            $view[$error->getOrigin()->getName()] = $error->getMessage();
        }

        return $view;
    }
}
