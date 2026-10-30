<?php

namespace App\Services\Chat;

/**
 * Crisis detection on the SERVER, by plain rules, before the model is asked and whatever it answers (docs/CHAT.md). The chat never depends on
 * the model alone to notice that someone may be in danger.
 *
 * It looks for the person talking about ending their life or hurting themselves, in English and Bahasa Malaysia, spelling variations and
 * texting shortcuts included. It prefers a false alarm to a miss: a flag only makes the app show a calm card with where to get help, and never
 * stops the conversation. Quoted or hypothetical uses ("the film about suicide") are flagged too: a card is cheap, a missed message is not.
 */
class CrisisDetector
{
    /** @var list<string> regular expressions over the TIDIED text (lower case, no accents, punctuation turned into spaces) */
    private const PATTERNS = [
        // English: ending one's life
        '\bsuicid',
        '\bkill(ing)? (my ?self|me)\b',
        '\bend(ing)? (my|it all|everything)( life| now)?\b',
        '\btake my (own )?life\b',
        '\b(want|wanna|wish|plan|planning|thinking|think|ready|trying|tempted) (to |of |about )?(just )?(die|dying|disappear for good)\b',
        '\bwant(ed)? to be dead\b',
        '\b(wish|wished) (i|that i) (was|were|am) (dead|never born)\b',
        '\bwish i (could )?(just )?(die|disappear|not wake up)\b',
        '\b(dont|do not|not) want to (live|be alive|wake up|exist)( anymore| any more)?\b',
        '\bno (reason|point) (to|in) (live|living|go on|going on|being alive)\b',
        '\b(better|best) (off )?dead\b',
        '\bbetter off without me\b',
        '\beveryone (would be|is) better off without me\b',
        '\b(hurt|harm|cut|cutting|injure|injuring|punish) (my ?self|my arms?|my wrists?)\b',
        '\bself ?harm',
        '\boverdos',
        '\bhang (my ?self)\b',
        '\bjump (off|from) (a |the )?(bridge|building|roof|balcony)\b',
        // Bahasa Malaysia
        '\bbunuh diri\b',
        '\bbunuh sendiri\b',
        '\b(nak|nk|mahu|mau|ingin|hendak|hendk|rasa nak|rasa mahu) (mati|mampus|bunuh diri|tamatkan hidup|habiskan nyawa)\b',
        '\b(tak|tk|tidak|xnak|x nak|xmahu|x mahu|malas) (nak|mahu|mau|ingin)? ?(hidup|lagi hidup)\b',
        '\b(tamat|tamatkan|akhiri|habiskan) (hidup|nyawa|riwayat)( ?(ku|aku|saya|sendiri))?\b',
        '\b(cederakan|sakitkan|lukakan|seksa|siksa) diri( sendiri)?\b',
        '\b(menyakiti|mencederakan|mencederai|melukai) diri( sendiri)?\b',
        '\bmenghiris (tangan|pergelangan)\b',
        '\bhiris (tangan|pergelangan)\b',
        '\blebih baik (aku|saya|sy) (mati|tiada|tak ada|tidak ada)\b',
        '\btak (ada|de) (sebab|guna|gunanya) (untuk )?(hidup|aku hidup|saya hidup)\b',
        '\btidak (ada|ada lagi) (sebab|gunanya) (untuk )?hidup\b',
        '\bsemua orang (lebih )?(baik|bagus) tanpa (aku|saya)\b',
        '\bterjun (dari|bangunan|jambatan)\b',
        '\bgantung diri\b',
    ];

    public function flags(string $text): bool
    {
        $tidy = $this->tidy($text);

        if ($tidy === '') {
            return false;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match('/'.$pattern.'/u', $tidy) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Lower case, accents removed, apostrophes dropped (don't -> dont), every other mark turned into a space, spaces collapsed. */
    public function tidy(string $text): string
    {
        $text = mb_strtolower($text);
        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = is_string($folded) && $folded !== '' ? $folded : $text;
        $text = str_replace(["'", '’', '`', '´'], '', $text);
        $text = (string) preg_replace('/[^a-z0-9]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
