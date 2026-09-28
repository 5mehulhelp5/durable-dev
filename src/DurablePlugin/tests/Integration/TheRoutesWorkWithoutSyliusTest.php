<?php

declare(strict_types=1);

namespace Gplanchat\Durable\Plugin\Tests\Integration;

use Gplanchat\Durable\Plugin\DurablePlugin;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

/**
 * #520: an application without Sylius imports the plugin's routes and boots. The pages answer
 * #519's not-found, since the Sylius admin they live in is absent, instead of failing on a
 * parameter only Sylius defines.
 */
final class TheRoutesWorkWithoutSyliusTest extends TestCase
{
    private string $cacheDir;

    private ?Kernel $kernel = null;

    /** @var array{exception: ?callable, error: ?callable} */
    private array $handlers;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/durable-plugin-routes-' . bin2hex(random_bytes(4));
        $this->handlers = self::currentHandlers();
    }

    /**
     * A booted kernel installs error and exception handlers (Symfony 6.4.0's ErrorHandler never
     * takes them back), and PHPUnit marks the test risky unless both stacks end as they started.
     */
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        // ponytail: ten pops at most, far more than one kernel boot pushes.
        for ($i = 0; $i < 10 && self::currentHandlers()['exception'] !== $this->handlers['exception']; ++$i) {
            restore_exception_handler();
        }
        for ($i = 0; $i < 10 && self::currentHandlers()['error'] !== $this->handlers['error']; ++$i) {
            restore_error_handler();
        }
        (new Filesystem())->remove($this->cacheDir);
    }

    /** @return array{exception: ?callable, error: ?callable} */
    private static function currentHandlers(): array
    {
        $exception = set_exception_handler(null);
        restore_exception_handler();
        $error = set_error_handler(null);
        restore_error_handler();

        return ['exception' => $exception, 'error' => $error];
    }

    public function testWithoutSyliusTheRunsListIsNotFoundUnderThePluginsOwnPrefix(): void
    {
        $response = $this->kernel([])->handle(Request::create('/admin/durable/runs'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testTheFormerDashboardAddressStillLeadsToTheList(): void
    {
        $response = $this->kernel([])->handle(Request::create('/admin/durable/dashboard'));

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/admin/durable/runs', $response->headers->get('Location'));
    }

    /** The application's admin prefix wins, set in its config as Sylius sets it. */
    public function testASyliusAdminPrefixIsKept(): void
    {
        $response = $this->kernel(['sylius_admin.path_name' => 'back-office'])->handle(Request::create('/back-office/durable/dashboard'));

        self::assertSame('/back-office/durable/runs', $response->headers->get('Location'));
    }

    /** @param array<string, string> $parameters */
    private function kernel(array $parameters): Kernel
    {
        return $this->kernel = new class ('test', false, $this->cacheDir, $parameters) extends Kernel {
            /** @param array<string, string> $parameters */
            public function __construct(string $environment, bool $debug, private readonly string $dir, private readonly array $parameters)
            {
                parent::__construct($environment, $debug);
            }

            public function registerBundles(): iterable
            {
                return [new FrameworkBundle(), new TwigBundle(), new DurablePlugin()];
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(function (ContainerBuilder $container): void {
                    // The pages' 404 is expected here; it has no business on the test output.
                    $container->register('logger', NullLogger::class);
                    foreach ($this->parameters as $name => $value) {
                        $container->setParameter($name, $value);
                    }
                    $container->loadFromExtension('framework', [
                        'secret' => 'test',
                        'test' => true,
                        'http_method_override' => false,
                        'handle_all_throwables' => true,
                        'php_errors' => ['log' => true],
                        'router' => ['resource' => '@DurablePlugin/config/routes.yaml', 'utf8' => true],
                    ]);
                });
            }

            public function getProjectDir(): string
            {
                return $this->dir;
            }

            public function getCacheDir(): string
            {
                return $this->dir . '/cache';
            }

            public function getLogDir(): string
            {
                return $this->dir . '/log';
            }
        };
    }
}
