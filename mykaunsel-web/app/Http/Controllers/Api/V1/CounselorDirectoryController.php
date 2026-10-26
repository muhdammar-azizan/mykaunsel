<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SessionMode;
use App\Http\Controllers\Controller;
use App\Services\CounselorDirectory;
use App\Support\CounselorPublicPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The mobile app's counselor search. What may be shown is decided in CounselorDirectory (who and when) and
 * CounselorPublicPresenter (which fields); see docs/PUBLIC_COUNSELOR_DATA.md.
 *
 * Nothing a person searches for is stored, audited or logged.
 */
class CounselorDirectoryController extends Controller
{
    public function index(Request $request, CounselorDirectory $directory): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', Rule::in(array_map(fn (SessionMode $m) => $m->value, SessionMode::cases()))],
            'state' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $result = $directory->search($request->user(), $filters, (int) ($filters['page'] ?? 1));

        return $this->private(response()->json([
            'data' => $result['profiles']->map(fn ($profile) => CounselorPublicPresenter::card($profile, $result['slots']->get($profile->user_id, collect()), $result['orgIds']))->values(),
            'meta' => [
                'page' => $result['page'],
                'per_page' => $result['perPage'],
                'total' => $result['total'],
                'last_page' => $result['lastPage'],
            ],
        ]));
    }

    public function show(Request $request, string $publicId, CounselorDirectory $directory): JsonResponse
    {
        // Not a counselor id at all: the same "not found" as a hidden counselor.
        abort_unless(preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $publicId) === 1, 404);

        $found = $directory->find($request->user(), strtolower($publicId));

        abort_if($found === null, 404);

        return $this->private(response()->json([
            'data' => CounselorPublicPresenter::profile($found['profile'], $found['slots'], $found['orgIds']),
        ]));
    }

    public function filters(Request $request, CounselorDirectory $directory): JsonResponse
    {
        return $this->private(response()->json(['data' => $directory->filterOptions($request->user())]));
    }

    /** What this person may see depends on who they are: no shared cache may keep it. */
    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
