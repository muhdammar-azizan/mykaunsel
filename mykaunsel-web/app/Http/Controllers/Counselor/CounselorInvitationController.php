<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Services\OrgCounselors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** A counselor answers an organization's invitation (shown on the Dashboard). Someone else's invitation is a 404. The rules are in OrgCounselors. */
class CounselorInvitationController extends Controller
{
    public function accept(Request $request, string $publicId, OrgCounselors $counselors): RedirectResponse
    {
        try {
            $membership = $counselors->accept($request->user(), $publicId);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        return back()->with('status', 'You now work for '.$membership->organization->name.'. Open times for it on your Schedule.');
    }

    public function decline(Request $request, string $publicId, OrgCounselors $counselors): RedirectResponse
    {
        try {
            $counselors->decline($request->user(), $publicId);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        return back()->with('status', 'Invitation declined.');
    }
}
