<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrgReviewNote;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The reviewers' working area on an application: the shared checklist and internal notes. */
class PlatformOrganizationReviewController extends Controller
{
    /**
     * Tick / untick one checklist box. Saved immediately so every admin sees the same state.
     * Reference only — it never blocks approving or rejecting.
     */
    public function checklist(Request $request, Organization $organization): JsonResponse
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys(Organization::REVIEW_CHECKLIST))],
            'checked' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $result = null;

        DB::transaction(function () use ($organization, $data, $user, &$result) {
            // Lock so two admins ticking different boxes at once don't overwrite each other.
            $fresh = Organization::query()->lockForUpdate()->findOrFail($organization->id);

            if ($fresh->subscription_status !== OrgStatus::Pending) {
                return;
            }

            $state = $fresh->review_checklist ?? [];

            if ($data['checked']) {
                $state[$data['key']] = ['by' => $user->id, 'at' => now()->toIso8601String()];
            } else {
                unset($state[$data['key']]);
            }

            $fresh->update(['review_checklist' => $state ?: null]);
            $result = $state[$data['key']] ?? null;
        });

        if ($result === null && $data['checked']) {
            return response()->json(['message' => 'This application is no longer pending, so its checklist is closed.'], 409);
        }

        return response()->json([
            'checked' => $data['checked'],
            'by' => $data['checked'] ? $user->name : null,
            'at' => $data['checked'] ? 'just now' : null,
        ]);
    }

    /** Add an internal note. Only Platform Admins ever see these; applicants never do. */
    public function storeNote(Request $request, Organization $organization): RedirectResponse
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        $data = $request->validateWithBag('note', [
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Write something before adding the note.',
        ]);

        $note = OrgReviewNote::create([
            'org_id' => $organization->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);

        AuditLog::record('organization_note_added', $organization, ['note_id' => $note->id]);

        return redirect()->to(route('platform.organizations.show', $organization).'#notes')
            ->with('status', 'Note added.');
    }
}
