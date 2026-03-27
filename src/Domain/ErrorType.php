<?php

declare(strict_types=1);

namespace App\Domain;

enum ErrorType: string
{
    case SubscriptionNotFound = 'subscription-not-found';
    case InvalidEntityType = 'invalid-entity-type';
    case ValidationError = 'validation-error';
}
