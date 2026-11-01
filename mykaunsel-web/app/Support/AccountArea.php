<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The Account pages (profile, security, notifications) are one set of controllers and views used in two places: Platform Admin (`platform.account...`)
 * and the counselor and organization admin portals (`portal.account...`). This says which one a request is in, so a form posts, a redirect returns and a
 * link points to the SAME place the person is working in, and which e-mails that person may switch off.
 */
final class AccountArea
{
    /** "portal" for the counselor and organization admin portals, "platform" for Platform Admin. */
    public static function prefix(): string
    {
        return str_starts_with((string) request()->route()?->getName(), 'portal.') ? 'portal' : 'platform';
    }

    /** The route name in the current area: route('account.security') becomes platform.account.security or portal.account.security. */
    public static function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return route(self::prefix().'.'.$name, $parameters, $absolute);
    }

    /** The e-mails this person may switch off: the admins' own set in Platform Admin, and one set per role in the portals. @return array<string, array{label: string, description: string, default: bool}> */
    public static function notificationItems(Request $request): array
    {
        if (self::prefix() === 'platform') {
            return config('account.notifications');
        }

        return config('account.portal_notifications.'.$request->session()->get('current_role'), []);
    }
}
