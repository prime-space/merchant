<?php namespace App\Exception;

use Exception;
use Symfony\Component\Form\FormInterface;

class FormValidationException extends Exception
{
    private $form;

    public function __construct(FormInterface $form = null)
    {
        $this->form = $form;
        parent::__construct();
    }

    public function getErrors(): array
    {
        if (null === $this->form) {
            return [];
        }

        $formErrors = $this->form->getErrors(true);
        $errors = [];
        foreach ($formErrors as $formError) {
            $errors[$formError->getOrigin()->getName()] = $formError->getMessage();
        }

        return $errors;
    }
}
