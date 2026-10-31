<?php

namespace App\Enums;

enum JoinSource: string
{
    case Domain = 'domain';
    case InviteCode = 'invite_code';
    case Invitation = 'invitation';
    case MemberList = 'member_list';
    case Manual = 'manual';
}
