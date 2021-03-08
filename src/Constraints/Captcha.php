<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Captcha extends Constraint
{
    public $message = 'invalid-captcha';
}
