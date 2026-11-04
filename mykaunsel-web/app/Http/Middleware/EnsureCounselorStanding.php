<?php

namespace App\Http\Middleware;

use App\Enums\VerificationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a counselor may open depends on where their application stands (docs/COUNSELORS.md).
 *
 * - Pending or Rejected, for someone who APPLIED on the web (an independent counselor): the portal is not open yet. They go to the application
 *   status page, where they see how the review is going, the reason when it was rejected, and the way to send the documents again.
 * - Suspended: the portal stays open (booked sessions still need looking after); the dashboard says why they are not listed, with a link to the
 *   status page, and opening new times is refused (see `counselor.can-open-times`).
 * - Approved: nothing is limited here.
 * A counselor who was assigned by an organization did not apply, so there is no application page for them: they are not sent there.
 */
class EnsureCounselorStanding
{
    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user()?->counselorProfile;

        if ($profile !== null && $profile->isIndependent() && in_array($profile->verification_status, [VerificationStatus::Pending, VerificationStatus::Rejected], true)) {
            return redirect()->route('counselors.signup.waiting-approval');
        }

        return $next($request);
    }
}
