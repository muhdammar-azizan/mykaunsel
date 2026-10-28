<?php

namespace Tests\Feature;

use App\Models\AdminInvitation;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The places where SQLite (the normal test run) and MySQL/MariaDB (the real server) behave differently, written down as tests.
 * Some run on both databases; the ones marked "MySQL only" are skipped on SQLite and run when the suite is run on MySQL
 * (docs/MYSQL_TESTING.md).
 */
class DatabaseCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private function mysqlOnly(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('MySQL only: SQLite does not behave like this. Run the suite on MySQL (docs/MYSQL_TESTING.md).');
        }
    }

    private function invitation(array $over = []): AdminInvitation
    {
        return AdminInvitation::create($over + [
            'name' => 'Person', 'email' => 'p'.uniqid().'@example.com', 'token_hash' => hash('sha256', uniqid('', true)), 'active_email' => null,
            'status' => 'revoked', 'expires_at' => now()->addDay(), 'last_sent_at' => now(),
        ]);
    }

    // ---- both databases -------------------------------------------------------------------------------------

    public function test_many_rows_may_have_null_in_a_unique_column_but_two_equal_values_may_not(): void
    {
        $this->invitation(['active_email' => null]);
        $this->invitation(['active_email' => null]);   // two NULLs: allowed on both databases
        $this->invitation(['active_email' => 'one@example.com', 'status' => 'pending']);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->invitation(['active_email' => 'one@example.com', 'status' => 'pending']);
    }

    public function test_a_four_byte_character_survives_a_round_trip(): void
    {
        $user = User::factory()->create(['name' => 'Aina 😊 Syafiqah']);

        $this->assertSame('Aina 😊 Syafiqah', $user->fresh()->name, 'utf8mb4 end to end');
    }

    public function test_money_in_whole_sen_comes_back_exactly(): void
    {
        $plan = SubscriptionPlan::create(['org_type' => 'university', 'name' => 'Big', 'min_members' => 1, 'max_members' => null, 'price_cents' => 100_000_000, 'active' => true, 'version' => 1]);
        $code = PromoCode::create(['code' => 'ROUND-1', 'type' => 'fixed', 'value' => 99_999_999, 'valid_from' => now(), 'valid_until' => now()->addDay(), 'applies_to' => 'all', 'version' => 1]);

        $this->assertSame(100_000_000, $plan->fresh()->price_cents);
        $this->assertSame(99_999_999, $code->fresh()->value);
        $this->assertIsInt($plan->fresh()->price_cents);
    }

    public function test_percentages_round_to_the_nearest_sen_in_php_not_in_the_database(): void
    {
        // Whole-number arithmetic, so the answer cannot depend on how a database rounds decimals or floats.
        foreach ([[5, 10, 1], [5, 30, 2], [4, 10, 0], [99999999, 33, 33000000], [1, 50, 1], [3, 50, 2]] as [$price, $percent, $expected]) {
            $this->assertSame($expected, \App\Services\SubscriptionAssignmentService::discount($price, 'percent', $percent), "{$percent}% of {$price}");
        }
    }

    public function test_a_time_written_in_utc_is_read_back_as_the_same_moment(): void
    {
        $moment = \Illuminate\Support\Carbon::parse('2027-03-28 01:30:00', 'UTC');   // around a daylight-saving change in many zones
        $code = PromoCode::create(['code' => 'TIME-1', 'type' => 'percent', 'value' => 10, 'valid_from' => $moment, 'valid_until' => $moment->copy()->addDay(), 'applies_to' => 'all', 'version' => 1]);

        $this->assertSame($moment->timestamp, $code->fresh()->valid_from->timestamp);
        $this->assertSame('2027-03-28 01:30:00', $code->fresh()->valid_from->utc()->format('Y-m-d H:i:s'));
    }

    // ---- MySQL only -----------------------------------------------------------------------------------------

    public function test_mysql_only_the_connection_talks_utc_whatever_the_server_zone_is(): void
    {
        $this->mysqlOnly();

        $this->assertSame('+00:00', DB::selectOne('select @@session.time_zone as tz')->tz);

        // TIMESTAMP columns are converted with the session zone: the stored moment must be the real one.
        $user = User::factory()->create(['created_at' => '2027-03-28 01:30:00']);
        $this->assertSame(\Illuminate\Support\Carbon::parse('2027-03-28 01:30:00', 'UTC')->timestamp, (int) DB::selectOne('select unix_timestamp(created_at) as t from users where id = ?', [$user->id])->t);
    }

    public function test_mysql_only_a_value_that_is_too_long_or_out_of_range_is_an_error_not_silently_cut(): void
    {
        $this->mysqlOnly();

        try {
            SubscriptionPlan::create(['org_type' => 'university', 'name' => str_repeat('x', 101), 'min_members' => 1, 'price_cents' => 1, 'active' => true, 'version' => 1]);
            $this->fail('a 101 character name was cut instead of refused');
        } catch (QueryException) {
            // expected: strict mode
        }

        $this->expectException(QueryException::class);
        SubscriptionPlan::create(['org_type' => 'university', 'name' => 'Negative', 'min_members' => 1, 'price_cents' => -1, 'active' => true, 'version' => 1]);   // unsigned column
    }

    public function test_mysql_only_text_is_compared_without_regard_to_capitals_and_the_code_is_stored_in_capitals(): void
    {
        $this->mysqlOnly();
        PromoCode::create(['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'valid_from' => now(), 'valid_until' => now()->addDay(), 'applies_to' => 'all', 'version' => 1]);

        // MySQL's default collation is case-insensitive, so even a lower-case duplicate is refused by the index itself.
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('promo_codes')->insert(['code' => 'welcome10', 'type' => 'percent', 'value' => 5, 'valid_from' => now(), 'valid_until' => now(), 'applies_to' => 'all', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_mysql_only_the_longest_indexed_text_columns_fit_the_index_limit(): void
    {
        $this->mysqlOnly();

        // utf8mb4 uses up to 4 bytes a character: a unique index on a 255 character column needs 1020 bytes (the limit is 3072 on InnoDB with
        // DYNAMIC rows, 767 on very old servers). The migrations ran, so it fits; this keeps the requirement visible.
        $longest = DB::select("select table_name, column_name, character_maximum_length as len from information_schema.columns where table_schema = database() and data_type = 'varchar' and character_maximum_length * 4 > 3072");
        $this->assertSame([], $longest, 'no varchar column is long enough to break the 3072 byte index limit');
    }
}
