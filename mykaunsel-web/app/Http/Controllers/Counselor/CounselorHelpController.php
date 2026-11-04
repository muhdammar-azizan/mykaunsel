<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Services\CounselorBlocks;
use App\Services\CounselorRequests;
use App\Services\CounselorWeeklyHours;

/**
 * The counselor's own Help page inside the portal. The numbers in the text (minutes, hours, days, limits) are read from the configuration and from
 * the services that enforce them, never typed into the page, so the page can not say one thing while the system does another.
 */
class CounselorHelpController extends Controller
{
    public function show()
    {
        return view('counselors.dashboard.help', [
            'n' => [
                'lead_hours' => (int) config('booking.min_lead_hours'),
                'client_horizon_days' => (int) config('booking.horizon_days'),
                'planner_horizon_days' => (int) config('booking.planner_horizon_days'),
                'max_weeks' => CounselorWeeklyHours::MAX_WEEKS,
                'max_fill' => CounselorWeeklyHours::MAX_FILL,
                'max_block_days' => CounselorBlocks::MAX_DAYS,
                'buffers' => array_values(array_filter((array) config('counselors.profile.buffer_minutes'))),
                'link_minutes' => (int) config('booking.meeting_link_visible_minutes'),
                'cancel_hours' => (int) config('booking.cancellation_default_hours'),
                'max_reschedules' => (int) config('booking.max_reschedules'),
                'request_hours' => (int) config('booking.request_expiry_hours'),
                'pending_requests' => (int) config('booking.max_pending_requests'),
                'answered_days' => CounselorRequests::ANSWERED_DAYS,
                'pa_reminders' => (array) config('counselors.pa_reminder_days'),
                'note_years' => (int) config('session-notes.retention_years'),
                'read_days' => (int) config('notifications.retention.read_days'),
                'unread_days' => (int) config('notifications.retention.unread_days'),
            ],
            'helplines' => (array) config('chat.helplines'),
            'support' => config('api.support_email'),
        ]);
    }
}
