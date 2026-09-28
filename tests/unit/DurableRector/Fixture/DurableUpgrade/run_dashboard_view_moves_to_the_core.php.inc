<?php

namespace unit\DurableRector\Fixture\DurableUpgrade;

final class DecoratesTheDashboard
{
    public function __construct(private \Gplanchat\Durable\Plugin\Dashboard\RunDashboardView $view)
    {
    }
}

?>
-----
<?php

namespace unit\DurableRector\Fixture\DurableUpgrade;

final class DecoratesTheDashboard
{
    public function __construct(private \Gplanchat\Durable\Observation\RunDashboard $view)
    {
    }
}

?>
