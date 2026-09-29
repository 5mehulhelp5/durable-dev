<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal\Codec;

use Gplanchat\Bridge\Temporal\Codec\PayloadCodecInterface;
use Gplanchat\Bridge\Temporal\Codec\PayloadCodecWorkflowServiceClient;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientInterface;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Header;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\WorkflowExecutionStartedEventAttributes;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;

/**
 * DUR055: every payload the application sends is encoded, and every payload it reads is decoded,
 * at the one place all RPCs pass — search attributes excepted, which the server must index.
 */
final class PayloadCodecWorkflowServiceClientTest extends TestCase
{
    public function testARequestLeavesWithItsPayloadsEncodedAndItsSearchAttributesInClear(): void
    {
        $sent = null;
        $inner = $this->createMock(WorkflowServiceClientInterface::class);
        $inner->method('StartWorkflowExecution')->willReturnCallback(static function (StartWorkflowExecutionRequest $request) use (&$sent): StartWorkflowExecutionResponse {
            $sent = $request;

            return new StartWorkflowExecutionResponse();
        });

        $request = new StartWorkflowExecutionRequest();
        $request->setInput(new Payloads(['payloads' => [new Payload(['data' => 'order-42'])]]));
        $request->setHeader(new Header(['fields' => ['trace' => new Payload(['data' => 'abc'])]]));
        $request->setSearchAttributes(new SearchAttributes(['indexed_fields' => ['DurableExecutionId' => new Payload(['data' => '"order-42"'])]]));

        (new PayloadCodecWorkflowServiceClient($inner, new ReversingCodec()))->StartWorkflowExecution($request);

        self::assertInstanceOf(StartWorkflowExecutionRequest::class, $sent);
        self::assertSame('24-redro', $sent->getInput()?->getPayloads()[0]->getData());
        self::assertSame('cba', $sent->getHeader()?->getFields()['trace']->getData());
        self::assertSame('"order-42"', $sent->getSearchAttributes()?->getIndexedFields()['DurableExecutionId']->getData());
    }

    /**
     * A caller that sends the same request twice (a retry) must not find it encoded twice.
     */
    public function testTheCallersRequestIsLeftAsItWas(): void
    {
        $inner = $this->createMock(WorkflowServiceClientInterface::class);
        $inner->method('StartWorkflowExecution')->willReturn(new StartWorkflowExecutionResponse());
        $request = new StartWorkflowExecutionRequest();
        $request->setInput(new Payloads(['payloads' => [new Payload(['data' => 'order-42'])]]));

        (new PayloadCodecWorkflowServiceClient($inner, new ReversingCodec()))->StartWorkflowExecution($request);

        self::assertSame('order-42', $request->getInput()?->getPayloads()[0]->getData());
    }

    public function testAResponseArrivesWithItsPayloadsDecoded(): void
    {
        $started = new WorkflowExecutionStartedEventAttributes();
        $started->setInput(new Payloads(['payloads' => [ReversingCodec::encoded('order-42')]]));
        $response = new GetWorkflowExecutionHistoryResponse();
        $response->setHistory(new History(['events' => [new HistoryEvent(['workflow_execution_started_event_attributes' => $started])]]));
        $inner = $this->createMock(WorkflowServiceClientInterface::class);
        $inner->method('GetWorkflowExecutionHistory')->willReturn($response);

        $read = (new PayloadCodecWorkflowServiceClient($inner, new ReversingCodec()))->GetWorkflowExecutionHistory(new GetWorkflowExecutionHistoryRequest());

        $input = $read->getHistory()?->getEvents()[0]->getWorkflowExecutionStartedEventAttributes()?->getInput();
        self::assertSame('order-42', $input?->getPayloads()[0]->getData());
    }
}

/**
 * Reverses the bytes and marks the payload: reversible, visible, and not a cipher.
 */
final class ReversingCodec implements PayloadCodecInterface
{
    public static function encoded(string $data): Payload
    {
        return new Payload(['metadata' => ['encoding' => 'test/reversed'], 'data' => strrev($data)]);
    }

    public function encode(Payload $payload): Payload
    {
        return self::encoded($payload->getData());
    }

    public function decode(Payload $payload): Payload
    {
        if ('test/reversed' !== (iterator_to_array($payload->getMetadata())['encoding'] ?? null)) {
            return $payload;
        }

        return new Payload(['metadata' => ['encoding' => 'json/plain'], 'data' => strrev($payload->getData())]);
    }
}
