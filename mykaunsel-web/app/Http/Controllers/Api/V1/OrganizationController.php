<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipNotEndable;
use App\Exceptions\MembershipRefused;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Services\MembershipEnding;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in person's organization (docs/APP_SCREENS.md, "Decisions of the owner"): which one they belong to or wait to join, joining with a
 * reference code, and leaving. A client has only one. Always about the signed-in person; there is no id in any of these routes.
 */
class OrganizationController extends Controller
{
    public function __construct(private MembershipService $memberships, private MembershipEnding $ending) {}

    /** `organization` is null when the person belongs to none. `status` is "waiting" (asked with a code, not approved yet) or "member". */
    public function show(Request $request): JsonResponse
    {
        return $this->private(response()->json(['organization' => $this->present($this->memberships->heldBy($request->user()))]));
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);

        try {
            $this->memberships->joinViaInviteCode($request->user(), $data['code']);
        } catch (MembershipRefused $refused) {
            return $refused->toResponse();
        }

        return $this->private(response()->json(['organization' => $this->present($this->memberships->heldBy($request->user()))], 201));
    }

    /** Leaving. What happened to the sessions is in the answer, so the app can say it. Nothing to leave: 404. */
    public function leave(Request $request): JsonResponse
    {
        try {
            $done = $this->ending->leave($request->user());
        } catch (MembershipNotEndable) {
            return $this->private(response()->json(['message' => 'You do not belong to an organization.', 'code' => 'not_a_member'], 404));
        }

        return $this->private(response()->json(['left' => $done]));
    }

    /** @return array{name: string, status: string, grace_days: int}|null */
    private function present(?Membership $membership): ?array
    {
        if ($membership === null) {
            return null;
        }

        return [
            'name' => $membership->organization->name,
            'status' => $membership->status === MembershipStatus::PendingApproval ? 'waiting' : 'member',
            'grace_days' => MembershipEnding::GRACE_DAYS,
        ];
    }

    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
