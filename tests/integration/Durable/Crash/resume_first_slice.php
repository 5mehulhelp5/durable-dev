<?php

declare(strict_types=1);

/**
 * One step of {@see integration\Durable\Crash\ResumeDispatchedFirstCrashTest}, as a whole PHP
 * process over one SQLite file: the journal, the metadata, and the two queues of
 * {@see integration\Durable\Crash\SqliteTestQueues}.
 *
 * Usage: php resume_first_slice.php <journal.sqlite> start|workflow|activity|status|count-resumes
 *
 * Environment:
 *   SLICE_LOG   appended to, one line per activity actually executed
 *   SLICE_KILL  before-append | after-append: SIGKILL the activity worker around the outcome's append
 *   SLICE_ACK   on-dequeue: acknowledge the activity message before processing it, as an inline
 *               drain does, so that nothing redelivers it
 *
 * Exit codes: 0 done, 3 a resume is waiting for its outcome, 4 usage. A SIGKILL leaves none.
 */

use Doctrine\DBAL\DriverManager;
use Gplanchat\Bridge\Dbal\Schema\DurableSchema;
use Gplanchat\Bridge\Dbal\Store\DbalChildWorkflowParentLinkStore;
use Gplanchat\Bridge\Dbal\Store\DbalEventStore;
use Gplanchat\Bridge\Dbal\Store\DbalWorkflowMetadataStore;
use Gplanchat\Durable\Event\ActivityCompleted;
use Gplanchat\Durable\Event\Event;
use Gplanchat\Durable\Exception\ResumeArrivedBeforeItsOutcome;
use Gplanchat\Durable\ExecutionEngine;
use Gplanchat\Durable\ExecutionRuntime;
use Gplanchat\Durable\Handler\ResumeWorkflowHandler;
use Gplanchat\Durable\Port\ActivityHeartbeatSenderInterface;
use Gplanchat\Durable\Port\NullWorkflowTimerDispatcher;
use Gplanchat\Durable\RegistryActivityExecutor;
use Gplanchat\Durable\Store\EventStoreInterface;
use Gplanchat\Durable\Transport\ActivityMessage;
use Gplanchat\Durable\Transport\ResumeWorkflowMessage;
use Gplanchat\Durable\Worker\ActivityMessageProcessor;
use Gplanchat\Durable\Workflow\WorkflowDefinitionLoader;
use Gplanchat\Durable\WorkflowEnvironment;
use Gplanchat\Durable\WorkflowRegistry;
use integration\Durable\Crash\CrashBenchActivities;
use integration\Durable\Crash\SqliteTestQueues;

require __DIR__ . '/../../../../vendor/autoload.php';

const EXECUTION = '01900000-0000-7000-8000-00000000d050';

[$journalPath, $step] = [$argv[1] ?? null, $argv[2] ?? null];
if (null === $journalPath || !\in_array($step, ['start', 'workflow', 'activity', 'status', 'count-resumes'], true)) {
    fwrite(STDERR, "usage: resume_first_slice.php <journal.sqlite> start|workflow|activity|status|count-resumes\n");
    exit(4);
}

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $journalPath]);
$schema = new DurableSchema($connection);
$journal = new DbalEventStore($connection, $schema);
$metadata = new DbalWorkflowMetadataStore($connection, $schema);
$queues = new SqliteTestQueues($connection, $metadata);

$executor = new RegistryActivityExecutor();
$executor->register('bench.first', static function (): string {
    file_put_contents((string) getenv('SLICE_LOG'), "charge\n", FILE_APPEND | LOCK_EX);

    return 'ch_1';
});

switch ($step) {
    case 'start':
        $queues->dispatchNewWorkflowRun(EXECUTION, 'resume-first', []);
        exit(0);

    case 'status':
        echo ($metadata->get(EXECUTION)['completed'] ?? false) ? 'completed' : 'running', "\n";
        exit(0);

    case 'count-resumes':
        echo $queues->count('resumes'), "\n";
        exit(0);

    case 'workflow':
        $registry = new WorkflowRegistry();
        $registry->registerFactory('resume-first', static fn(array $payload) => static fn(WorkflowEnvironment $env): string => $env->await($env->activityStub(CrashBenchActivities::class)->first('a')));
        $handler = new ResumeWorkflowHandler(
            new ExecutionEngine($journal, new ExecutionRuntime($journal, $queues, $executor, 0, null, true)),
            $registry,
            $metadata,
            $queues,
            $journal,
            new DbalChildWorkflowParentLinkStore($connection, $schema),
            new NullWorkflowTimerDispatcher(),
            new WorkflowDefinitionLoader(),
        );
        while (null !== ($taken = $queues->take('resumes'))) {
            [$id, $message] = $taken;
            \assert($message instanceof ResumeWorkflowMessage);

            try {
                $handler($message);
            } catch (ResumeArrivedBeforeItsOutcome) {
                exit(3); // left in the queue: the transport's retry is the wait
            }
            $queues->ack($id);
        }
        exit(0);

    case 'activity':
        $taken = $queues->take('activities');
        if (null === $taken) {
            exit(0);
        }
        [$id, $message] = $taken;
        \assert($message instanceof ActivityMessage);
        if ('on-dequeue' === getenv('SLICE_ACK')) {
            $queues->ack($id);
        }
        // SIGKILL and not exit(): no destructor, no shutdown function, nothing flushed.
        $kill = (string) getenv('SLICE_KILL');
        $killing = new class ($journal, $kill) implements EventStoreInterface {
            public function __construct(private readonly EventStoreInterface $inner, private readonly string $kill) {}

            public function append(Event $event): void
            {
                if ($event instanceof ActivityCompleted && 'before-append' === $this->kill) {
                    posix_kill(posix_getpid(), \SIGKILL);
                }
                $this->inner->append($event);
                if ($event instanceof ActivityCompleted && 'after-append' === $this->kill) {
                    posix_kill(posix_getpid(), \SIGKILL);
                }
            }

            public function readStream(string $executionId): iterable
            {
                return $this->inner->readStream($executionId);
            }

            public function readStreamWithRecordedAt(string $executionId): iterable
            {
                return $this->inner->readStreamWithRecordedAt($executionId);
            }

            public function countEventsInStream(string $executionId): int
            {
                return $this->inner->countEventsInStream($executionId);
            }
        };
        $heartbeat = new class implements ActivityHeartbeatSenderInterface {
            public function sendHeartbeat(mixed $details = null): bool
            {
                return true;
            }

            public function isCancellationRequested(): bool
            {
                return false;
            }
        };
        (new ActivityMessageProcessor($killing, $queues, $executor, $queues, $heartbeat))->process($message);
        $queues->ack($id);
        exit(0);
}
