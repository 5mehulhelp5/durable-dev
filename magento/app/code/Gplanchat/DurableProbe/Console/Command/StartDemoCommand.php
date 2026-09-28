<?php

declare(strict_types=1);

namespace Gplanchat\DurableProbe\Console\Command;

use Gplanchat\DurableModule\Runtime\RuntimeFactory;
use Gplanchat\DurableProbe\Workflow\PlaceOrderWorkflow;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento durable:demo:start <execution-id>` — starts the demonstration order on the cluster
 * and returns, without waiting for it.
 *
 * The admin page of a run finds it by the id the application started it with (#514), and that id
 * may hold a slash. `durable:demo` cannot seed such a run: it executes in this process, and the
 * admin request is another one. Started through `workflowClient()`, the run lives on the cluster,
 * carries its execution id in the `durableExecutionId` memo, and the page reads it with no worker
 * running.
 *
 * Not `final`: Magento generates an `Interceptor` extending it, see {@see RunDemoCommand}.
 */
class StartDemoCommand extends Command
{
    public function __construct(
        private readonly RuntimeFactory $runtimeFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('durable:demo:start')
            ->setDescription('Starts the demonstration order on the Temporal cluster, under the execution id given')
            ->addArgument('execution-id', InputArgument::REQUIRED, 'The execution id to start it under; a slash is welcome');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $executionId = (string) $input->getArgument('execution-id');

        // The message for a missing DSN comes from the factory, and it names `app/etc/env.php`.
        $workflowId = $this->runtimeFactory->workflowClient()->startAsync(
            PlaceOrderWorkflow::class,
            ['orderId' => $executionId],
            $executionId,
        );

        $output->writeln(sprintf('  %s started on the cluster as %s', $executionId, $workflowId));

        return Command::SUCCESS;
    }
}
