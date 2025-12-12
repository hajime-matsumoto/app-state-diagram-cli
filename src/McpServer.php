<?php

declare(strict_types=1);

namespace AsdCli;

use function array_key_exists;
use function date;
use function feof;
use function fgets;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function flush;
use function fwrite;
use function getmypid;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function register_shutdown_function;
use function stream_select;
use function stream_set_blocking;
use function substr;
use function time;
use function trim;

use const FILE_APPEND;
use const STDERR;
use const STDIN;
use const STDOUT;

final class McpServer
{
    private const SERVER_NAME = 'asd-cli';
    private const SERVER_VERSION = '1.0.1';
    private const MCP_PROTOCOL_VERSION = '2024-11-05';
    private const PING_INTERVAL_SECONDS = 30;

    private int $pingCounter = 0;

    public function __construct(
        private AlpsService $service
    ) {
    }

    private function log(string $message): void
    {
        $logFile = '/tmp/asd-mcp.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }

    public function run(): void
    {
        $this->log('Server started (PID: ' . getmypid() . ')');
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null) {
                $this->log('SHUTDOWN ERROR: ' . json_encode($error));
            } else {
                $this->log('Clean shutdown');
            }
        });

        fwrite(STDERR, "Starting ASD MCP Server...\n");
        fwrite(STDERR, 'Server: ' . self::SERVER_NAME . ' v' . self::SERVER_VERSION . "\n");
        fwrite(STDERR, 'Protocol: MCP ' . self::MCP_PROTOCOL_VERSION . "\n\n");

        // Non-blocking mode with keepalive
        stream_set_blocking(STDIN, false);

        while (true) {
            $read = [STDIN];
            $write = null;
            $except = null;

            // Wait up to PING_INTERVAL_SECONDS for input
            $ready = stream_select($read, $write, $except, self::PING_INTERVAL_SECONDS);

            if ($ready === false) {
                $this->log('stream_select failed');
                break;
            }

            if ($ready === 0) {
                // Timeout - send keepalive ping
                $this->sendPing();
                $this->log('Sent keepalive ping');
                continue;
            }

            // Check if STDIN is closed
            if (feof(STDIN)) {
                $this->log('STDIN EOF detected');
                break;
            }

            $line = fgets(STDIN);
            if ($line === false) {
                continue; // No data available yet
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $this->log('Received: ' . substr($line, 0, 100) . '...');

            $request = json_decode($line, true);

            if (! is_array($request) || ! isset($request['jsonrpc'], $request['method'])) {
                $this->handleMalformed($request);
                continue;
            }

            $response = $this->handleRequest($request);

            if ($response !== null) {
                $this->writeResponse($response);
                $this->log('Response OK for: ' . ($request['method'] ?? 'unknown'));
            }
        }

        $this->log('Loop ended');
    }

    private function sendPing(): void
    {
        $this->pingCounter++;
        $ping = [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 'server-ping-' . $this->pingCounter,
        ];
        fwrite(STDOUT, json_encode($ping) . "\n");
        flush();
    }

    /**
     * @param array<string, mixed> $response
     */
    private function writeResponse(array $response): void
    {
        $encoded = json_encode($response);
        if ($encoded !== false) {
            fwrite(STDOUT, $encoded . "\n");
            flush();
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
            'ping' => $this->handlePing($id),
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
    private function handlePing(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => (object) [],
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
                                'file_path' => [
                                    'type' => 'string',
                                    'description' => 'Path to ALPS profile file (XML or JSON)',
                                ],
                            ],
                            'required' => ['file_path'],
                        ],
                    ],
                    [
                        'name' => 'alps2dot',
                        'description' => 'Convert ALPS profile to DOT format for Graphviz',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'file_path' => [
                                    'type' => 'string',
                                    'description' => 'Path to ALPS profile file (XML or JSON)',
                                ],
                                'use_title' => [
                                    'type' => 'boolean',
                                    'description' => 'Use human-readable titles instead of IDs',
                                    'default' => false,
                                ],
                            ],
                            'required' => ['file_path'],
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
        $filePath = $args['file_path'] ?? '';

        if (! is_string($filePath) || $filePath === '') {
            return $this->errorResult('file_path parameter is required');
        }

        if (! file_exists($filePath)) {
            return $this->errorResult("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return $this->errorResult("Failed to read file: {$filePath}");
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
        $filePath = $args['file_path'] ?? '';
        $useTitle = (bool) ($args['use_title'] ?? false);

        if (! is_string($filePath) || $filePath === '') {
            return $this->errorResult('file_path parameter is required');
        }

        if (! file_exists($filePath)) {
            return $this->errorResult("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return $this->errorResult("Failed to read file: {$filePath}");
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
