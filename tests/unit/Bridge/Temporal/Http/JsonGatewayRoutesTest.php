<?php

declare(strict_types=1);

namespace unit\Gplanchat\Bridge\Temporal\Http;

use GPBMetadata\Temporal\Api\Workflowservice\V1\Service;
use Gplanchat\Bridge\Temporal\Http\JsonGatewayRoutes;
use Gplanchat\Bridge\Temporal\WorkflowServiceClientInterface;
use PHPUnit\Framework\TestCase;

/**
 * The route table is copied from the protobuf HTTP annotations by hand; this holds it to the
 * descriptor shipped with the installed `Temporal\Api` classes, so an API upgrade that moves a
 * route fails here rather than as a 404 in production.
 */
final class JsonGatewayRoutesTest extends TestCase
{
    public function testEveryRouteMatchesTheDescriptorBinding(): void
    {
        $bindings = self::bindingsFromDescriptor();

        foreach (JsonGatewayRoutes::ROUTES as $rpc => [$verb, $path]) {
            self::assertArrayHasKey($rpc, $bindings, \sprintf('%s has no HTTP binding in the descriptor.', $rpc));
            self::assertContains($verb . ' ' . $path, $bindings[$rpc], \sprintf('%s is bound elsewhere in the descriptor.', $rpc));
        }
    }

    public function testEveryInterfaceRpcWithoutARouteHasNoBindingInTheDescriptor(): void
    {
        $bindings = self::bindingsFromDescriptor();

        foreach ((new \ReflectionClass(WorkflowServiceClientInterface::class))->getMethods() as $method) {
            $rpc = $method->getName();
            if (isset(JsonGatewayRoutes::ROUTES[$rpc])) {
                continue;
            }
            self::assertArrayNotHasKey($rpc, $bindings, \sprintf('%s is bound in the descriptor but missing from the route table.', $rpc));
        }
    }

    /**
     * @return array<string, list<string>> rpc => ["VERB /api/v1/..."]
     */
    private static function bindingsFromDescriptor(): array
    {
        $source = self::serializedDescriptor();
        // Each method block names the RPC then its request type; the http rule bytes follow, with
        // the verb as the HttpRule field tag (2 get, 3 put, 4 post, 5 delete, 6 patch).
        preg_match_all('/([A-Z][A-Za-z]+)[\s\S]{1,3}\.temporal\.api\.workflowservice\.v1\.\1Request/', $source, $matches, \PREG_OFFSET_CAPTURE);
        $verbs = [0x12 => 'GET', 0x1a => 'PUT', 0x22 => 'POST', 0x2a => 'DELETE', 0x32 => 'PATCH'];

        $bindings = [];
        foreach ($matches[1] as $i => [$rpc, $offset]) {
            $end = $matches[1][$i + 1][1] ?? \strlen($source);
            $block = substr($source, $offset, $end - $offset);
            preg_match_all('~([\x12\x1a\x22\x2a\x32])(.)(/api/v1/[a-z0-9_{}/.-]+)~s', $block, $rules, \PREG_SET_ORDER);
            foreach ($rules as $rule) {
                $bindings[$rpc][] = $verbs[\ord($rule[1])] . ' ' . $rule[3];
            }
        }

        return $bindings;
    }

    /**
     * The descriptor bytes, read from the string literal the metadata class hands to the pool,
     * whichever quoting its generator chose.
     */
    private static function serializedDescriptor(): string
    {
        $file = (string) (new \ReflectionClass(Service::class))->getFileName();
        $literal = '';
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (\is_array($token) && \T_CONSTANT_ENCAPSED_STRING === $token[0] && \strlen($token[1]) > \strlen($literal)) {
                $literal = $token[1];
            }
        }
        $body = substr($literal, 1, -1);

        return '"' === ($literal[0] ?? '') ? stripcslashes($body) : strtr($body, ['\\\\' => '\\', "\\'" => "'"]);
    }
}
