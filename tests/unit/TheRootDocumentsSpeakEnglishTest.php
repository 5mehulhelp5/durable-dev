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
 * Same signals as {@see TheShippedTemplatesSpeakEnglishTest}: a French accented letter, and a
 * French function word for the lines written without accents. The scripts under `bin/` ride along:
 * their `::error` lines end up in CI output, which is read by the same strangers.
 */
final class TheRootDocumentsSpeakEnglishTest extends TestCase
{
    private const ACCENTED = '/[àâäçéèêëîïôöùûüœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŒ]/u';

    /**
     * French written without accents slips past the letter check. These function words do not
     * occur in English prose or in code, so one of them on a line is the same signal.
     */
    private const FUNCTION_WORDS = '/\b(le|la|les|des|une|est|sont|dans|pour|avec|sans|tous|toutes|aussi|donc|mais|ou|où|pas|très|cette|ces|leur|leurs|notre|votre|chez|vers|depuis|jamais|toujours|encore|déjà|entre|selon|sinon|puis|alors|ainsi|afin|lorsque|quand)\b/iu';

    /**
     * @return iterable<string, array{string}>
     */
    public static function rootDocuments(): iterable
    {
        $root = \dirname(__DIR__, 2);

        foreach (array_merge(['README.md', 'UPGRADE.md'], glob('src/*/README.md') ?: [], glob('src/Bridge/*/README.md') ?: [], glob('bin/*.sh') ?: []) as $relative) {
            yield $relative => [$root . '/' . $relative];
        }
    }

    #[DataProvider('rootDocuments')]
    public function testADocumentCarriesNoFrench(string $path): void
    {
        $offenders = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            if (1 === preg_match(self::ACCENTED, $line) || 1 === preg_match(self::FUNCTION_WORDS, $line)) {
                $offenders[] = ($number + 1) . ': ' . trim($line);
            }
        }

        self::assertSame([], $offenders, 'French found in ' . $path);
    }
}
