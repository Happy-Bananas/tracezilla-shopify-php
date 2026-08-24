<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Retry;

enum FailureCategory: string
{
    case Temporary = 'temporary';
    case Business = 'business';
    case Unexpected = 'unexpected';
}

