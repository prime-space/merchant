<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class PaymentSign extends Constraint
{
    public $message = 'constraint.payment-sign';
}
