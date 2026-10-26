<?php

namespace App\Services;

/**
 * Keyed hashes for the waitlist, made with a dedicated secret (config waitlist.hmac_key), never APP_KEY.
 *
 *  - emailHash: what is kept for someone who opted out, and how an opt-out link finds the address. The kind of sign-up is
 *    part of it, so opting out of one list says nothing about another.
 *  - signature: signs an opt-out link. A link carries only the hash and this signature (no address, no expiry), so it
 *    cannot be made up by anyone without the key, and it still works after the address has been deleted.
 */
class WaitlistHasher
{
    private const MIN_KEY_LENGTH = 32;

    /** Without a proper key the waitlist is off (the form is not shown, nothing is stored). */
    public function available(): bool
    {
        return strlen($this->key()) >= self::MIN_KEY_LENGTH;
    }

    public function emailHash(string $type, string $email): string
    {
        return $this->mac("email|{$type}|{$email}");
    }

    public function signature(string $type, string $emailHash): string
    {
        return $this->mac("unsubscribe|{$type}|{$emailHash}");
    }

    public function valid(string $type, string $emailHash, string $signature): bool
    {
        return $this->available()
            && preg_match('/^[a-f0-9]{64}$/', $emailHash) === 1
            && hash_equals($this->signature($type, $emailHash), $signature);
    }

    /** The opt-out link for one address (no expiry; see the class note). */
    public function unsubscribeUrl(string $type, string $email): string
    {
        $hash = $this->emailHash($type, $email);

        return route('waitlist.unsubscribe', ['type' => $type, 'hash' => $hash, 'signature' => $this->signature($type, $hash)]);
    }

    private function mac(string $data): string
    {
        return hash_hmac('sha256', $data, $this->key());
    }

    private function key(): string
    {
        return (string) config('waitlist.hmac_key');
    }
}
