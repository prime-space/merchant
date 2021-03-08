<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class ShopUrlChange extends Constraint
{
    public $message = 'constraint.shop-url-cannot-change';
}
