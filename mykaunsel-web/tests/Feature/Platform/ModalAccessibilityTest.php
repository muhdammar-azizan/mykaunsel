<?php

namespace Tests\Feature\Platform;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use Database\Seeders\ComplaintReviewSeeder;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * The shared modal window (every confirmation and reason window in Platform Admin) is a proper dialog: announced as one,
 * named by its heading, closed with Escape, keeps keyboard focus inside while open and gives it back to the button that opened it.
 */
class ModalAccessibilityTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        $this->seed(ComplaintReviewSeeder::class);
    }

    /** Modal windows that are marked as dialogs (the search palette is a dialog of its own and is not counted). */
    private function dialogs(string $html): int
    {
        return preg_match_all('/x-show="show"\s+role="dialog"\s+aria-modal="true"/', $html);
    }

    public function test_every_modal_on_a_complaint_page_is_a_dialog_named_by_its_heading(): void
    {
        $complaint = Complaint::where('status', ComplaintStatus::Open->value)->firstOrFail();

        $html = $this->as()->get(route('platform.complaints.show', $complaint))->assertOk()->getContent();

        $modals = substr_count($html, 'x-on:open-modal.window');
        $this->assertGreaterThanOrEqual(3, $modals, 'resolve, dismiss and warn at least');
        $this->assertSame($modals, $this->dialogs($html), 'every modal window is a dialog');
        $this->assertSame($modals, substr_count($html, "const heading = \$el.querySelector('h1, h2, h3')"), 'each names itself by its first heading');
    }

    public function test_the_modal_closes_with_escape_traps_focus_and_returns_it(): void
    {
        $html = $this->as()->get(route('platform.complaints.show', Complaint::where('status', 'open')->firstOrFail()))->getContent();

        $this->assertStringContainsString('x-on:keydown.escape.window="show = false"', $html);
        $this->assertStringContainsString('x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"', $html);
        $this->assertStringContainsString('x-on:keydown.shift.tab.prevent="prevFocusable().focus()"', $html);
        $this->assertStringContainsString('trigger = document.activeElement', $html, 'remembers the button that opened it');
        $this->assertStringContainsString('trigger.focus()', $html, 'and gives focus back on close');
    }

    public function test_other_admin_pages_with_modals_still_render_and_are_dialogs_too(): void
    {
        $profile = $this->profile('KB10234');

        foreach ([route('platform.counselors.show', $profile), route('platform.registration'), route('platform.admins')] as $url) {
            $html = $this->as()->get($url)->assertOk()->getContent();
            $this->assertSame(substr_count($html, 'x-on:open-modal.window'), $this->dialogs($html), $url);
        }
    }
}
