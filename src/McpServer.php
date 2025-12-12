<?php

declare(strict_types=1);

namespace AsdCli;

use function array_key_exists;
use function fgets;
use function fwrite;
use function is_array;
use function json_decode;
use function json_encode;
use function trim;

use const STDERR;
use const STDIN;
use const STDOUT;

final class McpServer
{
    private const SERVER_NAME = 'asd-cli';
    private const SERVER_VERSION = '1.0.0';
    private const MCP_PROTOCOL_VERSION = '2024-11-05';

    public function __construct(
        private AlpsService $service
    ) {
    }

    public function run(): void
    {
        fwrite(STDERR, "Starting ASD MCP Server...\n");
        fwrite(STDERR, 'Server: ' . self::SERVER_NAME . ' v' . self::SERVER_VERSION . "\n");
        fwrite(STDERR, 'Protocol: MCP ' . self::MCP_PROTOCOL_VERSION . "\n\n");

        while ($line = fgets(STDIN)) {
            $request = json_decode(trim($line), true);

            if (! is_array($request) || ! isset($request['jsonrpc'], $request['method'])) {
                $this->handleMalformed($request);
                continue;
            }

            $response = $this->handleRequest($request);

            if ($response !== null) {
                $encoded = json_encode($response);
                if ($encoded !== false) {
                    fwrite(STDOUT, $encoded . "\n");
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>|null
     */
    private function handleRequest(array $request): ?array
    {
        $method = $request['method'];
        $id = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        return match ($method) {
            'initialize' => $this->initialize($id),
            'notifications/initialized' => null,
            'tools/list' => $this->toolsList($id),
            'tools/call' => $this->toolsCall($id, $params),
            'resources/list' => $this->resourcesList($id),
            'prompts/list' => $this->promptsList($id),
            default => $this->methodNotFound($request),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function initialize(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => self::MCP_PROTOCOL_VERSION,
                'serverInfo' => [
                    'name' => self::SERVER_NAME,
                    'version' => self::SERVER_VERSION,
                ],
                'capabilities' => [
                    'tools' => (object) [],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'tools' => [
                    [
                        'name' => 'validate_alps',
                        'description' => 'Validate ALPS profile and check for errors',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'alps_content' => [
                                    'type' => 'string',
                                    'description' => 'ALPS profile content (XML or JSON)',
                                ],
                            ],
                            'required' => ['alps_content'],
                        ],
                    ],
                    [
                        'name' => 'alps2dot',
                        'description' => 'Convert ALPS profile to DOT format for Graphviz',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'alps_content' => [
                                    'type' => 'string',
                                    'description' => 'ALPS profile content (XML or JSON)',
                                ],
                                'use_title' => [
                                    'type' => 'boolean',
                                    'description' => 'Use human-readable titles instead of IDs',
                                    'default' => false,
                                ],
                            ],
                            'required' => ['alps_content'],
                        ],
                    ],
                    [
                        'name' => 'alps_guide',
                        'description' => 'Get ALPS best practices and reference guide',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => (object) [],
                            'required' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function toolsCall(mixed $id, array $params): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        $result = match ($toolName) {
            'validate_alps' => $this->handleValidate($arguments),
            'alps2dot' => $this->handleAlps2Dot($arguments),
            'alps_guide' => $this->handleGuide(),
            default => $this->unknownTool($toolName),
        };

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function handleValidate(array $args): array
    {
        $content = $args['alps_content'] ?? '';

        if (! is_string($content) || $content === '') {
            return $this->errorResult('alps_content parameter is required');
        }

        $result = $this->service->validate($content);

        if ($result['valid']) {
            $text = "Valid ALPS profile\n";
            $text .= "Descriptors: {$result['descriptors']}\n";
            $text .= "Links: {$result['links']}";

            return $this->successResult($text);
        }

        return $this->errorResult("Invalid: {$result['message']}");
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function handleAlps2Dot(array $args): array
    {
        $content = $args['alps_content'] ?? '';
        $useTitle = (bool) ($args['use_title'] ?? false);

        if (! is_string($content) || $content === '') {
            return $this->errorResult('alps_content parameter is required');
        }

        $result = $this->service->alps2dot($content, $useTitle);

        if ($result['success']) {
            return $this->successResult($result['dot'] ?? '');
        }

        return $this->errorResult($result['error'] ?? 'Unknown error');
    }

    /**
     * @return array<string, mixed>
     */
    private function handleGuide(): array
    {
        return $this->successResult($this->service->guide());
    }

    /**
     * @return array<string, mixed>
     */
    private function unknownTool(string $name): array
    {
        return $this->errorResult("Unknown tool: {$name}");
    }

    /**
     * @return array<string, mixed>
     */
    private function resourcesList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => ['resources' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function promptsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => ['prompts' => []],
        ];
    }

    /**
     * @param array<string, mixed>|null $request
     */
    private function handleMalformed(?array $request): void
    {
        if (is_array($request) && array_key_exists('id', $request) && $request['id'] !== null) {
            $response = [
                'jsonrpc' => '2.0',
                'error' => ['code' => -32600, 'message' => 'Invalid Request'],
                'id' => $request['id'],
            ];
            $encoded = json_encode($response);
            if ($encoded !== false) {
                fwrite(STDOUT, $encoded . "\n");
            }
        }
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>|null
     */
    private function methodNotFound(array $request): ?array
    {
        if (! array_key_exists('id', $request) || $request['id'] === null) {
            return null;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $request['id'],
            'error' => ['code' => -32601, 'message' => 'Method not found'],
        ];
    }

    /**
     * @return array{content: list<array{type: string, text: string}>, isError: false}
     */
    private function successResult(string $text): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => false,
        ];
    }

    /**
     * @return array{content: list<array{type: string, text: string}>, isError: true}
     */
    private function errorResult(string $text): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => true,
        ];
    }
}
