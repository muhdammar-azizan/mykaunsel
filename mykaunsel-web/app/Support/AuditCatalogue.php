<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Read access to the action catalogue (config/audit.php) for pages that show audit entries. */
class AuditCatalogue
{
    /**
     * Category, badge and label for an action key. Keys that are not in the catalogue
     * (rows from before it existed) get a readable fallback instead of breaking the page.
     *
     * @return array{category: string, badge: string, label: string}
     */
    public static function describe(string $action): array
    {
        $known = config('audit.actions', [])[$action] ?? null;

        if ($known !== null) {
            return $known;
        }

        // A key an earlier version wrote: still readable, no longer writable.
        $legacy = config('audit.legacy_actions', [])[$action] ?? null;

        if ($legacy !== null) {
            return $legacy;
        }

        return [
            'category' => 'other',
            'badge' => 'updated',
            'label' => Str::headline(str_replace('.', ' ', $action)),
        ];
    }

    /** Categories hidden on the Audit Log page until "Sign-in activity" is switched on. */
    public static function hiddenByDefault(): array
    {
        return collect(config('audit.categories', []))
            ->filter(fn ($c) => $c['hidden_by_default'] ?? false)
            ->keys()
            ->all();
    }
}
