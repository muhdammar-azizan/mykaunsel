<?php

namespace Tests\Unit;

use App\Services\Chat\PromptScrubber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** What identifies a person is taken out of the text before it leaves for the model (docs/CHAT.md). */
class PromptScrubberTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function cases(): array
    {
        return [
            'email' => ['write to siti.aminah+uni@umpsa.edu.my please', 'write to [email] please'],
            'link' => ['see https://example.com/me?x=1 and www.example.org/a', 'see [link] and [link]'],
            'mykad with dashes' => ['my ic is 990101-06-5432 ok', 'my ic is [id number] ok'],
            'mykad with spaces' => ['990101 06 5432', '[id number]'],
            'mykad plain' => ['ic 990101065432.', 'ic [id number].'],
            'mobile plain' => ['call 0123456789 now', 'call [phone] now'],
            'mobile with dash' => ['call 012-345 6789 now', 'call [phone] now'],
            'mobile international' => ['+60 12-345 6789', '[phone]'],
            'landline' => ['pejabat 09-5492345', 'pejabat [phone]'],
            'nothing to take out' => ['I feel sad about my exams on 3 November.', 'I feel sad about my exams on 3 November.'],
            'short numbers stay' => ['I slept 4 hours and ate 2 meals, rated 7 out of 10', 'I slept 4 hours and ate 2 meals, rated 7 out of 10'],
            'a year stays' => ['in 2024 it started', 'in 2024 it started'],
        ];
    }

    #[DataProvider('cases')]
    public function test_it_removes_what_identifies_and_keeps_the_rest(string $in, string $out): void
    {
        $this->assertSame($out, (new PromptScrubber)->scrub($in));
    }
}
