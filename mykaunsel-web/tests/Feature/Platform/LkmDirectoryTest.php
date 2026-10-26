<?php

namespace Tests\Feature\Platform;

use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\LkmDirectorySnapshot;
use App\Models\Organization;
use App\Models\User;
use App\Services\LkmVerificationService;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Feature\Counselor\CounselorTestCase;

class LkmDirectoryTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function record(string $kb): LkmDirectorySnapshot
    {
        return LkmDirectorySnapshot::where('kb_number', $kb)->firstOrFail();
    }

    private function names(array $query = []): array
    {
        return $this->as()->get(route('platform.lkm', $query))->assertOk()->viewData('records')->getCollection()->pluck('kb_number')->all();
    }

    private function valid(array $override = []): array
    {
        return $override + [
            'kb_number' => 'kb20001', 'pa_number' => 'pa30001', 'full_name' => '  Zainal Abidin bin Ali  ', 'status' => 'Aktif',
            'pa_valid_from' => '2025-01-01', 'pa_valid_until' => '2029-01-01', 'email' => 'zainal@example.com', 'city' => 'Kuantan', 'state' => 'Pahang',
        ];
    }

    private function store(array $data)
    {
        return $this->as()->post(route('platform.lkm.store'), $data);
    }

    private function log(string $action, LkmDirectorySnapshot $record)
    {
        return AuditLog::where('action', $action)->where('target_type', 'lkm_record')->where('target_id', $record->id)->latest('id')->first();
    }

    // ---- access ----------------------------------------------------------------------------

    public function test_only_platform_admins_can_see_or_change_the_directory(): void
    {
        $record = $this->record('KB10234');
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $before = $record->full_name;

        foreach ([
            fn ($t) => $t->get(route('platform.lkm')),
            fn ($t) => $t->get(route('platform.lkm.create')),
            fn ($t) => $t->getJson(route('platform.lkm.lookup', ['kb' => 'KB10234'])),
            fn ($t) => $t->post(route('platform.lkm.store'), $this->valid()),
            fn ($t) => $t->get(route('platform.lkm.show', $record)),
            fn ($t) => $t->put(route('platform.lkm.update', $record), $this->valid()),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin']))->assertForbidden();
        }

        $this->flushSession();
        auth()->logout();
        $this->get(route('platform.lkm'))->assertRedirect();

        $this->assertSame($before, $record->fresh()->full_name);
        $this->assertNull(LkmDirectorySnapshot::where('kb_number', 'KB20001')->first());
    }

    public function test_the_sidebar_link_is_live_and_records_cannot_be_deleted(): void
    {
        $this->as()->get(route('platform.lkm'))->assertOk()->assertSee(route('platform.lkm'), false)->assertSee('aria-current="page"', false);

        $record = $this->record('KB10234');
        $this->as()->delete('/platform/lkm-directory/'.$record->id)->assertStatus(405);
        $this->assertNotNull($record->fresh());
    }

    // ---- list ------------------------------------------------------------------------------

    public function test_the_list_shows_every_record_by_name_with_status_pa_and_whether_they_are_on_mykaunsel(): void
    {
        $response = $this->as()->get(route('platform.lkm'))->assertOk();
        $names = $response->viewData('records')->getCollection()->pluck('full_name')->all();

        $this->assertSame(collect($names)->sort()->values()->all(), $names, 'alphabetical');
        $this->assertSame(15, count($names), 'first page of 15');
        $response->assertSee('20')->assertSee('records.')->assertSee('Aktif')->assertSee('KB10234')->assertSee('View / edit');

        $row = $response->viewData('records')->getCollection()->firstWhere('kb_number', 'KB10234');
        $this->assertSame(1, $row->counselor_profiles_count);
    }

    public function test_search_by_name_kb_or_email_and_wildcards_are_plain_text(): void
    {
        $this->assertSame(['KB11489'], $this->names(['q' => 'lee mei']));
        $this->assertSame(['KB11489'], $this->names(['q' => 'kb11489']));
        $this->assertSame(['KB11489'], $this->names(['q' => 'lee.meiling@example']));
        $this->assertSame([], $this->names(['q' => 'tiada-siapa']));
        $this->assertSame([], $this->names(['q' => '%']));
        $this->assertSame([], $this->names(['q' => 'KB1_489']));
    }

    public function test_filters_status_pa_state_and_registered(): void
    {
        $this->assertEqualsCanonicalizing(['KB14478'], $this->names(['status' => 'Tidak Aktif']));
        $this->assertEqualsCanonicalizing(['KB14701'], $this->names(['status' => 'Digantung']));
        $this->assertSame(18, $this->as()->get(route('platform.lkm', ['status' => 'Aktif']))->viewData('records')->total());

        $this->assertEqualsCanonicalizing(['KB10891'], $this->names(['state' => 'Wilayah Persekutuan']));
        $this->assertSame([], $this->names(['state' => 'Narnia']));

        // PA: whole days — valid until today is still valid and "expiring", yesterday is expired.
        Carbon::setTestNow(Carbon::parse('2026-10-24 15:00:00', config('app.timezone')));
        LkmDirectorySnapshot::query()->update(['pa_valid_until' => '2030-01-01']);
        $this->record('KB10234')->update(['pa_valid_until' => '2026-10-24']);
        $this->record('KB10567')->update(['pa_valid_until' => '2026-11-23']);
        $this->record('KB10891')->update(['pa_valid_until' => '2026-11-24']);
        $this->record('KB11023')->update(['pa_valid_until' => '2026-10-23']);

        $this->assertEqualsCanonicalizing(['KB10234', 'KB10567'], $this->names(['pa' => 'expiring']));
        $this->assertSame(['KB11023'], $this->names(['pa' => 'expired']));
        $this->assertNotContains('KB11023', $this->names(['pa' => 'valid', 'q' => 'KB1']));
        $this->assertContains('KB10234', $this->names(['pa' => 'valid', 'q' => 'KB10234']));

        // On MyKaunsel or not.
        $yes = $this->names(['registered' => 'yes']);
        $this->assertContains('KB10234', $yes);
        $this->assertContains('KB11023', $yes);
        $this->assertNotContains('KB14478', $yes);
        $no = $this->names(['registered' => 'no']);
        $this->assertContains('KB14478', $no);
        $this->assertNotContains('KB10234', $no);
    }

    public function test_filters_combine_and_bad_values_are_ignored_and_explained(): void
    {
        $this->assertSame(['KB10891'], $this->names(['registered' => 'yes', 'state' => 'Wilayah Persekutuan', 'status' => 'Aktif', 'q' => 'tan']));
        $this->assertSame([], $this->names(['registered' => 'no', 'status' => 'Aktif', 'q' => 'tan']));

        $response = $this->as()->get(route('platform.lkm', ['status' => 'Hilang', 'pa' => 'forever', 'registered' => 'maybe', 'q' => str_repeat('a', 101)]))->assertOk();
        $response->assertSee('Some filters in the link were not valid and were ignored')->assertSee('Unknown status.')->assertSee('Unknown PA status.');
        $this->assertSame(15, $response->viewData('records')->count(), 'nothing was filtered by the bad values');
        $this->as()->get(route('platform.lkm', ['q' => ['array']]))->assertOk();
    }

    public function test_pagination_and_empty_states(): void
    {
        $this->assertCount(5, $this->names(['page' => 2]));
        $this->as()->get(route('platform.lkm', ['page' => 9]))->assertRedirect(route('platform.lkm', ['page' => 2]));

        $this->as()->get(route('platform.lkm', ['q' => 'zzz']))->assertSee('No records match your search')->assertSee('Clear filters');

        \DB::table('lkm_directory_snapshot')->delete();
        $this->as()->get(route('platform.lkm'))->assertSee('The directory is empty')->assertSee('Add the first record');
    }

    // ---- add -------------------------------------------------------------------------------

    public function test_adding_a_record_cleans_the_input_audits_it_and_lets_that_counselor_register(): void
    {
        $this->store($this->valid())->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('status');

        $record = $this->record('KB20001');
        $this->assertSame(['PA30001', 'Zainal Abidin bin Ali', 'Aktif', 'zainal@example.com', 'Pahang'], [$record->pa_number, $record->full_name, $record->status, $record->email, $record->state]);
        $this->assertSame('2029-01-01', $record->pa_valid_until->toDateString());

        $log = $this->log('lkm.record_created', $record);
        $this->assertSame([$this->admin->id, 'Zainal Abidin bin Ali', 'lkm'], [$log->actor_user_id, $log->target_name, $log->category]);
        $this->assertSame('KB20001', $log->metadata['kb_number']);

        // The whole point of the directory: registration matches against it.
        $service = app(LkmVerificationService::class);
        $this->assertTrue($service->verify('KB20001', 'PA30001', 'Zainal Abidin bin Ali'));
        $this->assertNull($service->verificationFailureReason('KB20001', 'PA30001', 'Zainal Abidin bin Ali'));

        $this->as()->get(route('platform.lkm.show', $record))->assertOk()->assertSee('Zainal Abidin bin Ali')->assertSee('No counselor has registered with this KB number yet')->assertSee('Added KB20001');
    }

    public function test_optional_fields_can_be_left_empty(): void
    {
        $this->store($this->valid(['email' => '', 'city' => '', 'state' => '  ']))->assertSessionHasNoErrors();

        $record = $this->record('KB20001');
        $this->assertSame([null, null, null], [$record->email, $record->city, $record->state]);
    }

    public function test_bad_input_is_refused_and_nothing_is_created(): void
    {
        $before = LkmDirectorySnapshot::count();

        $this->store($this->valid(['kb_number' => 'KB1234']))->assertSessionHasErrors('kb_number');
        $this->store($this->valid(['kb_number' => 'AB12345']))->assertSessionHasErrors('kb_number');
        $this->store($this->valid(['kb_number' => 'KB10234']))->assertSessionHasErrors('kb_number'); // already there
        $this->store($this->valid(['pa_number' => 'PA1']))->assertSessionHasErrors('pa_number');
        $this->store($this->valid(['full_name' => '']))->assertSessionHasErrors('full_name');
        $this->store($this->valid(['status' => 'Hilang']))->assertSessionHasErrors('status');
        $this->store($this->valid(['pa_valid_from' => '01/01/2025']))->assertSessionHasErrors('pa_valid_from');
        $this->store($this->valid(['pa_valid_until' => '2024-12-31']))->assertSessionHasErrors('pa_valid_until'); // before "from"
        $this->store($this->valid(['email' => 'bukan-emel']))->assertSessionHasErrors('email');

        $this->assertSame($before, LkmDirectorySnapshot::count());
        $this->assertSame(0, AuditLog::where('action', 'lkm.record_created')->count());
    }

    public function test_a_failed_audit_entry_means_no_record_is_added(): void
    {
        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));

        $this->withoutExceptionHandling();
        try {
            $this->store($this->valid());
            $this->fail('the request should have failed');
        } catch (RuntimeException) {
        }

        $this->assertNull(LkmDirectorySnapshot::where('kb_number', 'KB20001')->first());
    }

    // ---- change ----------------------------------------------------------------------------

    private function update(string $kb, array $data)
    {
        return $this->as()->put(route('platform.lkm.update', $this->record($kb)), $data);
    }

    private function formFor(string $kb, array $override = []): array
    {
        $r = $this->record($kb);

        return $override + [
            'pa_number' => $r->pa_number, 'full_name' => $r->full_name, 'status' => $r->status,
            'pa_valid_from' => $r->pa_valid_from->toDateString(), 'pa_valid_until' => $r->pa_valid_until->toDateString(),
            'email' => $r->email, 'city' => $r->city, 'state' => $r->state,
        ];
    }

    public function test_changing_a_record_saves_it_and_audits_only_what_changed_old_to_new(): void
    {
        $record = $this->record('KB10234');
        $oldUntil = $record->pa_valid_until->toDateString();

        $this->update('KB10234', $this->formFor('KB10234', ['status' => 'Digantung', 'pa_valid_until' => '2030-06-30', 'city' => '']))
            ->assertSessionHasNoErrors()->assertRedirect(route('platform.lkm.show', $record))->assertSessionHas('status', 'Record updated.');

        $record->refresh();
        $this->assertSame(['Digantung', '2030-06-30', null], [$record->status, $record->pa_valid_until->toDateString(), $record->city]);

        $log = $this->log('lkm.record_updated', $record);
        $this->assertSame('KB10234', $log->metadata['kb_number']);
        $this->assertSame([
            'status' => ['old' => 'Aktif', 'new' => 'Digantung'],
            'pa_valid_until' => ['old' => $oldUntil, 'new' => '2030-06-30'],
            'city' => ['old' => 'Kuantan', 'new' => null],
        ], $log->metadata['changes']);
        $this->assertSame($this->admin->id, $log->actor_user_id);
    }

    public function test_saving_without_changes_changes_and_logs_nothing(): void
    {
        $this->update('KB10234', $this->formFor('KB10234'))->assertSessionHas('status', 'No changes to save.');

        $this->assertNull($this->log('lkm.record_updated', $this->record('KB10234')));
    }

    public function test_the_kb_number_cannot_be_changed(): void
    {
        $record = $this->record('KB10234');

        $this->update('KB10234', $this->formFor('KB10234', ['kb_number' => 'KB99999', 'full_name' => 'Nama Baharu Sahaja']))->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame(['KB10234', 'Nama Baharu Sahaja'], [$record->kb_number, $record->full_name]);
        $this->assertSame(['full_name'], array_keys($this->log('lkm.record_updated', $record)->metadata['changes']));
        $this->as()->get(route('platform.lkm.show', $record))->assertSee('The KB number cannot be changed');
    }

    public function test_changes_are_validated(): void
    {
        $record = $this->record('KB10234');

        $this->update('KB10234', $this->formFor('KB10234', ['full_name' => '']))->assertSessionHasErrors('full_name');
        $this->update('KB10234', $this->formFor('KB10234', ['status' => 'Hilang']))->assertSessionHasErrors('status');
        $this->update('KB10234', $this->formFor('KB10234', ['pa_valid_until' => '2000-01-01']))->assertSessionHasErrors('pa_valid_until');
        $this->update('KB10234', $this->formFor('KB10234', ['pa_number' => 'XX']))->assertSessionHasErrors('pa_number');

        $this->assertSame('Ahmad Zulkarnain bin Yusof', $record->fresh()->full_name);
        $this->assertNull($this->log('lkm.record_updated', $record));
    }

    public function test_a_lowercase_pa_number_is_accepted_and_stored_in_capitals(): void
    {
        $this->update('KB10234', $this->formFor('KB10234', ['pa_number' => 'pa55555']))->assertSessionHasNoErrors();

        $this->assertSame('PA55555', $this->record('KB10234')->pa_number);
    }

    public function test_a_failed_audit_entry_means_the_change_is_not_saved(): void
    {
        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->update('KB10234', $this->formFor('KB10234', ['status' => 'Digantung']));
            $this->fail('the request should have failed');
        } catch (RuntimeException) {
        }

        $this->assertSame('Aktif', $this->record('KB10234')->status);
    }

    public function test_marking_a_record_not_active_is_what_stops_a_counselor_registering_or_being_approved(): void
    {
        $service = app(LkmVerificationService::class);
        $this->assertTrue($service->verify('KB12624', 'PA22635', 'Chong Yee Sheng'));

        $this->update('KB12624', $this->formFor('KB12624', ['status' => 'Tidak Aktif']))->assertSessionHasNoErrors();

        $this->assertFalse($service->verify('KB12624', 'PA22635', 'Chong Yee Sheng'));
        $this->assertNotNull($this->record('KB12624'), 'still there, as a record that this person existed');
    }

    // ---- the record page ---------------------------------------------------------------------

    public function test_the_page_lists_the_counselors_on_mykaunsel_with_how_well_they_match(): void
    {
        $this->as()->get(route('platform.lkm.show', $this->record('KB11702')))->assertOk()
            ->assertSee('Nur Ain binti Hashim')->assertSee('Pending')->assertSee('Independent')
            ->assertSee('Does not fully match LKM')   // its date changed after she applied
            ->assertSee(route('platform.counselors.show', $this->profile('KB11702')), false)
            ->assertSee('Changes here are not copied to these accounts');

        $this->as()->get(route('platform.lkm.show', $this->record('KB11023')))->assertSee('Matches LKM');
        $this->as()->get(route('platform.lkm.show', $this->record('KB10234')))->assertSee('Organization');
    }

    public function test_the_history_lists_changes_and_links_to_the_audit_log(): void
    {
        $this->update('KB10234', $this->formFor('KB10234', ['status' => 'Digantung']));

        $page = $this->as()->get(route('platform.lkm.show', $this->record('KB10234')))->assertOk();
        $page->assertSee('LKM record updated')->assertSee('Aktif → Digantung')->assertSee('Platform Administrator')->assertSee('View full history')
            ->assertSee('target_type=lkm_record', false);

        $this->as()->get(route('platform.lkm.show', $this->record('KB12624')))->assertSee('No changes recorded yet');
    }

    public function test_the_audit_log_links_back_to_the_record(): void
    {
        $this->update('KB10234', $this->formFor('KB10234', ['status' => 'Digantung']));

        $this->as()->get(route('platform.audit-log', ['category' => 'lkm']))->assertOk()
            ->assertSee(route('platform.lkm.show', $this->record('KB10234')), false)->assertSee('LKM record updated');
    }

    public function test_an_unknown_record_is_a_404(): void
    {
        $this->as()->get('/platform/lkm-directory/999999')->assertNotFound();
        $this->as()->get('/platform/lkm-directory/abc')->assertNotFound();
        $this->as()->put('/platform/lkm-directory/999999', $this->valid())->assertNotFound();
    }

    // ---- list extras: banner, summary, sort, linked counselor, expired edge -----------------------

    public function test_the_page_always_says_the_data_is_simulated(): void
    {
        $this->as()->get(route('platform.lkm'))->assertOk()
            ->assertSee('Simulated dataset used for credential verification')
            ->assertSee('Simulated data.')->assertSee('not copied from the real LKM register')->assertSee('MoU')->assertSee('Future Work');

        // It is there on an empty directory and with a search too, and it has no close button.
        $this->as()->get(route('platform.lkm', ['q' => 'zzz']))->assertSee('Simulated data.');
        \DB::table('lkm_directory_snapshot')->delete();
        $this->as()->get(route('platform.lkm'))->assertSee('Simulated data.');
    }

    public function test_the_summary_counts_the_whole_directory_whatever_the_filters(): void
    {
        $expected = [
            'total' => 20,
            'active' => LkmDirectorySnapshot::where('status', 'Aktif')->count(),
            'expired' => LkmDirectorySnapshot::paStatus('expired')->count(),
            'linked' => LkmDirectorySnapshot::has('counselorProfiles')->count(),
        ];

        $this->assertSame($expected, $this->as()->get(route('platform.lkm'))->viewData('summary'));
        $this->assertSame($expected, $this->as()->get(route('platform.lkm', ['q' => 'tan', 'status' => 'Digantung']))->viewData('summary'));
    }

    public function test_sorting_by_name_kb_number_or_pa_expiry_is_stable_and_not_counted_as_a_filter(): void
    {
        LkmDirectorySnapshot::query()->update(['pa_valid_until' => '2030-01-01']);
        $this->record('KB10234')->update(['pa_valid_until' => '2026-01-01']);
        $this->record('KB11023')->update(['pa_valid_until' => '2027-03-01']);

        $kb = $this->names(['sort' => 'kb']);
        $this->assertSame(collect($kb)->sort()->values()->all(), $kb);

        $pa = $this->names(['sort' => 'pa']);
        $this->assertSame(['KB10234', 'KB11023'], array_slice($pa, 0, 2), 'soonest (already expired) first');

        $response = $this->as()->get(route('platform.lkm', ['sort' => 'kb']))->assertOk();
        $response->assertDontSee('Clear filters')->assertSee('20')->assertSee('records.');
        $this->assertSame('kb', $response->viewData('filters')->sort());

        // The order is kept when paging, and a made-up order is ignored and explained.
        $this->assertStringContainsString('sort=kb', $response->viewData('records')->nextPageUrl());
        $this->as()->get(route('platform.lkm', ['sort' => 'random']))->assertSee('Unknown sort order.');
        $this->assertSame($this->names(), $this->names(['sort' => 'random']));
    }

    public function test_the_list_names_the_linked_counselor_with_a_link_or_says_not_linked(): void
    {
        $linked = $this->profile('KB10234');
        $html = $this->as()->get(route('platform.lkm', ['q' => 'KB10234']))->assertOk()->getContent();

        $this->assertStringContainsString(route('platform.counselors.show', $linked), $html);
        $this->assertStringContainsString($linked->user->name, $html);

        $this->as()->get(route('platform.lkm', ['q' => 'KB14478']))->assertSee('Not linked');
    }

    public function test_expired_rows_get_a_red_edge_and_others_do_not(): void
    {
        LkmDirectorySnapshot::query()->update(['pa_valid_until' => '2035-01-01']);
        $this->as()->get(route('platform.lkm'))->assertDontSee('border-l-brand-red', false);

        $this->record('KB11023')->update(['pa_valid_until' => '2020-01-01']);
        $this->as()->get(route('platform.lkm', ['q' => 'KB11023']))->assertSee('border-l-brand-red', false);
    }

    public function test_deleting_is_not_possible_and_the_audit_catalogue_has_no_delete_for_lkm(): void
    {
        $this->assertArrayNotHasKey('lkm.record_deleted', config('audit.actions'));
        $this->assertArrayHasKey('lkm.record_created', config('audit.actions'));
    }

    // ---- live checks and the shared PA note ---------------------------------------------------

    public function test_the_lookup_tells_whether_a_kb_exists_and_who_else_uses_a_pa_number(): void
    {
        $record = $this->record('KB10234');

        $this->as()->getJson(route('platform.lkm.lookup', ['kb' => 'KB10234', 'pa' => $record->pa_number]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['kb' => ['kb_number' => 'KB10234', 'name' => $record->full_name], 'pa' => [['kb_number' => 'KB10234', 'name' => $record->full_name]]]);

        // Editing a record: its own PA number is not "also used".
        $this->as()->getJson(route('platform.lkm.lookup', ['pa' => $record->pa_number, 'except' => $record->id]))->assertOk()->assertExactJson(['kb' => null, 'pa' => []]);

        $this->as()->getJson(route('platform.lkm.lookup', ['kb' => 'KB99999', 'pa' => 'PA99999']))->assertOk()->assertExactJson(['kb' => null, 'pa' => []]);
        $this->as()->getJson(route('platform.lkm.lookup'))->assertOk()->assertExactJson(['kb' => null, 'pa' => []]);
    }

    public function test_the_lookup_refuses_anything_that_is_not_a_kb_or_pa_number(): void
    {
        foreach ([['kb' => 'KB1'], ['kb' => "KB12345' or 1=1"], ['pa' => 'XX12345'], ['pa' => ['PA12345']], ['except' => 'abc'], ['except' => '0']] as $query) {
            $this->as()->getJson(route('platform.lkm.lookup', $query))->assertStatus(422);
        }
    }

    public function test_saving_a_pa_number_another_record_already_has_works_but_says_so(): void
    {
        $first = $this->record('KB10234');

        $this->store($this->valid(['pa_number' => $first->pa_number]))->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($text) => str_contains($text, 'KB20001 added') && str_contains($text, "{$first->pa_number} is also on KB10234"));

        $this->update('KB20001', $this->formFor('KB20001', ['full_name' => 'Zainal bin Ali']))
            ->assertSessionHas('status', fn ($text) => str_starts_with($text, 'Record updated.') && str_contains($text, 'is also on KB10234'));

        $this->update('KB10234', $this->formFor('KB10234'))
            ->assertSessionHas('status', fn ($text) => str_contains($text, 'is also on KB20001'));
    }

    public function test_the_form_has_the_live_checks_and_the_name_warning(): void
    {
        $this->as()->get(route('platform.lkm.create'))->assertOk()
            ->assertSee(str_replace('/', '\/', route('platform.lkm.lookup')), false)->assertSee('x-model="kb"', false) // (@js writes slashes escaped)
            ->assertSee('Name must match the counselor’s registration name exactly. Changing it affects verification.', false);

        $this->as()->get(route('platform.lkm.show', $this->record('KB10234')))->assertOk()
            ->assertSee('mark this record', false)->assertSee('Tidak Aktif');
    }

    // ---- links from the Counselors page ----------------------------------------------------------

    public function test_the_counselor_page_links_to_its_lkm_record_and_says_so_when_there_is_none(): void
    {
        $record = $this->record('KB11023');

        $this->as()->get(route('platform.counselors.show', $this->profile('KB11023')))
            ->assertSee(route('platform.lkm.show', $record), false)->assertSee('Open the LKM record');

        $this->as()->get(route('platform.counselors.show', $this->profile('KB10234')))
            ->assertSee(route('platform.lkm.show', $this->record('KB10234')), false)->assertSee('Open in LKM Directory');

        $record->delete(); // (only possible directly in the database — the app never deletes records)
        $this->as()->get(route('platform.counselors.show', $this->profile('KB10891')))->assertSee('Open in LKM Directory');
        $this->record('KB10891')->delete();
        $this->as()->get(route('platform.counselors.show', $this->profile('KB10891')))->assertSee('Not in the directory');
    }
}
