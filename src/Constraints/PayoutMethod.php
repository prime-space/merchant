<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class PayoutMethod extends Constraint
{
    public $messages = [
        'via' => 'constraint.via',
        'disabled' => 'constraint.method-disabled',
        'not-realised' => 'constraint.method-not-realised',
        'excluded' => 'constraint.method-excluded',
    ];
}
