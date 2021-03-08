<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class ShopDomainMatch extends Constraint
{
    public $message = 'constraint.domains-does-not-match';
}
