<?php

namespace Tests\Unit;

use App\Services\Chat\CrisisDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Crisis detection is plain rules on the server, never the model alone (docs/CHAT.md). It prefers a false alarm to a miss. */
class CrisisDetectorTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function worrying(): array
    {
        return [
            'plain english' => ['I want to kill myself'],
            'killing' => ['i keep thinking about killing myself'],
            'suicide' => ['I have been having suicidal thoughts'],
            'suicide word' => ['thinking about suicide'],
            'end my life' => ["I'm going to end my life tonight"],
            'take my life' => ['i might take my own life'],
            'want to die' => ['i just want to die'],
            'wanna die' => ['i wanna die'],
            'dont want to live' => ["I don't want to live anymore"],
            'curly apostrophe' => ['I don’t want to be alive'],
            'no reason to live' => ['there is no reason to live'],
            'better off dead' => ["they'd be better off dead without me" ],
            'better off without me' => ['everyone would be better off without me'],
            'wish i was dead' => ['i wish i was dead'],
            'wish i could disappear' => ['sometimes i wish i could just disappear'],
            'self harm' => ['I have started self-harm again'],
            'cutting' => ['i have been cutting myself'],
            'hurt myself' => ['i want to hurt myself'],
            'overdose' => ['thinking of an overdose'],
            'upper case and punctuation' => ['I WANT TO DIE!!!'],
            'extra spaces' => ["i   want   to   die"],
            'malay bunuh diri' => ['saya nak bunuh diri'],
            'malay bunuh diri word' => ['Aku fikir pasal bunuh diri'],
            'malay nak mati' => ['aku nak mati je'],
            'malay mahu mati' => ['saya mahu mati'],
            'malay short' => ['nk mati'],
            'malay tak nak hidup' => ['aku tak nak hidup lagi'],
            'malay xnak hidup' => ['xnak hidup dah'],
            'malay tamatkan hidup' => ['aku nak tamatkan hidup aku'],
            'malay cederakan diri' => ['aku nak cederakan diri sendiri'],
            'malay menyakiti diri' => ['saya sering menyakiti diri sendiri'],
            'malay hiris tangan' => ['aku hiris tangan semalam'],
            'malay lebih baik mati' => ['lebih baik aku mati'],
            'malay semua lebih baik tanpa aku' => ['semua orang lebih baik tanpa aku'],
            'malay gantung diri' => ['aku fikir nak gantung diri'],
            'in the middle of a longer message' => ['Exams are awful and my family fights all the time and honestly I do not want to be alive anymore, I just feel tired.'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function calm(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['    '],
            'stress' => ['I am so stressed about my exams'],
            'sad' => ['I feel really sad and lonely today'],
            'anxious' => ['My anxiety is bad before presentations'],
            'tired' => ['I am dying to sleep, so tired'],
            'killing time' => ['I am just killing time before class'],
            'end of the semester' => ['I cannot wait for the end of the semester'],
            'ending a relationship' => ['we are ending things between us and it hurts'],
            'cut' => ['I cut my finger cooking'],
            'live' => ['I live in a hostel near the campus'],
            'hurts' => ['my head hurts after studying all night'],
            'malay normal' => ['saya stres sebab exam minggu depan'],
            'malay mati' => ['telefon aku mati bateri'],
            'malay hidup' => ['hidup di kolej memang penat'],
            'malay diri' => ['saya nak kenal diri sendiri lebih baik'],
            'hello' => ['hello, can we talk?'],
            'counselor' => ['I would like to see a counselor'],
        ];
    }

    #[DataProvider('worrying')]
    public function test_it_flags_talk_of_ending_ones_life_or_self_harm(string $text): void
    {
        $this->assertTrue((new CrisisDetector)->flags($text), $text);
    }

    #[DataProvider('calm')]
    public function test_it_leaves_ordinary_worries_alone(string $text): void
    {
        $this->assertFalse((new CrisisDetector)->flags($text), $text);
    }

    public function test_tidying_folds_case_accents_apostrophes_and_marks(): void
    {
        $this->assertSame('i dont want to live', (new CrisisDetector)->tidy("  I DON'T   want-to, Live!! "));
        $this->assertSame('cafe', (new CrisisDetector)->tidy('Café'));
    }
}
