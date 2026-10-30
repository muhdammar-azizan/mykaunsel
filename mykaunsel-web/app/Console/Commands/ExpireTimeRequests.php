<?php

namespace App\Console\Commands;

use App\Notifications\TimeRequestAnsweredClient;
use App\Services\AvailabilityService;
use App\Services\BookingRequests;
use Illuminate\Console\Command;
use Throwable;

/**
 * Closes every time request nobody answered in time (48 hours, or the time itself if that comes first) and tells the person by e-mail.
 * Safe to run again and again: a closed request is never touched twice, so nobody is told twice. Scheduled every ten minutes.
 */
class ExpireTimeRequests extends Command
{
    protected $signature = 'requests:expire';

    protected $description = 'Close time requests that nobody answered in time, and tell the people who asked';

    public function handle(BookingRequests $requests, AvailabilityService $availability): int
    {
        $closed = $requests->expireDue();

        foreach ($closed as $request) {
            try {
                $start = $requests->startOf($request);

                $request->user->notify(new TimeRequestAnsweredClient([
                    'outcome' => 'expired',
                    'counselor' => $requests->present($request)['counselor']['display_name'],
                    'date' => $start->format('l, j F Y'),
                    'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $request->duration_minutes)->format('g:i A'),
                    'minutes' => (int) $request->duration_minutes,
                    'mode' => ($request->session_mode ?? \App\Enums\SessionMode::Online)->value,
                    'reason' => null,
                ]));
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->info(count($closed).' request(s) closed.');

        return self::SUCCESS;
    }
}
