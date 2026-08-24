<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Retry;

enum RetryStatus: string
{
    case Pending = 'retry_pending';
    case Attention = 'attention_required';
}

