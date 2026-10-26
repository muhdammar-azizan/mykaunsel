<?php

namespace App\Jobs;

use App\Models\WaitlistEntry;
use App\Models\WaitlistSuppression;
use App\Notifications\WaitlistSignUpReopened;
use App\Services\PlatformSettings;
use App\Services\WaitlistHasher;
use App\Services\WaitlistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends the one "sign-up is open again" email to one address that "Notify all" has claimed (App\Services\WaitlistService).
 * Queued and sent after the commit, spread out over the minutes (a queue worker must be running).
 *
 * It checks again at the moment of sending, because time has passed since the claim: the address may have opted out or been
 * removed (then nothing is sent), the claim may no longer be this one, or the sign-up may have been closed again (then the
 * address goes back to waiting: an email saying "open" must never go out while it is closed). If sending fails for good the
 * address goes back to waiting so it can be tried again.
 */
class SendWaitlistEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @param  string  $claimedAt  ISO-8601 time the address was claimed: identifies THIS claim */
    public function __construct(public int $entryId, public string $claimedAt) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(WaitlistHasher $hasher, WaitlistService $waitlist, PlatformSettings $settings): void
    {
        $claimedAt = Carbon::parse($this->claimedAt);
        $entry = WaitlistEntry::query()->find($this->entryId);

        if ($entry === null || $entry->status !== WaitlistEntry::NOTIFIED || $entry->notified_at === null || ! $entry->notified_at->equalTo($claimedAt)) {
            return; // opted out, removed, purged, or claimed again since: nothing to send
        }

        if (WaitlistSuppression::query()->where('type', $entry->type)->where('email_hash', $entry->email_hash)->exists()) {
            return;
        }

        if (! $settings->registrationOpen($entry->type)) {
            $waitlist->release($entry->id, $claimedAt);

            return;
        }

        // At most ONE email per claim, even if this job runs twice (a worker restart, a redelivery): the address is marked
        // "emailed" first, by a conditional update only one run can win. If sending then fails, the mark is taken off again.
        $won = WaitlistEntry::query()->where('id', $entry->id)->where('status', WaitlistEntry::NOTIFIED)->where('notified_at', $claimedAt)->whereNull('emailed_at')
            ->update(['emailed_at' => now()]);

        if ($won !== 1) {
            return;
        }

        try {
            Notification::route('mail', $entry->email)->notifyNow(new WaitlistSignUpReopened($entry->type, $hasher->unsubscribeUrl($entry->type, $entry->email)));
        } catch (Throwable $e) {
            WaitlistEntry::query()->where('id', $entry->id)->where('notified_at', $claimedAt)->update(['emailed_at' => null]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        report($e);

        app(WaitlistService::class)->release($this->entryId, Carbon::parse($this->claimedAt));
    }
}
