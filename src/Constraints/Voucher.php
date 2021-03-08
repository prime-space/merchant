<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class Voucher extends Constraint
{
    public $messages = [
        'notFound' => 'constraint.voucher.not-found',
        'used' => 'constraint.voucher.used',
    ];
}
