<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class UserBlock extends Constraint
{
    public $message = 'constraint.user-blocked';
}
