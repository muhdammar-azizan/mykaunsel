<?php

namespace App\Services\Chat;

/**
 * Takes what identifies a person out of the text BEFORE it leaves for the model (docs/CHAT.md): e-mail addresses, web links, Malaysian identity
 * card numbers and phone numbers. The conversation is kept as the person wrote it; only the copy sent to the model is cleaned. The person's
 * name, e-mail and account are never part of a request at all. This is a safety net for what people type about themselves, not a promise that
 * nothing identifying can slip through (a name inside a sentence stays).
 */
class PromptScrubber
{
    public function scrub(string $text): string
    {
        $patterns = [
            // e-mail addresses
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u' => '[email]',
            // web links
            '/\b(?:https?:\/\/|www\.)\S+/iu' => '[link]',
            // MyKad: 6 digits, 2 digits, 4 digits, with dashes or spaces or none
            '/\b\d{6}[\s\-]?\d{2}[\s\-]?\d{4}\b/u' => '[id number]',
            // phone numbers: +60 / 0 prefix, 8 to 11 digits in total, with spaces, dashes or dots
            '/(?<![\d])(?:\+?60|0)[\s\-.]?(?:1\d|[3-9])(?:[\s\-.]?\d){6,9}(?![\d])/u' => '[phone]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }
}
