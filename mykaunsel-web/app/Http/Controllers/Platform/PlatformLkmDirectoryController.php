<?php

namespace App\Http\Controllers\Platform;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LkmDirectorySnapshot;
use App\Services\LkmVerificationService;
use App\Support\AuditEntryPresenter;
use App\Support\LkmDirectoryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform Admin → LKM Directory: our snapshot of the LKM register, kept up to date by hand.
 * Records are added and changed here, never deleted (counselors refer to them by KB number).
 */
class PlatformLkmDirectoryController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $filters = LkmDirectoryFilters::fromRequest($request);

        $records = $filters->apply(LkmDirectorySnapshot::query()->withCount('counselorProfiles')->with('counselorProfiles.user:id,name'))
            ->paginate(LkmDirectoryFilters::PER_PAGE)
            ->withQueryString();

        // A page number past the end (e.g. after narrowing the filters): go to the last real page.
        if ($records->isEmpty() && $records->currentPage() > 1) {
            return redirect()->route('platform.lkm', $filters->toQuery(['page' => $records->lastPage()]));
        }

        return view('platform.lkm.index', [
            'filters' => $filters,
            'records' => $records,
            'total' => LkmDirectorySnapshot::count(),
            // The whole directory, whatever the filters: the numbers in the chips above the list.
            'summary' => [
                'total' => LkmDirectorySnapshot::count(),
                'active' => LkmDirectorySnapshot::where('status', 'Aktif')->count(),
                'expired' => LkmDirectorySnapshot::paStatus('expired')->count(),
                'linked' => LkmDirectorySnapshot::registered()->count(),
            ],
            'states' => LkmDirectorySnapshot::query()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state'),
        ]);
    }

    public function create(): View
    {
        return view('platform.lkm.form', ['record' => new LkmDirectorySnapshot(['status' => 'Aktif']), 'editing' => false]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['kb_number' => strtoupper(trim((string) $request->input('kb_number'))), 'pa_number' => strtoupper(trim((string) $request->input('pa_number')))]);

        $data = $request->validate([
            'kb_number' => ['required', 'regex:/^KB\d{5}$/', Rule::unique('lkm_directory_snapshot', 'kb_number')],
            ...$this->rules(),
        ], ['kb_number.unique' => 'This KB number is already in the directory.', 'kb_number.regex' => 'A KB number looks like KB12345.']);

        $record = DB::transaction(function () use ($data) {
            $record = LkmDirectorySnapshot::create($this->clean($data));

            Audit::log('lkm.record_created', $record, [
                'kb_number' => $record->kb_number,
                'summary' => "Added {$record->kb_number} ({$record->status})",
            ]);

            return $record;
        });

        return redirect()->route('platform.lkm.show', $record)->with('status', "{$record->kb_number} added to the directory.".$this->sharedPaNote($record));
    }

    /**
     * Live checks for the form while typing: does this KB number exist, and which other records already use this PA number?
     * A warning only; saving is decided by the same server rules as always.
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kb' => ['nullable', 'regex:/^KB\d{5}$/'],
            'pa' => ['nullable', 'regex:/^PA\d{5}$/'],
            'except' => ['nullable', 'integer', 'min:1'],
        ]);

        $kb = isset($data['kb']) ? LkmDirectorySnapshot::where('kb_number', $data['kb'])->first(['id', 'kb_number', 'full_name']) : null;

        $pa = isset($data['pa'])
            ? LkmDirectorySnapshot::where('pa_number', $data['pa'])
                ->when(isset($data['except']), fn ($q) => $q->where('id', '!=', $data['except']))
                ->orderBy('kb_number')->limit(3)->get(['kb_number', 'full_name'])
                ->map(fn ($r) => ['kb_number' => $r->kb_number, 'name' => $r->full_name])->all()
            : [];

        return response()->json([
            'kb' => $kb ? ['kb_number' => $kb->kb_number, 'name' => $kb->full_name] : null,
            'pa' => $pa,
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, LkmDirectorySnapshot $record, LkmVerificationService $lkm): View
    {
        $profiles = $record->counselorProfiles()->with('user')->get();
        $timezone = $request->user()->display_timezone;

        $presenter = new AuditEntryPresenter($timezone);
        $history = AuditLog::query()
            ->where('target_type', 'lkm_record')->where('target_id', $record->id)
            ->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get();
        $presenter->preload($history);

        return view('platform.lkm.show', [
            'record' => $record,
            'profiles' => $profiles,
            'comparisons' => $lkm->compareMany($profiles),
            'history' => $history->map(fn (AuditLog $log) => $presenter->row($log)),
            'editing' => true,
            'timezone' => $timezone,
        ]);
    }

    public function update(Request $request, LkmDirectorySnapshot $record): RedirectResponse
    {
        // The KB number is the key counselors are matched by: it is not editable (add a new record instead).
        $request->merge(['pa_number' => strtoupper(trim((string) $request->input('pa_number')))]);

        $data = $request->validate($this->rules());
        $new = $this->clean($data);

        $changed = false;

        DB::transaction(function () use ($record, $new, &$changed) {
            $locked = LkmDirectorySnapshot::query()->lockForUpdate()->findOrFail($record->id);
            $changes = [];

            foreach ($new as $field => $value) {
                $old = $locked->getAttribute($field);
                $oldText = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : $old;

                if ((string) $oldText !== (string) $value) {
                    $changes[$field] = ['old' => $oldText, 'new' => $value];
                }
            }

            if ($changes === []) {
                return;
            }

            $locked->update($new);
            $changed = true;

            Audit::log('lkm.record_updated', $locked, [
                'kb_number' => $locked->kb_number,
                'changes' => $changes,
            ]);
        });

        return redirect()->route('platform.lkm.show', $record)->with('status', ($changed ? 'Record updated.' : 'No changes to save.').$this->sharedPaNote($record->refresh()));
    }

    // ---- helpers ---------------------------------------------------------------------------

    /** A reminder (not an error) when another record carries the same PA number: it is usually a typing mistake. */
    private function sharedPaNote(LkmDirectorySnapshot $record): string
    {
        $others = LkmDirectorySnapshot::where('pa_number', $record->pa_number)->where('id', '!=', $record->id)->orderBy('kb_number')->pluck('kb_number');

        return $others->isEmpty() ? '' : " Note: {$record->pa_number} is also on ".$others->implode(', ').'.';
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return LkmDirectorySnapshot::fieldRules();
    }

    /** Trim text, turn empty optional fields into null, and keep the PA number upper-case. */
    private function clean(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;
            $out[$key] = $value === '' ? null : $value;
        }

        if (isset($out['pa_number'])) {
            $out['pa_number'] = strtoupper($out['pa_number']);
        }

        return $out;
    }
}
