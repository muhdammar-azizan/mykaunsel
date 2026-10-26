<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Prints a new random secret for WAITLIST_HMAC_KEY (it changes nothing by itself: paste it into .env and keep it stable). */
class WaitlistKey extends Command
{
    protected $signature = 'waitlist:key';

    protected $description = 'Print a new random WAITLIST_HMAC_KEY for .env';

    public function handle(): int
    {
        $this->line('WAITLIST_HMAC_KEY='.bin2hex(random_bytes(32)));
        $this->newLine();
        $this->comment('Put this line in .env. Keep it stable: changing it later makes old opt-out links stop working and old opt-outs stop matching. It is separate from APP_KEY on purpose.');

        return self::SUCCESS;
    }
}
