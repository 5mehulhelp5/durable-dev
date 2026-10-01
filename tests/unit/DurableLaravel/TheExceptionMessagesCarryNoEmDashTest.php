<?php

declare(strict_types=1);

namespace unit\Gplanchat\Durable\Laravel;

use PHPUnit\Framework\TestCase;

/**
 * The docs quote the package's error messages, and the docs carry no em dash (#842).
 *
 * A source scan rather than a runtime test: the messages sit behind boot-time guards (a missing
 * bridge class, a `sync` connection, an `array` lock) that no single container setup reaches all at
 * once. Every string literal between a `throw` and the end of its statement is read.
 */
final class TheExceptionMessagesCarryNoEmDashTest extends TestCase
{
    public function testNoThrownMessageContainsAnEmDash(): void
    {
        $offending = [];
        $root = \dirname(__DIR__, 3) . '/src/DurableLaravel';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'php' !== $file->getExtension()) {
                continue;
            }

            $inThrow = false;
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (\is_array($token) && \T_THROW === $token[0]) {
                    $inThrow = true;
                } elseif (';' === $token) {
                    $inThrow = false;
                } elseif ($inThrow && \is_array($token) && \in_array($token[0], [\T_CONSTANT_ENCAPSED_STRING, \T_ENCAPSED_AND_WHITESPACE], true) && str_contains($token[1], '—')) {
                    $offending[] = substr($file->getPathname(), \strlen($root) + 1) . ':' . $token[2];
                }
            }
        }

        self::assertSame([], $offending);
    }
}
