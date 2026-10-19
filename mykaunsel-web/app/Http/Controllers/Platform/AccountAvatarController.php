<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountAvatarController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('account.avatar_max_kb')],
        ], [
            'avatar.max' => 'The photo must be 2MB or smaller.',
            'avatar.mimes' => 'Use a JPG, PNG or WEBP image.',
        ]);

        $jpeg = $this->squareJpeg($request->file('avatar'));

        $disk = Storage::disk(config('account.avatar_disk'));
        $user = $request->user();
        $old = $user->avatar_path;

        // Random filename so nobody can guess or enumerate other users' photos.
        $path = 'avatars/'.Str::random(40).'.jpg';
        $disk->put($path, $jpeg, 'public');

        $user->forceFill(['avatar_path' => $path])->save();

        if ($old) {
            $disk->delete($old);
        }

        AuditLog::record('avatar_changed', $user, ['change' => 'uploaded']);

        return redirect()->route('platform.account')->with('status', 'Photo updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk(config('account.avatar_disk'))->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();

            AuditLog::record('avatar_changed', $user, ['change' => 'removed']);
        }

        return redirect()->route('platform.account')->with('status', 'Photo removed.');
    }

    /**
     * Centre-crop to a square and re-encode as JPEG. Re-encoding also proves the
     * file is a real image and strips any embedded metadata.
     */
    private function squareJpeg(UploadedFile $file): string
    {
        $info = @getimagesize($file->getRealPath());

        $source = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if (! $source) {
            throw ValidationException::withMessages(['avatar' => 'That file is not a valid image.']);
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $side = min($width, $height);
        $size = (int) config('account.avatar_size');

        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $size, $size, $side, $side);

        ob_start();
        imagejpeg($canvas, null, 88);

        return (string) ob_get_clean();
    }
}
