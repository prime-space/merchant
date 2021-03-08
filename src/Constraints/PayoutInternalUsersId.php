<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class PayoutInternalUsersId extends Constraint
{
    public $message = 'constraint.duplicate-id';
}
