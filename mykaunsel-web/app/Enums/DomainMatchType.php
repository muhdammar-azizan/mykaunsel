<?php

namespace App\Enums;

enum DomainMatchType: string
{
    case Exact = 'exact';
    case Wildcard = 'wildcard';
}
