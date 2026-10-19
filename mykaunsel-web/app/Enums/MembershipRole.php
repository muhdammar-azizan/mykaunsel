<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Student = 'student';
    case Staff = 'staff';
    case Employee = 'employee';
    case Counselor = 'counselor';
    case OrgAdmin = 'org_admin';
    case PlatformAdmin = 'platform_admin';
}
