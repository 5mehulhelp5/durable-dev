<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Dbal;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Gplanchat\Bridge\Dbal\Schema\DurableSchema;
use PHPUnit\Framework\TestCase;

/**
 * The journal tables must be **declarable** to the Doctrine tooling, not only creatable by the
 * bridge.
 *
 * A table `doctrine:migrations:diff` does not see in the expected schema is an orphan table: the
 * generated migration drops it. The journal of durable executions is exactly what we do not want
 * to see vanish in a migration nobody read closely.
 *
 * @see DUR030
 */
final class DurableSchemaDeclarationTest extends TestCase
{
    private const TABLES = [
        'durable_events',
        'durable_workflow_metadata',
        'durable_child_workflow_parent_link',
        'durable_workflow_runs',
    ];

    public function testTheTablesAreDeclaredInAnEmptySchema(): void
    {
        $connection = self::connection();
        $schema = new Schema();

        (new DurableSchema($connection))->configureSchema($schema, $connection, static fn(): bool => true);

        foreach (self::TABLES as $table) {
            self::assertTrue($schema->hasTable($table), \sprintf('%s must be declared', $table));
        }
    }

    /**
     * The schema Doctrine builds already carries the entity tables, and may carry ours if a
     * previous migration created them. Redeclaring a present table would throw.
     */
    public function testATableAlreadyPresentInTheSchemaIsNotRedeclared(): void
    {
        $connection = self::connection();
        $schema = new Schema();
        $alreadyThere = $schema->createTable('durable_events');
        $alreadyThere->addColumn('id', 'bigint');

        (new DurableSchema($connection))->configureSchema($schema, $connection, static fn(): bool => true);

        self::assertTrue($schema->hasTable('durable_events'));
        self::assertTrue($schema->hasTable('durable_workflow_runs'), 'the others are declared all the same');
    }

    /**
     * The journal may live on another connection than the ORM's. Declaring our tables there would
     * create, in the application database, tables that are not there — and drop, in the journal
     * database, those that are.
     */
    public function testNothingIsDeclaredWhenItIsNotTheSameDatabase(): void
    {
        $schema = new Schema();

        (new DurableSchema(self::connection()))->configureSchema(
            $schema,
            self::connection(),
            static fn(): bool => false,
        );

        self::assertSame([], $schema->getTables(), 'no table may join the schema of another database');
    }

    public function testTheSameDatabaseOnAnotherConnectionIsDeclared(): void
    {
        $schema = new Schema();

        (new DurableSchema(self::connection()))->configureSchema(
            $schema,
            self::connection(),
            static fn(): bool => true,
        );

        self::assertCount(\count(self::TABLES), $schema->getTables());
    }

    /**
     * When migrations hold the schema, the bridge's lazy DDL has no reason to exist any more: it
     * would write behind the back of the tool now in charge of it.
     */
    public function testDisabledAutoSetupCreatesNoTable(): void
    {
        $connection = self::connection();

        (new DurableSchema($connection, autoSetup: false))->ensure();

        self::assertSame([], $connection->createSchemaManager()->listTableNames());
    }

    public function testEnabledAutoSetupCreatesTheTables(): void
    {
        $connection = self::connection();

        (new DurableSchema($connection))->ensure();

        foreach (self::TABLES as $table) {
            self::assertContains($table, $connection->createSchemaManager()->listTableNames());
        }
    }

    private static function connection(): \Doctrine\DBAL\Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
