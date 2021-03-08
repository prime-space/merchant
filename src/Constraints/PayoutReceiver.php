<?php namespace App\Constraints;

use Symfony\Component\Validator\Constraint;

class PayoutReceiver extends Constraint
{
    const MESSAGE_KEY_INCORRECT = 'constraint.receiver.incorrect';
    const MESSAGE_KEY_SELF = 'constraint.receiver.self';
    const MESSAGE_KEY_CURRENCY = 'constraint.receiver.currency';
    const MESSAGE_KEY_NOT_FOUND = 'constraint.receiver.not-found';
}
