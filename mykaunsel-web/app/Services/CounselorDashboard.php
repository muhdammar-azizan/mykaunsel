<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Support\PaValidity;
use Carbon\CarbonImmutable;

/**
 * What the counselor sees first on the portal Dashboard, built from the services that already own the rules (no new rule here):
 * today's sessions, the next session, sessions that ended and still need marking, time requests waiting, the next free time, and a few
 * short notices. Only the counselor's own data; a client is a display name only (B3).
 */
class CounselorDashboard
{
    private const NOTICE_PA_DAYS = 30;

    private const REQUEST_PREVIEW = 3;

    public function __construct(
        private readonly CounselorSessions $sessions,
        private readonly CounselorRequests $requests,
        private readonly CounselorPlanner $planner,
        private readonly AvailabilityService $availability,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $counselor): array
    {
        $now = $this->availability->now();
        $today = $now->format('D, j M Y');

        $open = $this->sessions->open($counselor);
        $upcoming = $open['upcoming'];
        $requests = $this->requests->pending($counselor);

        return [
            'name' => (string) $counselor->name,
            'today_label' => $now->format('l, j F Y'),
            'today' => array_values(array_filter($upcoming, fn (array $row) => $row['date'] === $today)),
            'next' => $upcoming[0] ?? null,
            'upcoming_count' => count($upcoming),
            'to_mark' => count($open['to_mark']),
            'requests' => array_slice($requests, 0, self::REQUEST_PREVIEW),
            'requests_count' => count($requests),
            'next_free' => $this->nextFree($counselor, $now),
            'notices' => $this->notices($counselor, $now),
            'invitations' => app(OrgCounselors::class)->invitationsFor($counselor)->map(fn ($i) => ['id' => $i->public_id, 'organization' => $i->organization->name])->all(),
        ];
    }

    /** The first time still ahead that nobody has booked. @return array{day: string, time: string}|null */
    private function nextFree(User $counselor, CarbonImmutable $now): ?array
    {
        foreach ($this->planner->upcoming($counselor) as $slot) {
            if ($slot['booked']) {
                continue;
            }

            $start = CarbonImmutable::parse($slot['day'].' '.explode(' – ', $slot['time'])[0], $this->availability->timezone());

            if ($start->gt($now)) {
                return ['day' => $slot['day_label'], 'time' => $slot['time']];
            }
        }

        return null;
    }

    /** @return list<array{tone: string, text: string, url?: string, label?: string}> */
    private function notices(User $counselor, CarbonImmutable $now): array
    {
        $profile = $counselor->counselorProfile;
        $notices = [];

        if ($profile === null) {
            return $notices;
        }

        if ($profile->verification_status !== VerificationStatus::Approved) {
            $notice = ['tone' => 'red', 'text' => 'Your profile is '.$profile->verification_status->value.', so people can not book you.'];

            // Someone who applied on the web has a page for their application (the reason, and what to do next).
            if ($profile->isIndependent()) {
                $notice += ['url' => route('counselors.signup.waiting-approval'), 'label' => 'See the details'];
            }

            $notices[] = $notice;
        }

        $left = PaValidity::daysLeft($profile->pa_valid_until, $now);

        if ($left !== null && $left < 0) {
            $notices[] = ['tone' => 'red', 'text' => 'Your practising certificate (PA) has expired.'];
        } elseif ($left !== null && $left <= self::NOTICE_PA_DAYS) {
            $notices[] = ['tone' => 'amber', 'text' => 'Your practising certificate (PA) expires in '.$left.' '.($left === 1 ? 'day' : 'days').'.'];
        }

        return $notices;
    }
}
