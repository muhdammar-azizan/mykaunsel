<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\CounselorDecisionBlocked;
use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\WaitlistEntry;
use App\Services\WaitlistService;
use App\Support\AuditLogCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform Admin → Registration Control → Waitlist: email everyone once when a sign-up has reopened, remove one address
 * on request, export the list. Never shows who opted out (only how many). Individuals have no waitlist.
 */
class PlatformWaitlistController extends Controller
{
    public function notify(Request $request, string $type, WaitlistService $waitlist): RedirectResponse
    {
        abort_unless($waitlist->validType($type), 404);

        try {
            $result = $waitlist->notifyAll($request->user(), $type);
        } catch (CounselorDecisionBlocked $e) {
            return $this->back($type)->with('error', 'Nobody was emailed. '.implode(' ', $e->reasons));
        }

        $label = config("platform-settings.registration_roles.{$type}.label");
        $emails = $result['sent'].' '.($result['sent'] === 1 ? 'email' : 'emails');

        return $this->back($type)->with('status', "{$emails} queued for people waiting for {$label} sign-up, a minute apart in batches of ".config('waitlist.send_per_minute').'.'
            .($result['failed'] > 0 ? " {$result['failed']} could not be claimed because they changed meanwhile." : ''));
    }

    public function remove(Request $request, WaitlistEntry $entry, WaitlistService $waitlist): RedirectResponse
    {
        $type = $entry->type;
        $waitlist->remove($request->user(), $entry);

        return $this->back($type)->with('status', 'That address was removed from the waitlist.');
    }

    /** The active tab as a CSV (waiting and notified; formulas made safe). The export is recorded in the audit log, with a count only. */
    public function export(Request $request, string $type, WaitlistService $waitlist): StreamedResponse|RedirectResponse
    {
        abort_unless($waitlist->validType($type), 404);

        $query = WaitlistEntry::query()->ofType($type)->orderBy('id');
        $total = (clone $query)->count();
        $max = (int) config('waitlist.export_max_rows');

        if ($total > $max) {
            return $this->back($type)->with('error', 'That is '.number_format($total).' addresses — more than the '.number_format($max).' an export can hold.');
        }

        DB::transaction(fn () => Audit::log('waitlist.exported', null, ['type' => $type, 'count' => $total], $request->user()));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Email', 'Type', 'Status', 'Signed up (UTC)', 'Notified (UTC)'], ',', '"', '');

            $query->chunkById((int) config('waitlist.chunk'), function ($entries) use ($out) {
                foreach ($entries as $entry) {
                    fputcsv($out, array_map([AuditLogCsv::class, 'neutralise'], [
                        $entry->email, $entry->type, $entry->status,
                        $entry->created_at->copy()->utc()->format('Y-m-d H:i:s'),
                        $entry->notified_at?->copy()->utc()->format('Y-m-d H:i:s'),
                    ]), ',', '"', '');
                }
            });

            fclose($out);
        }, 'waitlist-'.$type.'_'.now()->format('Ymd_Hi').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function back(string $type): RedirectResponse
    {
        return redirect()->to(route('platform.registration', ['waitlist' => $type]).'#waitlist');
    }
}
