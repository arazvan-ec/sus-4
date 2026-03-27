<?php

declare(strict_types=1);

namespace App\Domain;

enum EntityType: string
{
    case Journalist = 'journalist';
    case Tag = 'tag';
    case Section = 'section';
}
