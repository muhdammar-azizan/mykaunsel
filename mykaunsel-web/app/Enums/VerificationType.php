<?php

namespace App\Enums;

enum VerificationType: string
{
    case OrgAssigned = 'org_assigned';
    case PlatformVerified = 'platform_verified';
}
