<?php

declare(strict_types=1);

namespace integration\Temporal\Fixtures;

use Gplanchat\Durable\Demo\Contracts\Billing\BillingContract;
use Gplanchat\Durable\Demo\Contracts\Billing\BillingServed;
use Gplanchat\Durable\Demo\Contracts\Delivery\DeliveryContract;
use Gplanchat\Durable\Demo\Contracts\Delivery\DeliveryServed;
use Gplanchat\Durable\Demo\Contracts\Stock\StockContract;
use Gplanchat\Durable\Demo\Contracts\Stock\StockServed;
use Gplanchat\Durable\Nexus\NexusOperationName;
use Gplanchat\Durable\Nexus\NexusService;
use Gplanchat\Durable\Nexus\Serving\NexusContractResolver;
use Gplanchat\Durable\Nexus\Serving\NexusHandlerInvoker;
use Gplanchat\Durable\Nexus\Serving\NexusOperationRegistry;
use Gplanchat\Durable\WorkflowEnvironment;
use Gplanchat\Durable\WorkflowRegistry;

/**
 * What `nexus-demo-harness.php` serves (#663): the demo contracts, with answers fixed and shaped
 * like the benches' own, so a bench job has a counterpart without the other three benches.
 *
 * The immediate operations go through `NexusHandlerInvoker` and the deferred ones through
 * `registerFulfilment()`: the pieces a host wires, not a reconstruction of them.
 */
final class DemoHarness
{
    /** The endpoint names the benches call, as `bin/demo-nexus` creates them. */
    public const ENDPOINTS = [
        'stock' => 'demo-shop-stock',
        'billing' => 'demo-business-billing',
        'delivery' => 'demo-laravel-delivery',
    ];

    private const CONTRACTS = [
        'stock' => StockContract::class,
        'billing' => BillingContract::class,
        'delivery' => DeliveryContract::class,
    ];

    /** Workflow type => the operation it fulfils. */
    private const FULFILMENTS = [
        'DemoHarnessCharge' => [BillingContract::class, 'charge'],
        'DemoHarnessShip' => [DeliveryContract::class, 'ship'],
    ];

    /**
     * @param list<string> $services keys of {@see self::ENDPOINTS}
     */
    public static function registry(array $services): NexusOperationRegistry
    {
        $registry = NexusOperationRegistry::routedBy('temporal');
        $resolver = new NexusContractResolver(null);
        $handler = new class implements BillingServed, StockServed, DeliveryServed {
            public function verify(string $order, int $amount, string $currency): array
            {
                return ['accepted' => true, 'reason' => null];
            }

            public function reserve(string $order, array $lines): array
            {
                return ['reserved' => true, 'missing' => []];
            }

            public function schedule(string $order, array $lines): array
            {
                return ['scheduled' => true, 'slot' => 'next-day 09:00-12:00', 'carrier' => 'courier', 'reason' => null];
            }
        };

        foreach ($services as $service) {
            $contract = self::CONTRACTS[$service];
            foreach ($resolver->operations($contract) as $method => $operation) {
                $workflowType = array_search([$contract, $method], self::FULFILMENTS, true);
                if (false === $workflowType) {
                    $registry->register(NexusService::named($service), NexusOperationName::named($operation), (new NexusHandlerInvoker($handler, $contract, $method))(...));
                } else {
                    $registry->registerFulfilment(NexusService::named($service), NexusOperationName::named($operation), $workflowType);
                }
            }
        }

        return $registry;
    }

    public static function registerWorkflows(WorkflowRegistry $registry): void
    {
        $registry->registerFactory('DemoHarnessCharge', static fn(array $input) => static fn(WorkflowEnvironment $env): array => [
            'receipt' => 'RCP-' . $input['order'],
            'charged' => (int) $input['amount'],
        ]);
        $registry->registerFactory('DemoHarnessShip', static fn(array $input) => static fn(WorkflowEnvironment $env): array => [
            'shipped' => true,
            'tracking' => 'TRK-' . $input['order'],
        ]);

        // The caller a bench's workflow would be: stubs on the contracts, one per endpoint.
        $registry->registerFactory('DemoHarnessCaller', static fn(array $input) => static function (WorkflowEnvironment $env) use ($input): array {
            $prefix = (string) ($input['prefix'] ?? '');
            $billing = $env->nexusStub(BillingContract::class, $prefix . self::ENDPOINTS['billing']);
            $stock = $env->nexusStub(StockContract::class, $prefix . self::ENDPOINTS['stock']);
            $delivery = $env->nexusStub(DeliveryContract::class, $prefix . self::ENDPOINTS['delivery']);

            return [
                'verify' => $env->await($billing->verify('ORD-1', 1200, 'EUR')),
                'reserve' => $env->await($stock->reserve('ORD-1', ['SKU-1' => 1])),
                'schedule' => $env->await($delivery->schedule('ORD-1', ['SKU-1' => 1])),
                'charge' => $env->await($billing->charge('ORD-1', 1200, 'EUR')),
                'ship' => $env->await($delivery->ship('ORD-1', 'next-day 09:00-12:00')),
            ];
        });
    }
}
