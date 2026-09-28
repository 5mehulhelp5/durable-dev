<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal;

use Gplanchat\Bridge\Temporal\TemporalConnection;
use PHPUnit\Framework\TestCase;

/**
 * #594: the journal workflow (#356) left a type, a signal name and an id scheme on the connection,
 * and a resolver for its signals. Nothing starts or reads that workflow any more.
 */
final class TheJournalWorkflowIsGoneTest extends TestCase
{
    public function testAWorkflowTypeInTheDsnIsRefusedAndSaysWhy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/workflow_type.*no longer/');

        TemporalConnection::fromDsn('temporal://localhost:7233?workflow_type=DurableJournal');
    }

    public function testTheConnectionNoLongerCarriesTheJournalWorkflow(): void
    {
        $connection = new \ReflectionClass(TemporalConnection::class);

        self::assertFalse($connection->hasConstant('DEFAULT_WORKFLOW_TYPE'));
        self::assertFalse($connection->hasConstant('DEFAULT_SIGNAL_APPEND'));
        self::assertFalse($connection->hasProperty('workflowType'));
        self::assertFalse($connection->hasProperty('signalAppend'));
        self::assertFalse($connection->hasMethod('journalWorkflowId'));
        self::assertFalse(class_exists('Gplanchat\\Bridge\\Temporal\\Journal\\JournalStateResolver'));
    }
}
