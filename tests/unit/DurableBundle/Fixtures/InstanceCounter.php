<?php

declare(strict_types=1);

namespace unit\DurableBundle\Fixtures;

/**
 * Counts what the container really built.
 */
final class InstanceCounter
{
    /** @var list<string> */
    private static array $built = [];

    public static function reset(): void
    {
        self::$built = [];
    }

    public static function note(string $what): void
    {
        self::$built[] = $what;
    }

    /**
     * @return list<string>
     */
    public static function built(): array
    {
        return self::$built;
    }

    public static function total(): int
    {
        return \count(self::$built);
    }
}
