<?php

declare(strict_types=1);

namespace App\Domain;

enum CampaignStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
}
