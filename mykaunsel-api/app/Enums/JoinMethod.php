<?php

namespace App\Enums;

enum JoinMethod: string
{
    case Domain = 'domain';
    case InviteCode = 'invite_code';
    case MemberList = 'member_list';
    case Approval = 'approval';
}
