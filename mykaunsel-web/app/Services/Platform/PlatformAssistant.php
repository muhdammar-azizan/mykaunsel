<?php

namespace App\Services\Platform;

use App\Models\User;
use App\Services\Chat\AssistantModel;
use App\Services\Chat\AssistantUnavailable;
use App\Services\DashboardService;

/**
 * The "Ask anything" box on the Platform Admin dashboard. It answers questions about the platform's NUMBERS and points to the page where the
 * admin can act. It is read only: it changes nothing.
 *
 * What the model is sent: a short list of counts (the same ones the dashboard shows, from DashboardService) and the names of the pages it may
 * point to. Never a person's name, e-mail, complaint text, document or chat. The model chooses page KEYS; the real addresses are filled in here, so
 * it can not invent a link.
 */
class PlatformAssistant
{
    public function __construct(private readonly AssistantModel $model, private readonly DashboardService $dashboard) {}

    /** The pages the assistant may point to: key => [label, address]. */
    public function pages(): array
    {
        return [
            'organizations_pending' => ['Pending organizations', route('platform.organizations', ['tab' => 'pending'])],
            'organizations_skipped' => ['Organizations that skipped DNS verification', route('platform.organizations', ['tab' => 'skipped'])],
            'organizations' => ['All organizations', route('platform.organizations')],
            'counselors_pending' => ['Pending counselors', route('platform.counselors', ['tab' => 'pending'])],
            'counselors' => ['All counselors', route('platform.counselors')],
            'complaints_open' => ['Open complaints', route('platform.complaints', ['tab' => 'open'])],
            'complaints' => ['All complaints', route('platform.complaints')],
            'audit_log' => ['Audit log', route('platform.audit-log')],
            'lkm' => ['LKM directory', route('platform.lkm')],
            'invoices' => ['Invoices', route('platform.invoices')],
            'pricing' => ['Pricing & promotions', route('platform.pricing')],
            'analytics' => ['Analytics', route('platform.analytics')],
            'registration' => ['Registration control', route('platform.registration')],
        ];
    }

    /**
     * @return array{answer: string, links: list<array{label: string, url: string}>}
     *
     * @throws AssistantUnavailable the model can't answer now
     */
    public function ask(User $admin, string $question): array
    {
        $raw = $this->model->complete($this->system($admin), [['role' => 'user', 'content' => $question]], 600);

        return $this->parse($raw);
    }

    private function system(User $admin): string
    {
        $data = $this->dashboard->build($admin);
        $cards = $data['cards'];
        $stats = $data['stats'];

        $facts = [
            'Organizations waiting for approval: '.$cards['pending_organizations']['count'],
            'Organizations that skipped DNS verification (still pending): '.$cards['skipped_dns']['count'],
            'Counselors waiting for credential review: '.$cards['pending_counselors']['count'],
            'Active complaints: '.$cards['active_complaints']['count'].' ('.$cards['active_complaints']['open'].' open, '.$cards['active_complaints']['under_review'].' under review)',
            'Approved organizations: '.$stats['organizations'],
            'Approved counselors: '.$stats['counselors'],
            'Active members: '.$stats['members'],
            'Sessions completed this month: '.($stats['sessions']['has_data'] ? $stats['sessions']['count'] : 'no outcomes recorded yet'),
        ];

        $pages = [];

        foreach ($this->pages() as $key => [$label]) {
            $pages[] = "- {$key}: {$label}";
        }

        return implode("\n", [
            'You are the assistant inside the MyKaunsel Platform Admin dashboard. MyKaunsel is a counseling platform in Malaysia. You help the platform administrator '.$admin->name.' understand what needs attention and where to go.',
            '',
            'Answer ONLY from these facts, which are current. If the question needs anything else (a person, a complaint\'s content, money, trends), say you can not see that here and point to the page that does.',
            ...$facts,
            '',
            'Pages you may point to (use the key):',
            ...$pages,
            '',
            'Reply in the language of the question (English or Malay), in one to three short sentences. Never invent numbers, names or links. You can only read: you can not approve, reject, suspend or change anything, so if asked to, say the admin must do it on the page and point there.',
            'Return ONLY a JSON object, no other text: {"answer": "<your reply>", "pages": ["<page key>", ...]} with at most three page keys, or an empty list when none fits.',
        ]);
    }

    /** The model should answer with JSON; if it did not, its plain text is still a fair answer. Only known page keys become links. */
    private function parse(string $raw): array
    {
        $text = trim($raw);

        if (preg_match('/\{.*\}/s', $text, $found)) {
            $json = json_decode($found[0], true);

            if (is_array($json) && isset($json['answer']) && is_string($json['answer']) && trim($json['answer']) !== '') {
                $links = [];

                foreach (array_slice((array) ($json['pages'] ?? []), 0, 3) as $key) {
                    $page = is_string($key) ? ($this->pages()[$key] ?? null) : null;

                    if ($page !== null) {
                        $links[] = ['label' => $page[0], 'url' => $page[1]];
                    }
                }

                return ['answer' => trim($json['answer']), 'links' => $links];
            }
        }

        return ['answer' => $text, 'links' => []];
    }
}
