<?php

namespace App\Enums;

enum OrgType: string
{
    case University = 'university';
    case Corporate = 'corporate';
    case Clinic = 'clinic';
    case Platform = 'platform';
}
