<?php

namespace unit\DurableRector\Fixture\Activity;

// 'Billing.Order.' would give 'Billing.Order.charge' on both engines; the rule does not attempt it.
#[\Temporal\Activity\ActivityInterface(prefix: 'Billing.Order.')]
interface BillingOrderActivities
{
    public function charge(string $orderId): string;
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\Activity;

// 'Billing.Order.' would give 'Billing.Order.charge' on both engines; the rule does not attempt it.
// durable-rector: activity prefix "Billing.Order." cannot become a Durable activity name — name the activities by hand
#[\Temporal\Activity\ActivityInterface(prefix: 'Billing.Order.')]
interface BillingOrderActivities
{
    public function charge(string $orderId): string;
}

?>
