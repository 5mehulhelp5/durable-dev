<?php

declare(strict_types=1);

namespace unit\Gplanchat;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * WA006 makes English the working language, and the documents at the root of a package are the
 * first thing a stranger reads: the README on Packagist, the upgrade guide when a release breaks
 * something. `UPGRADE.md` was translated once (#303) and three French sections were merged on top of
 * it within the week — nothing in the definition of done stopped it.
 *
 * Same signal as {@see TheShippedTemplatesSpeakEnglishTest}: a French accented letter. It misses
 * French written without accents, and it catches the drift that actually happens.
 */
final class TheRootDocumentsSpeakEnglishTest extends TestCase
{
    private const ACCENTED = '/[àâäçéèêëîïôöùûüœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŒ]/u';

    /**
     * @return iterable<string, array{string}>
     */
    public static function rootDocuments(): iterable
    {
        $root = \dirname(__DIR__, 2);

        foreach (array_merge(['README.md', 'UPGRADE.md'], glob('src/*/README.md') ?: [], glob('src/Bridge/*/README.md') ?: []) as $relative) {
            yield $relative => [$root . '/' . $relative];
        }
    }

    #[DataProvider('rootDocuments')]
    public function testADocumentCarriesNoFrench(string $path): void
    {
        $offenders = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            if (1 === preg_match(self::ACCENTED, $line)) {
                $offenders[] = ($number + 1) . ': ' . trim($line);
            }
        }

        self::assertSame([], $offenders, 'French found in ' . $path);
    }
}
