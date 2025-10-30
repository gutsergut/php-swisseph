<?php

use PHPUnit\Framework\TestCase;
use Swisseph\Utc;

final class UtcJdPhpUnitTest extends TestCase
{
    public function testRoundTrip(): void
    {
        [$jd_ut] = Utc::utcToJd(2000, 1, 1, 12, 0, 0.0, 1);
        [$y, $m, $d, $H, $M, $S] = Utc::jdToUtc($jd_ut, 1);
        $this->assertSame(2000, $y);
        $this->assertSame(1, $m);
        $this->assertSame(1, $d);
        $this->assertSame(12, $H);
        $this->assertSame(0, $M);
        $this->assertLessThan(1e-6, abs($S));
    }
}
