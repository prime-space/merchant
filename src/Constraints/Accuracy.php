<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class Accuracy extends Constraint
{
    public $message = 'constraint.accuracy';
    public $accuracy;

    public function __construct(int $accuracy)
    {
        parent::__construct(['accuracy' => $accuracy]);
    }

}
