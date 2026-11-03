<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Where a page of the public website leads (config/landing.php). A page that exists opens its own route; every other page of the planned site opens the
 * "Coming soon" page, so no menu link is ever dead or a 404.
 */
class LandingLinks
{
    public static function url(string $slug): string
    {
        $route = config("landing.built.{$slug}");

        if ($route !== null && Route::has($route)) {
            return route($route);
        }

        if ($slug === 'get-the-app') {
            return (string) config('registration.mobile_app_url');
        }

        return route('landing.soon', ['page' => $slug]);
    }

    /** True for the pages that carry the small "Soon" tag. */
    public static function isSoon(string $slug): bool
    {
        return in_array($slug, (array) config('landing.soon_tagged'), true);
    }

    /** Whether a block of invented content may be shown now. A block that is not a placeholder is always shown. */
    public static function shows(string $block): bool
    {
        return ! in_array($block, (array) config('landing.placeholder_blocks'), true) || (bool) config('landing.show_placeholder_content');
    }

    /** The first file of this slot in public/landing (the owner drops the video or picture there), or null. */
    public static function media(string $slot): ?array
    {
        foreach (['mp4' => 'video', 'webm' => 'video', 'webp' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'svg' => 'image'] as $extension => $kind) {
            if (is_file(public_path("landing/{$slot}.{$extension}"))) {
                return ['kind' => $kind, 'url' => asset("landing/{$slot}.{$extension}")];
            }
        }

        return null;
    }
}
