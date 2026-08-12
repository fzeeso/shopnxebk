<?php

declare(strict_types=1);

namespace App\Enums;

enum TranslationRequestStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Superseded, self::Cancelled], true);
    }
}
