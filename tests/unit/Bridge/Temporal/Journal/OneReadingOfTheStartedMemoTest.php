<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal\Journal;

use Gplanchat\Bridge\Temporal\Codec\JsonPlainPayload;
use Gplanchat\Bridge\Temporal\Journal\JournalExecutionIdResolver;
use Gplanchat\Bridge\Temporal\Worker\TemporalExecutionHistory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\WorkflowExecutionStartedEventAttributes;

/**
 * The execution id on `WorkflowExecutionStarted` is read once (#799): the worker's history and the
 * public `durableExecutionIdFromStartedAttributes()` go through the same reader, so they cannot
 * drift apart. The history takes "no id" as absent (the runner falls back to the workflow id); the
 * public method, which promises a string, throws instead. A field that holds something other than
 * a non-empty string fails all three like a payload that is not JSON (#890): the runner must not
 * fall back then. `fromMemo()`, which reads the visibility of any workflow, reads both as no id.
 */
final class OneReadingOfTheStartedMemoTest extends TestCase
{
    /**
     * @return iterable<string, array{WorkflowExecutionStartedEventAttributes, ?string}>
     */
    public static function startedAttributes(): iterable
    {
        yield 'no memo' => [new WorkflowExecutionStartedEventAttributes(), null];
        yield 'memo without the field' => [self::started(null), null];
        yield 'an execution id' => [self::started(JsonPlainPayload::encode('order/42')), 'order/42'];
    }

    #[DataProvider('startedAttributes')]
    public function testTheHistoryAndThePublicMethodReadTheSameId(WorkflowExecutionStartedEventAttributes $attr, ?string $expected): void
    {
        self::assertSame($expected, JournalExecutionIdResolver::fromStartedAttributes($attr));
        self::assertSame($expected, self::history($attr)->durableExecutionId());

        if (null === $expected) {
            $this->expectException(\RuntimeException::class);
        }
        self::assertSame($expected, JournalExecutionIdResolver::durableExecutionIdFromStartedAttributes($attr));
    }

    /**
     * @return iterable<string, array{Payload}>
     */
    public static function invalidExecutionIds(): iterable
    {
        yield 'a number' => [JsonPlainPayload::encode(42)];
        yield 'an empty string' => [JsonPlainPayload::encode('')];
        yield 'null' => [JsonPlainPayload::encode(null)];
        yield 'an object' => [JsonPlainPayload::encode(['id' => 'order/42'])];
        yield 'an array' => [JsonPlainPayload::encode(['order/42'])];
        yield 'not json' => [new Payload(['data' => '{not json'])];
    }

    #[DataProvider('invalidExecutionIds')]
    public function testAnInvalidFieldFailsTheReader(Payload $executionId): void
    {
        $this->expectException(\JsonException::class);

        JournalExecutionIdResolver::fromStartedAttributes(self::started($executionId));
    }

    #[DataProvider('invalidExecutionIds')]
    public function testAnInvalidFieldFailsTheHistory(Payload $executionId): void
    {
        $this->expectException(\JsonException::class);

        self::history(self::started($executionId));
    }

    #[DataProvider('invalidExecutionIds')]
    public function testAnInvalidFieldFailsThePublicMethod(Payload $executionId): void
    {
        $this->expectException(\JsonException::class);

        JournalExecutionIdResolver::durableExecutionIdFromStartedAttributes(self::started($executionId));
    }

    #[DataProvider('invalidExecutionIds')]
    public function testTheVisibilityReadsAnInvalidFieldAsNoId(Payload $executionId): void
    {
        self::assertNull(JournalExecutionIdResolver::fromMemo(self::started($executionId)->getMemo()));
    }

    public function testTheVisibilityReadsTheId(): void
    {
        self::assertNull(JournalExecutionIdResolver::fromMemo(null));
        self::assertNull(JournalExecutionIdResolver::fromMemo(self::started(null)->getMemo()));
        self::assertSame('order/42', JournalExecutionIdResolver::fromMemo(self::started(JsonPlainPayload::encode('order/42'))->getMemo()));
    }

    public function testAPayloadThatIsNotJsonFailsTheReader(): void
    {
        $this->expectException(\JsonException::class);

        JournalExecutionIdResolver::fromStartedAttributes(self::started(new Payload(['data' => '{not json'])));
    }

    public function testAPayloadThatIsNotJsonFailsTheHistory(): void
    {
        $this->expectException(\JsonException::class);

        self::history(self::started(new Payload(['data' => '{not json'])));
    }

    public function testAPayloadThatIsNotJsonFailsThePublicMethod(): void
    {
        $this->expectException(\JsonException::class);

        JournalExecutionIdResolver::durableExecutionIdFromStartedAttributes(self::started(new Payload(['data' => '{not json'])));
    }

    private static function started(?Payload $executionId): WorkflowExecutionStartedEventAttributes
    {
        $memo = new Memo();
        if (null !== $executionId) {
            $memo->getFields()[JournalExecutionIdResolver::MEMO_KEY_DURABLE_EXECUTION_ID] = $executionId;
        }
        $attr = new WorkflowExecutionStartedEventAttributes();
        $attr->setMemo($memo);

        return $attr;
    }

    private static function history(WorkflowExecutionStartedEventAttributes $attr): TemporalExecutionHistory
    {
        $event = new HistoryEvent();
        $event->setEventId(1);
        $event->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED);
        $event->setWorkflowExecutionStartedEventAttributes($attr);

        return TemporalExecutionHistory::fromEvents([$event]);
    }
}
