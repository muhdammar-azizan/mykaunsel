<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one plain e-mail to check the mail settings (docs/SETUP.md, "Real e-mail while testing"). It says which mailer, host and sender are in use,
 * and whether the links in e-mails will open from another device. It never prints a password.
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {to : The address to send to}';

    protected $description = 'Send one test e-mail and say how the mail settings are set';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $host = (string) config("mail.mailers.{$mailer}.host", '-');
        $url = (string) config('app.url');

        $this->line("Mailer: {$mailer}".($mailer === 'smtp' ? " ({$host}:".config('mail.mailers.smtp.port').')' : ''));
        $this->line("From:   {$from}");
        $this->line("Links:  {$url}");

        if ($mailer === 'log') {
            $this->warn('The mailer is "log": nothing is sent, the e-mail is only written to storage/logs. Set MAIL_MAILER=smtp to send for real.');
        }

        if (preg_match('~^https?://(localhost|127\.0\.0\.1)~i', $url)) {
            $this->warn('APP_URL points at this computer, so a link in an e-mail will not open on a phone. Use an address the phone can reach (a tunnel or this computer\'s address on the Wi-Fi).');
        }

        if (str_ends_with($from, '@example.com')) {
            $this->warn('MAIL_FROM_ADDRESS is an example address; most mail services refuse it or send it to spam.');
        }

        try {
            Mail::raw("This is a test e-mail from MyKaunsel.\n\nIf you can read it, sending works. Links in e-mails will point at {$url}.", function ($message) use ($from) {
                $message->to($this->argument('to'))->subject('MyKaunsel test e-mail');
            });
        } catch (Throwable $e) {
            // The text of a transport error can name the host and the user, never the password.
            $this->error('Sending failed: '.$e::class.' - '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent to '.$this->argument('to').($mailer === 'log' ? ' (to the log only).' : '. Check the inbox, and the spam folder.'));

        return self::SUCCESS;
    }
}
