<?php

namespace Tests\Unit;

use App\Support\UserAgent;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    public function test_it_reads_common_browsers_and_systems(): void
    {
        $chromeWin = UserAgent::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36');
        $this->assertSame('Chrome on Windows', $chromeWin['label']);
        $this->assertFalse($chromeWin['mobile']);

        $edge = UserAgent::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0');
        $this->assertSame('Edge', $edge['browser']);

        $safariMac = UserAgent::parse('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15');
        $this->assertSame('Safari on macOS', $safariMac['label']);

        $iphone = UserAgent::parse('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1');
        $this->assertSame('Safari on iOS', $iphone['label']);
        $this->assertTrue($iphone['mobile']);

        $android = UserAgent::parse('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36');
        $this->assertSame('Chrome on Android', $android['label']);
        $this->assertTrue($android['mobile']);

        $firefox = UserAgent::parse('Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0');
        $this->assertSame('Firefox on Linux', $firefox['label']);
    }

    public function test_unknown_or_empty_agents_do_not_break(): void
    {
        $this->assertSame('Unknown browser on Unknown OS', UserAgent::parse(null)['label']);
        $this->assertSame('Unknown browser on Unknown OS', UserAgent::parse('curl/8.0')['label']);
    }
}
