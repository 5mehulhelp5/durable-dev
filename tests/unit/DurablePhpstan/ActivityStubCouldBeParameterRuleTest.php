<?php

declare(strict_types=1);

namespace unit\DurablePhpstan;

use Gplanchat\Durable\PHPStan\Rules\ActivityStubCouldBeParameterRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The rule only informs: it reports a stub the developer can move to an `#[Activities]` parameter
 * by hand, and stays silent whenever the move would change what runs.
 *
 * @extends RuleTestCase<ActivityStubCouldBeParameterRule>
 */
final class ActivityStubCouldBeParameterRuleTest extends RuleTestCase
{
    private const DIR = __DIR__ . '/Fixtures/StubCouldBeParameter/';

    private const TIP = 'Keep activityStub() when the options are computed at run time, or when a signal, update or helper method, or a closure, uses the stub. Otherwise ignore this with the identifier durable.activityStubCouldBeParameter.';

    protected function getRule(): Rule
    {
        return new ActivityStubCouldBeParameterRule();
    }

    public function testALocalStubWithoutOptionsIsReported(): void
    {
        $this->analyse([self::DIR . 'local-stub.php'], [
            [self::message('run', 'orders', ''), 16, self::TIP],
        ]);
    }

    public function testAConstructorStubReadOnlyByTheWorkflowMethodIsReported(): void
    {
        $this->analyse([self::DIR . 'constructor-stub.php'], [
            [self::message('run', 'orders', ''), 18, self::TIP],
        ]);
    }

    public function testLiteralPositionalOptionsAreSpelledAsAttributeFields(): void
    {
        $this->analyse([self::DIR . 'literal-options.php'], [
            [self::message('run', 'orders', ', attempts: 5, startToClose: 120, initialInterval: 2.5, nonRetryable: [\RuntimeException::class], taskQueue: \'billing\', backoffCoefficient: 3.0, maximumInterval: 60.0, summary: \'Charge\', cancellationType: \Gplanchat\Durable\Activity\ActivityCancellationType::Abandon'), 18, self::TIP],
        ]);
    }

    public function testNamedOptionsAreMappedAndAnEmptyListDropped(): void
    {
        $this->analyse([self::DIR . 'named-options.php'], [
            [self::message('run', 'orders', ', startToClose: 30.0, attempts: 3'), 17, self::TIP],
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function silentCases(): iterable
    {
        yield 'options computed at run time' => ['computed-options.php'];
        yield 'default(): the attribute alone would drop the retry backoff' => ['default-options.php'];
        yield 'of() with only an empty nonRetryable list' => ['empty-non-retryable.php'];
        yield 'an empty taskQueue, which the attribute refuses' => ['empty-task-queue.php'];
        yield 'a backoff coefficient the attribute refuses at registration' => ['refused-backoff.php'];
        yield 'a maximumInterval under the first retry delay, refused at registration' => ['refused-maximum-interval.php'];
        yield 'a stub a signal method reads' => ['signal-reads-stub.php'];
        yield 'a stub a helper method reads' => ['helper-reads-stub.php'];
        yield 'a stub read inside a closure' => ['closure-reads-stub.php'];
        yield 'a class with no workflow method' => ['not-a-workflow.php'];
        yield 'a workflow method an interface declares' => ['contract-interface.php'];
    }

    #[DataProvider('silentCases')]
    public function testTheMoveIsNotSuggestedWhenItWouldChangeWhatRuns(string $file): void
    {
        $this->analyse([self::DIR . $file], []);
    }

    private static function message(string $method, string $name, string $fields): string
    {
        return \sprintf(
            'Activity stub $%2$s could be a parameter of %1$s(): #[Activities(OrderActivities::class%3$s)] ActivityStub $%2$s, documented with @param ActivityStub<OrderActivities> $%2$s.',
            $method,
            $name,
            $fields,
        );
    }
}
