<?php namespace App\Exception;

use Exception;
use Symfony\Component\Form\FormErrorIterator;

class AdminApiEmbeddedFormValidationException extends Exception
{
    private $formErrors;

    public function __construct(FormErrorIterator $formErrors)
    {
        $this->formErrors = $formErrors;
    }

    public function getFormErrors(): FormErrorIterator
    {
        return $this->formErrors;
    }

}
