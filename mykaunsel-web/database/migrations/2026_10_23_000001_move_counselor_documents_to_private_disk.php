<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Counselor documents were saved on the PUBLIC disk (storage/app/public/counselor-documents), which means anyone
 * with the file name could download a KB / PA certificate or an IC. This moves every referenced file to the
 * private disk under a new random name, updates the paths, removes the public copy, and sweeps out anything
 * unreferenced that was left in the public directory. The public/storage symlink itself is NOT touched
 * (avatars and premises photos still need it).
 *
 * Safe to run more than once. Not reversible on purpose: nothing may go back to a public place.
 */
return new class extends Migration
{
    private const COLUMNS = ['cert_document_path', 'pa_document_path', 'ic_document_path'];

    private const PUBLIC_DIRECTORY = 'counselor-documents';

    public function up(): void
    {
        $this->moveDocuments();
    }

    /** @return array{moved: int, missing: int, orphans: int} */
    public function moveDocuments(): array
    {
        $public = Storage::disk('public');
        $private = Storage::disk(config('counselors.documents_disk'));
        $moved = $missing = $orphans = 0;

        foreach (DB::table('counselor_profiles')->select(['id', ...self::COLUMNS])->get() as $row) {
            foreach (self::COLUMNS as $column) {
                $path = $row->{$column};

                if (blank($path)) {
                    continue;
                }

                if ($public->exists($path)) {
                    $newPath = Str::random(40).self::extensionOf($path);

                    $stream = $public->readStream($path);
                    $private->writeStream($newPath, $stream);
                    is_resource($stream) && fclose($stream);

                    // Point the row at the new copy first; only then remove the public one.
                    DB::table('counselor_profiles')->where('id', $row->id)->update([$column => $newPath]);
                    $public->delete($path);
                    $moved++;
                } elseif (! $private->exists($path)) {
                    $missing++; // referenced but gone — nothing to move, left as it was
                }
            }
        }

        // Anything still sitting in the public directory is unreferenced: keep it, but out of public reach.
        foreach ($public->files(self::PUBLIC_DIRECTORY) as $file) {
            $stream = $public->readStream($file);
            $private->writeStream('orphaned/'.Str::random(40).self::extensionOf($file), $stream);
            is_resource($stream) && fclose($stream);
            $public->delete($file);
            $orphans++;
        }

        $public->deleteDirectory(self::PUBLIC_DIRECTORY);

        return compact('moved', 'missing', 'orphans');
    }

    private static function extensionOf(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== '' ? '.'.preg_replace('/[^a-z0-9]/', '', $extension) : '';
    }

    public function down(): void
    {
        // Intentionally empty: documents are never moved back to a public disk.
    }
};
