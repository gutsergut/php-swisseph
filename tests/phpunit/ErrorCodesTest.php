<?php

declare(strict_types=1);

namespace Swisseph\Tests;

use PHPUnit\Framework\TestCase;
use Swisseph\ErrorCodes;

final class ErrorCodesTest extends TestCase
{
    public function testComposerLoadsTheCanonicalErrorCodes(): void
    {
        self::assertSame('NOT_FOUND', ErrorCodes::name(ErrorCodes::NOT_FOUND));
        self::assertSame(
            'E1006 NOT_FOUND: Requested event not found in search interval - polar night',
            ErrorCodes::compose(ErrorCodes::NOT_FOUND, 'polar night')
        );
        self::assertSame('UNKNOWN', ErrorCodes::name(-1));
    }

    public function testLegacyEntryPointDoesNotRedeclareAnAutoloadedClass(): void
    {
        self::assertTrue(class_exists(ErrorCodes::class));

        require dirname(__DIR__, 2) . '/src/Error.php';
        require dirname(__DIR__, 2) . '/src/Error.php';

        self::assertSame('OK', ErrorCodes::name(ErrorCodes::OK));
        self::assertSame('E0 OK: No error', ErrorCodes::compose(ErrorCodes::OK));
    }
}
