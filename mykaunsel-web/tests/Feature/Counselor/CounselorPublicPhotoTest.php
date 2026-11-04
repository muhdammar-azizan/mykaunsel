<?php

namespace Tests\Feature\Counselor;

use App\Models\CounselorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * A counselor may have a photo on their ACCOUNT (the portal), but no photo reaches a client before an approval step exists (docs/PUBLIC_COUNSELOR_DATA.md):
 * the app shows initials. Whatever the counselor uploads, nothing about it appears in what clients can read.
 */
class CounselorPublicPhotoTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina Ahmad', [$this->platform]);
        $this->aina->user->forceFill(['avatar_path' => 'avatars/secretphotoname123.jpg'])->save();
        $this->slot($this->aina, $this->platform, '2026-11-05 10:00');
        $this->client();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_counselor_has_a_photo_on_their_own_account_but_clients_never_see_it(): void
    {
        $this->assertNotNull($this->aina->user->fresh()->avatar_url, 'the account does have one');

        $bodies = [
            $this->getJson('/api/v1/counselors')->assertOk()->getContent(),
            $this->getJson('/api/v1/counselors/'.$this->aina->public_id)->assertOk()->getContent(),
            $this->availability($this->aina)->assertOk()->getContent(),
        ];

        foreach ($bodies as $body) {
            $this->assertStringNotContainsString('secretphotoname123', $body);
            $this->assertStringNotContainsString('avatars/', $body);
        }

        // Wherever a photo field exists in an answer, it is empty: the app falls back to initials.
        $decoded = json_decode($bodies[2], true);
        $this->assertNull($decoded['counselor']['avatar_url'] ?? null);
    }
}
