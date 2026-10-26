<?php

namespace Tests\Feature\Platform;

use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\LkmDirectorySnapshot;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Feature\Counselor\CounselorTestCase;

class LkmImportTest extends CounselorTestCase
{
    private const HEADER = 'kb_number,pa_number,full_name,status,pa_valid_from,pa_valid_until,email,city,state';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('lkm_imports');
        $this->seedSamples();
    }

    private function csv(array $lines, string $header = self::HEADER): string
    {
        return $header."\n".implode("\n", $lines)."\n";
    }

    private function file(string $contents, string $name = 'records.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    /** Step 1: upload and preview. */
    private function stage(string $contents, array $extra = [])
    {
        return $this->as()->post(route('platform.lkm.import.stage'), ['file' => $this->file($contents), 'confirm_simulated' => '1'] + $extra);
    }

    private function commit(array $data = [])
    {
        return $this->as()->post(route('platform.lkm.import.commit'), ['confirm_simulated' => '1'] + $data);
    }

    private function staged(): array
    {
        return Storage::disk('lkm_imports')->allFiles();
    }

    private function preview(string $contents)
    {
        $this->stage($contents)->assertRedirect(route('platform.lkm.import'));

        return $this->as()->get(route('platform.lkm.import'));
    }

    private function record(string $kb): LkmDirectorySnapshot
    {
        return LkmDirectorySnapshot::where('kb_number', $kb)->firstOrFail();
    }

    /** A file row holding the record exactly as it is, except for the given columns. */
    private function rowOf(LkmDirectorySnapshot $r, array $override = []): string
    {
        $values = array_merge([
            'kb_number' => $r->kb_number, 'pa_number' => $r->pa_number, 'full_name' => $r->full_name, 'status' => $r->status,
            'pa_valid_from' => $r->pa_valid_from->format('Y-m-d'), 'pa_valid_until' => $r->pa_valid_until->format('Y-m-d'),
            'email' => $r->email, 'city' => $r->city, 'state' => $r->state,
        ], $override);

        return implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $values));
    }

    /** Two new records, one update of KB10234 (a counselor is linked to it: only its city changes) and one row already exactly in the directory. */
    private function mixedFile(): string
    {
        return $this->csv([
            'KB30001,PA40001,Aminah binti Said,Aktif,2025-01-01,2028-01-01,aminah@example.com,Kuantan,Pahang',
            'KB30002,PA40002,Bala a/l Raman,Aktif,2025-02-01,2028-02-01,,Ipoh,Perak',
            $this->rowOf($this->record('KB10234'), ['city' => 'Changed City']),
            $this->rowOf($this->record('KB10891')),
        ]);
    }

    // ---- access ----------------------------------------------------------------------------

    public function test_only_platform_admins_can_use_import_and_export(): void
    {
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $before = LkmDirectorySnapshot::count();

        foreach ([
            fn ($t) => $t->get(route('platform.lkm.import')),
            fn ($t) => $t->post(route('platform.lkm.import.stage'), ['file' => $this->file($this->mixedFile()), 'confirm_simulated' => '1']),
            fn ($t) => $t->post(route('platform.lkm.import.commit'), ['confirm_simulated' => '1']),
            fn ($t) => $t->delete(route('platform.lkm.import.cancel')),
            fn ($t) => $t->get(route('platform.lkm.import.errors')),
            fn ($t) => $t->get(route('platform.lkm.import.template')),
            fn ($t) => $t->get(route('platform.lkm.export')),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin']))->assertForbidden();
        }

        $this->flushSession();
        auth()->logout();
        $this->get(route('platform.lkm.import'))->assertRedirect();
        $this->get(route('platform.lkm.export'))->assertRedirect();

        $this->assertSame($before, LkmDirectorySnapshot::count());
        $this->assertSame([], $this->staged());
    }

    // ---- template and the page ---------------------------------------------------------------

    public function test_the_template_has_the_columns_and_one_example_row(): void
    {
        $body = $this->as()->get(route('platform.lkm.import.template'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();

        $lines = array_values(array_filter(preg_split('/\r?\n/', ltrim($body, "\xEF\xBB\xBF"))));
        $this->assertSame(self::HEADER, $lines[0]);
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('KB99999,PA99999,', $lines[1]);
    }

    public function test_the_page_explains_the_rules_and_always_carries_the_simulated_warning(): void
    {
        $this->as()->get(route('platform.lkm.import'))->assertOk()
            ->assertSee('Simulated data.')
            ->assertSee('This file contains simulated or test records only, not copied from the real LKM directory.')
            ->assertSee('Nothing is ever deleted')->assertSee('2 MB')->assertSee('5,000 rows')->assertSee('Download the template');

        $this->as()->get(route('platform.lkm'))->assertSee(route('platform.lkm.import'), false)->assertSee('Import CSV')->assertSee('Export CSV');
    }

    // ---- the confirmation box ----------------------------------------------------------------

    public function test_a_file_is_refused_unless_the_simulated_data_box_is_ticked(): void
    {
        foreach ([[], ['confirm_simulated' => '0'], ['confirm_simulated' => '']] as $extra) {
            $this->as()->post(route('platform.lkm.import.stage'), ['file' => $this->file($this->mixedFile())] + $extra)
                ->assertSessionHasErrors('confirm_simulated');
        }

        $this->assertSame([], $this->staged());
        $this->assertSame(0, AuditLog::where('action', 'lkm.records_imported')->count());
    }

    public function test_the_import_is_refused_unless_the_box_is_ticked_again_at_the_end(): void
    {
        $this->stage($this->mixedFile());
        $before = LkmDirectorySnapshot::count();

        $this->as()->post(route('platform.lkm.import.commit'), [])->assertSessionHasErrors('confirm_simulated');
        $this->as()->post(route('platform.lkm.import.commit'), ['confirm_simulated' => '0'])->assertSessionHasErrors('confirm_simulated');

        $this->assertSame($before, LkmDirectorySnapshot::count());
        $this->assertCount(1, $this->staged(), 'the file still waits');
    }

    // ---- preview: nothing is written -----------------------------------------------------------

    public function test_the_preview_counts_new_updated_unchanged_and_wrong_rows_and_writes_nothing(): void
    {
        $records = LkmDirectorySnapshot::count();
        $bad = 'KB1,PA1,X,Nope,31/12/2026,2026-01-01,not-an-email,,';

        $response = $this->preview($this->mixedFile().$bad."\n");

        $response->assertOk()->assertSee('5 rows: 2 new, 1 update, 1 unchanged, 1 error')
            ->assertSee('Records that will change')->assertSee('KB10234')->assertSee('city')
            ->assertSee('1 row has errors')->assertSee('Import valid rows only (3)');

        $this->assertSame($records, LkmDirectorySnapshot::count());
        $this->assertNull(LkmDirectorySnapshot::where('kb_number', 'KB30001')->first());
        $this->assertNotSame('Changed City', $this->record('KB10234')->city);
        $this->assertSame(0, AuditLog::where('action', 'lkm.records_imported')->count());
    }

    public function test_a_clean_file_offers_the_plain_import_button(): void
    {
        $this->preview($this->csv(['KB30001,PA40001,Aminah binti Said,Aktif,2025-01-01,2028-01-01,,,']))
            ->assertSee('1 row: 1 new, 0 updates, 0 errors')->assertSee('Import 1 row')->assertDontSee('valid rows only');
    }

    public function test_every_kind_of_wrong_row_is_named_by_row_and_column(): void
    {
        $page = $this->preview($this->csv([
            'KB3,PA40001,Name One,Aktif,2025-01-01,2028-01-01,,,',                    // row 2: KB format
            'KB30002,PA4,Name Two,Aktif,2025-01-01,2028-01-01,,,',                    // row 3: PA format
            'KB30003,PA40003,N,Aktif,2025-01-01,2028-01-01,,,',                       // row 4: name too short
            'KB30004,PA40004,Name Four,Maybe,2025-01-01,2028-01-01,,,',               // row 5: status
            'KB30005,PA40005,Name Five,Aktif,2025-02-31,2028-01-01,,,',               // row 6: not a real date
            'KB30006,PA40006,Name Six,Aktif,2028-01-01,2025-01-01,,,',                // row 7: until before from
            'KB30007,PA40007,Name Seven,Aktif,2025-01-01,2028-01-01,nope,,',          // row 8: email
            ',PA40008,Name Eight,Aktif,2025-01-01,2028-01-01,,,',                     // row 9: KB missing
            'KB30009,PA40009,Name Nine,Aktif,2025-01-01,2028-01-01,,,,extra',         // row 10: too many values
        ]))->assertOk();

        $page->assertSee('9 rows: 0 new, 0 updates, 9 errors');

        $errors = collect(app(\App\Services\LkmCsvImporter::class)->plan(Storage::disk('lkm_imports')->get($this->staged()[0]))->errors)
            ->mapWithKeys(fn ($e) => [$e['row'] => $e['field']]);

        $this->assertSame([2 => 'kb_number', 3 => 'pa_number', 4 => 'full_name', 5 => 'status', 6 => 'pa_valid_from', 7 => 'pa_valid_until', 8 => 'email', 9 => 'kb_number', 10 => '(row)'], $errors->all());
    }

    public function test_the_same_kb_number_twice_in_one_file_is_an_error_on_the_second(): void
    {
        $this->preview($this->csv([
            'KB30001,PA40001,Aminah binti Said,Aktif,2025-01-01,2028-01-01,,,',
            'kb30001,PA40001,Aminah Again,Aktif,2025-01-01,2028-01-01,,,',
        ]))->assertSee('2 rows: 1 new, 0 updates, 1 error')->assertSee('appears again in this file (first on row 2)');
    }

    public function test_kb_pa_and_status_are_tidied_before_they_are_checked(): void
    {
        $this->stage($this->csv(['  kb30001 , pa40001 ,  Aminah binti Said  , aktif ,2025-01-01,2028-01-01,,,']));
        $this->commit()->assertRedirect(route('platform.lkm'));

        $record = $this->record('KB30001');
        $this->assertSame(['PA40001', 'Aminah binti Said', 'Aktif'], [$record->pa_number, $record->full_name, $record->status]);
    }

    // ---- the file as a whole ---------------------------------------------------------------------

    public function test_a_file_with_the_wrong_shape_is_turned_down_with_a_reason_and_is_not_kept(): void
    {
        $cases = [
            'The file is empty.' => '',
            'Missing column: pa_valid_until.' => "kb_number,pa_number,full_name,status,pa_valid_from\nKB30001,PA40001,A B,Aktif,2025-01-01\n",
            'Unknown column: nickname.' => self::HEADER.",nickname\nKB30001,PA40001,A B,Aktif,2025-01-01,2028-01-01,,,,x\n",
            'A column appears twice.' => self::HEADER.",city\nKB30001,PA40001,A B,Aktif,2025-01-01,2028-01-01,,,,,\n",
            'separated by semicolons' => "kb_number;pa_number;full_name;status;pa_valid_from;pa_valid_until\nKB30001;PA40001;A B;Aktif;2025-01-01;2028-01-01\n",
            'The file is not UTF-8' => self::HEADER."\nKB30001,PA40001,Jos\xE9 Caf\xE9,Aktif,2025-01-01,2028-01-01,,,\n",
            'a header but no records' => self::HEADER."\n\n\n",
        ];

        foreach ($cases as $reason => $contents) {
            $this->stage($contents)->assertRedirect(route('platform.lkm.import'));
            $this->as()->get(route('platform.lkm.import'))->assertSee('The file was not accepted')->assertSee($reason);
            $this->assertSame([], $this->staged(), $reason);
        }

        $this->assertNull(LkmDirectorySnapshot::where('kb_number', 'KB30001')->first());
    }

    public function test_a_byte_order_mark_and_header_spelling_variants_are_fine(): void
    {
        $this->stage("\xEF\xBB\xBFKB Number,PA-Number,Full Name,STATUS,PA Valid From,PA Valid Until\r\nKB30001,PA40001,Aminah binti Said,Aktif,2025-01-01,2028-01-01\r\n")
            ->assertSessionHasNoErrors();
        $this->as()->get(route('platform.lkm.import'))->assertSee('1 row: 1 new');
    }

    public function test_blank_lines_and_quoted_commas_and_new_lines_are_read_correctly(): void
    {
        $this->stage(self::HEADER."\n\nKB30001,PA40001,\"Aminah, binti Said\",Aktif,2025-01-01,2028-01-01,,\"Kuala\nLumpur\",\n\n");
        $this->commit()->assertRedirect(route('platform.lkm'));

        $record = $this->record('KB30001');
        $this->assertSame(['Aminah, binti Said', "Kuala\nLumpur"], [$record->full_name, $record->city]);
    }

    public function test_the_size_and_row_limits_are_enforced(): void
    {
        config(['lkm.import.max_rows' => 3]);
        $rows = array_map(fn ($i) => "KB3000{$i},PA4000{$i},Name {$i} Abc,Aktif,2025-01-01,2028-01-01,,,", [1, 2, 3, 4]);

        $this->stage($this->csv(array_slice($rows, 0, 3)))->assertSessionHasNoErrors();
        $this->as()->get(route('platform.lkm.import'))->assertSee('3 rows: 3 new');

        $this->stage($this->csv($rows));
        $this->as()->get(route('platform.lkm.import'))->assertSee('more than 3 rows');
        $this->assertSame([], $this->staged());

        config(['lkm.import.max_bytes' => 1024]);
        $this->as()->post(route('platform.lkm.import.stage'), ['file' => UploadedFile::fake()->createWithContent('big.csv', str_repeat('x', 2048)), 'confirm_simulated' => '1'])
            ->assertSessionHasErrors('file');
    }

    public function test_only_csv_files_are_accepted(): void
    {
        $this->as()->post(route('platform.lkm.import.stage'), ['file' => $this->file($this->mixedFile(), 'records.exe'), 'confirm_simulated' => '1'])->assertSessionHasErrors('file');
        $this->as()->post(route('platform.lkm.import.stage'), ['confirm_simulated' => '1'])->assertSessionHasErrors('file');
        $this->assertSame([], $this->staged());
    }

    // ---- saving ------------------------------------------------------------------------------------

    public function test_confirming_saves_new_and_changed_rows_and_writes_one_audit_entry_with_counts_only(): void
    {
        $this->stage($this->mixedFile());
        $untouched = LkmDirectorySnapshot::count();

        $this->commit()->assertRedirect(route('platform.lkm'))
            ->assertSessionHas('status', 'Import done: 2 new, 1 updated, 1 unchanged.');

        $this->assertSame($untouched + 2, LkmDirectorySnapshot::count());
        $this->assertSame(['PA40001', 'Aminah binti Said', 'Pahang'], [$this->record('KB30001')->pa_number, $this->record('KB30001')->full_name, $this->record('KB30001')->state]);
        $this->assertNull($this->record('KB30002')->email, 'an empty cell is empty');
        $this->assertSame('Changed City', $this->record('KB10234')->city);

        $entries = AuditLog::where('action', 'lkm.records_imported')->get();
        $this->assertCount(1, $entries);
        $log = $entries->first();
        $this->assertSame($this->admin->id, $log->actor_user_id);
        $this->assertSame(['rows' => 4, 'new' => 2, 'updated' => 1, 'unchanged' => 1, 'skipped' => 0, 'linked_updated' => 1, 'valid_rows_only' => false], collect($log->metadata)->except('summary')->all());

        $everything = json_encode($log->metadata);
        foreach (['Aminah', 'Bala', 'KB30001', 'Changed City', 'example.com'] as $private) {
            $this->assertStringNotContainsString($private, $everything, 'the entry holds counts, not the rows');
        }

        // The file is gone and the page is clean again.
        $this->assertSame([], $this->staged());
        $this->as()->get(route('platform.lkm.import'))->assertDontSee('Records that will change');
    }

    public function test_importing_the_same_file_again_changes_nothing(): void
    {
        $this->stage($this->mixedFile());
        $this->commit();

        $this->stage($this->mixedFile());
        $this->as()->get(route('platform.lkm.import'))->assertSee('4 rows: 0 new, 0 updates, 4 unchanged, 0 errors')->assertSee('nothing to import');

        $count = LkmDirectorySnapshot::count();
        $this->commit()->assertSessionHas('error');

        $this->assertSame($count, LkmDirectorySnapshot::count());
        $this->assertSame(1, AuditLog::where('action', 'lkm.records_imported')->count());
    }

    public function test_a_file_with_errors_needs_valid_rows_only_and_then_skips_the_wrong_rows(): void
    {
        $this->stage($this->mixedFile().'KB1,PA1,X,Nope,x,y,,,'."\n");
        $before = LkmDirectorySnapshot::count();

        $this->commit()->assertSessionHas('error', fn ($m) => str_contains($m, 'rows with errors'));
        $this->assertSame($before, LkmDirectorySnapshot::count());

        $this->commit(['valid_only' => '1'])->assertRedirect(route('platform.lkm'))
            ->assertSessionHas('status', 'Import done: 2 new, 1 updated, 1 unchanged, 1 skipped because of errors.');

        $this->assertSame($before + 2, LkmDirectorySnapshot::count());
        $log = AuditLog::where('action', 'lkm.records_imported')->firstOrFail();
        $this->assertSame([1, true], [$log->metadata['skipped'], $log->metadata['valid_rows_only']]);
    }

    public function test_it_is_all_or_nothing_when_something_fails_while_saving(): void
    {
        $this->stage($this->mixedFile());
        $before = LkmDirectorySnapshot::count();
        $city = $this->record('KB10234')->city;

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->commit()->assertSessionHas('error', fn ($m) => str_contains($m, 'Nothing was saved'));

        $this->assertSame($before, LkmDirectorySnapshot::count());
        $this->assertSame($city, $this->record('KB10234')->city);
        $this->assertCount(1, $this->staged(), 'the file waits so it can be tried again');
    }

    public function test_if_the_directory_changes_after_the_preview_nothing_is_saved_until_it_is_shown_again(): void
    {
        $this->stage($this->csv([
            'KB30001,PA40001,Aminah binti Said,Aktif,2025-01-01,2028-01-01,,,',
            'KB30002,PA40002,Bala a/l Raman,Aktif,2025-01-01,2028-01-01,,,',
        ]));

        // Another admin adds KB30002 after the preview: the preview no longer describes what would happen.
        LkmDirectorySnapshot::create(['kb_number' => 'KB30002', 'pa_number' => 'PA40002', 'full_name' => 'Someone Else', 'status' => 'Aktif', 'pa_valid_from' => '2025-01-01', 'pa_valid_until' => '2028-01-01']);

        $this->commit()->assertSessionHas('error', fn ($m) => str_contains($m, 'directory changed since the preview'));

        $this->assertNull(LkmDirectorySnapshot::where('kb_number', 'KB30001')->first(), 'nothing from the file was saved');
        $this->assertSame('Someone Else', $this->record('KB30002')->full_name);

        // With the new numbers on screen, confirming again works.
        $this->as()->get(route('platform.lkm.import'))->assertSee('2 rows: 1 new, 1 update');
        $this->commit()->assertRedirect(route('platform.lkm'));
        $this->assertSame('Bala a/l Raman', $this->record('KB30002')->full_name);
    }

    public function test_an_import_never_deletes_and_never_changes_a_kb_number(): void
    {
        $count = LkmDirectorySnapshot::count();
        $this->stage($this->csv(['KB30001,PA40001,Aminah binti Said,Aktif,2025-01-01,2028-01-01,,,']));
        $this->commit();

        $this->assertSame($count + 1, LkmDirectorySnapshot::count(), 'the 20 records that were not in the file are all still there');
        $this->assertSame(0, AuditLog::where('action', 'like', '%deleted%')->count());
    }

    public function test_a_record_a_counselor_is_linked_to_can_be_updated_but_the_account_is_not_touched(): void
    {
        $profile = $this->profile('KB10234');
        $record = $this->record('KB10234');
        $name = $profile->fresh()->user->name;

        $this->stage($this->csv(["KB10234,{$record->pa_number},Renamed Person,Digantung,{$record->pa_valid_from->format('Y-m-d')},{$record->pa_valid_until->format('Y-m-d')},{$record->email},{$record->city},{$record->state}"]));
        $this->as()->get(route('platform.lkm.import'))->assertSee('1 of the updates change a record that a counselor account refers to');
        $this->commit();

        $this->assertSame(['Renamed Person', 'Digantung', 'KB10234'], [$record->fresh()->full_name, $record->fresh()->status, $record->fresh()->kb_number]);
        $this->assertSame($name, $profile->fresh()->user->name);
        $this->assertSame($profile->verification_status, $profile->fresh()->verification_status);
        $this->assertSame(1, AuditLog::where('action', 'lkm.records_imported')->firstOrFail()->metadata['linked_updated']);
    }

    public function test_an_optional_column_left_out_keeps_the_value_and_an_empty_cell_clears_it(): void
    {
        $record = $this->record('KB10234');
        $this->assertNotNull($record->email);
        $dates = "{$record->pa_valid_from->format('Y-m-d')},{$record->pa_valid_until->format('Y-m-d')}";

        // No email/city/state columns at all, but a new name: the rest stays.
        $this->stage("kb_number,pa_number,full_name,status,pa_valid_from,pa_valid_until\nKB10234,{$record->pa_number},New Name Here,Aktif,{$dates}\n");
        $this->commit();
        $fresh = $this->record('KB10234');
        $this->assertSame(['New Name Here', $record->email, $record->city], [$fresh->full_name, $fresh->email, $fresh->city]);

        // The email column is there and empty: cleared.
        $this->stage("kb_number,pa_number,full_name,status,pa_valid_from,pa_valid_until,email\nKB10234,{$record->pa_number},New Name Here,Aktif,{$dates},\n");
        $this->commit();
        $this->assertNull($this->record('KB10234')->email);
    }

    // ---- cancel, errors, old files ------------------------------------------------------------------

    public function test_cancelling_deletes_the_waiting_file_and_saves_nothing(): void
    {
        $this->stage($this->mixedFile());
        $this->assertCount(1, $this->staged());

        $this->as()->delete(route('platform.lkm.import.cancel'))->assertRedirect(route('platform.lkm.import'));

        $this->assertSame([], $this->staged());
        $this->assertNull(LkmDirectorySnapshot::where('kb_number', 'KB30001')->first());
        $this->commit()->assertSessionHas('error', fn ($m) => str_contains($m, 'no file waiting'));
    }

    public function test_a_new_upload_replaces_the_one_waiting(): void
    {
        $this->stage($this->mixedFile());
        $this->stage($this->csv(['KB30009,PA40009,Nine Nine,Aktif,2025-01-01,2028-01-01,,,']));

        $this->assertCount(1, $this->staged());
        $this->as()->get(route('platform.lkm.import'))->assertSee('1 row: 1 new');
    }

    public function test_files_left_behind_for_a_day_are_removed_with_the_next_upload(): void
    {
        $this->stage($this->mixedFile());
        $old = $this->staged()[0];
        $this->as()->flushSession();

        // A different admin session (nothing remembers the first file) uploads two days later.
        Carbon::setTestNow(now()->addDays(2));
        $this->stage($this->csv(['KB30009,PA40009,Nine Nine,Aktif,2025-01-01,2028-01-01,,,']));

        $this->assertNotContains($old, $this->staged());
        $this->assertCount(1, $this->staged());
    }

    public function test_the_error_report_lists_row_column_and_problem_without_the_row_content(): void
    {
        $this->stage($this->csv([
            'KB30001,PA40001,Aminah binti Said,Aktif,2025-01-01,2028-01-01,,,',
            '=CMD(),PA40002,Secret Person Name,Aktif,2025-01-01,2028-01-01,secret@example.com,,',
        ]));

        $body = ltrim($this->as()->get(route('platform.lkm.import.errors'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent(), "\xEF\xBB\xBF");

        $this->assertStringStartsWith('Row,"KB number",Column,Problem', $body);
        $this->assertStringContainsString("3,'=CMD(),kb_number,", $body, 'a cell that starts with = is made safe');
        $this->assertStringNotContainsString('Secret Person Name', $body);
        $this->assertStringNotContainsString('secret@example.com', $body);

        $this->cancelIt();
        $this->as()->get(route('platform.lkm.import.errors'))->assertRedirect(route('platform.lkm.import'));
    }

    private function cancelIt(): void
    {
        $this->as()->delete(route('platform.lkm.import.cancel'));
    }

    // ---- export ------------------------------------------------------------------------------------

    public function test_the_export_has_every_record_in_the_import_columns_and_is_audited(): void
    {
        $body = $this->as()->get(route('platform.lkm.export'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".self::HEADER."\n", $body);
        $rows = array_filter(explode("\n", trim(substr($body, 3))));
        $this->assertCount(1 + 20, $rows);
        $this->assertStringContainsString('KB10234,PA20345,"Ahmad Zulkarnain bin Yusof",Aktif,2024-01-01,2027-06-30', $body);

        $log = AuditLog::where('action', 'lkm.records_exported')->firstOrFail();
        $this->assertSame([$this->admin->id, 20, 'csv', 'none'], [$log->actor_user_id, $log->metadata['rows'], $log->metadata['format'], $log->metadata['filters']]);
        $this->assertStringNotContainsString('Ahmad', json_encode($log->metadata));
    }

    public function test_the_export_follows_the_search_and_filters_of_the_list(): void
    {
        $body = $this->as()->get(route('platform.lkm.export', ['status' => 'Digantung']))->streamedContent();

        $this->assertCount(2, array_filter(explode("\n", trim(substr($body, 3)))));
        $this->assertStringContainsString('KB14701', $body);
        $this->assertStringNotContainsString('KB10234', $body);
        $this->assertSame(1, AuditLog::where('action', 'lkm.records_exported')->firstOrFail()->metadata['rows']);
    }

    public function test_cells_that_could_run_as_formulas_are_made_safe_in_the_export(): void
    {
        $this->record('KB10234')->update(['full_name' => '=HYPERLINK("http://evil.example")', 'city' => '@SUM(1+1)', 'state' => '-2+3']);

        $body = $this->as()->get(route('platform.lkm.export', ['q' => 'KB10234']))->streamedContent();

        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil.example\"\")\"", $body);
        $this->assertStringContainsString("'@SUM(1+1)", $body);
        $this->assertStringContainsString("'-2+3", $body);
    }

    public function test_an_export_that_is_too_big_is_refused_before_it_starts(): void
    {
        config(['lkm.export_max_rows' => 5]);

        $this->as()->get(route('platform.lkm.export'))->assertRedirect(route('platform.lkm'))->assertSessionHas('error', fn ($m) => str_contains($m, 'more than the 5'));
        $this->assertSame(0, AuditLog::where('action', 'lkm.records_exported')->count());

        $this->as()->get(route('platform.lkm.export', ['status' => 'Digantung']))->assertOk();
    }

    public function test_an_exported_file_can_be_imported_again_and_changes_nothing(): void
    {
        $body = $this->as()->get(route('platform.lkm.export'))->streamedContent();

        $this->stage($body);
        $this->as()->get(route('platform.lkm.import'))->assertSee('20 rows: 0 new, 0 updates, 20 unchanged, 0 errors');
    }
}
