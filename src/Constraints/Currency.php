<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class Currency extends Constraint
{
    public $message = 'constraint.currency';
}
