<?php

declare(strict_types=1);

namespace AsdCli;

use function array_shift;
use function file_exists;
use function file_get_contents;
use function fwrite;
use function in_array;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

final class Cli
{
    private const VERSION = '1.0.1';

    private AlpsService $service;

    public function __construct()
    {
        $this->service = new AlpsService();
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        array_shift($argv); // Remove script name
        $command = array_shift($argv) ?? 'help';

        return match ($command) {
            'serve' => $this->serve(),
            'alps2dot' => $this->alps2dot($argv),
            'validate' => $this->validate($argv),
            'guide' => $this->guide(),
            'version', '-v', '--version' => $this->version(),
            'help', '-h', '--help' => $this->help(),
            default => $this->unknown($command),
        };
    }

    private function serve(): int
    {
        $server = new McpServer($this->service);
        $server->run();

        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function alps2dot(array $args): int
    {
        $useTitle = false;
        $file = null;

        foreach ($args as $arg) {
            if (in_array($arg, ['--title', '-t'], true)) {
                $useTitle = true;
            } elseif ($arg[0] !== '-') {
                $file = $arg;
            }
        }

        if ($file === null) {
            $this->error('Usage: asd-cli alps2dot [--title] <file>');

            return 1;
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return 1;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            $this->error("Cannot read file: {$file}");

            return 1;
        }

        $result = $this->service->alps2dot($content, $useTitle);

        if (! $result['success']) {
            $this->error('Error: ' . ($result['error'] ?? 'Unknown error'));

            return 1;
        }

        fwrite(STDOUT, $result['dot'] ?? '');

        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function validate(array $args): int
    {
        $file = $args[0] ?? null;

        if ($file === null) {
            $this->error('Usage: asd-cli validate <file>');

            return 1;
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return 1;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            $this->error("Cannot read file: {$file}");

            return 1;
        }

        $result = $this->service->validate($content);

        if ($result['valid']) {
            fwrite(STDOUT, "Valid ALPS profile" . PHP_EOL);
            fwrite(STDOUT, "  Descriptors: {$result['descriptors']}" . PHP_EOL);
            fwrite(STDOUT, "  Links: {$result['links']}" . PHP_EOL);

            return 0;
        }

        $this->error("Invalid ALPS profile: {$result['message']}");

        return 1;
    }

    private function guide(): int
    {
        fwrite(STDOUT, $this->service->guide() . PHP_EOL);

        return 0;
    }

    private function version(): int
    {
        fwrite(STDOUT, 'asd-cli version ' . self::VERSION . PHP_EOL);

        return 0;
    }

    private function help(): int
    {
        $help = <<<'HELP'
asd-cli - ALPS Validation And Processing

Usage:
  asd-cli <command> [options] [arguments]

Commands:
  serve              Start MCP server (for Claude Desktop integration)
  alps2dot <file>    Convert ALPS profile to DOT format
  validate <file>    Validate ALPS profile
  guide              Show ALPS best practices guide
  version            Show version information
  help               Show this help message

Options:
  alps2dot:
    --title, -t      Use human-readable titles instead of IDs

Examples:
  asd-cli serve
  asd-cli alps2dot profile.json > diagram.dot
  asd-cli alps2dot --title profile.xml
  asd-cli validate profile.json
  asd-cli guide

MCP Server Configuration (Claude Desktop):
  {
    "mcpServers": {
      "alps": {
        "command": "/path/to/asd-cli",
        "args": ["serve"]
      }
    }
  }

HELP;
        fwrite(STDOUT, $help);

        return 0;
    }

    private function unknown(string $command): int
    {
        $this->error("Unknown command: {$command}");
        $this->error("Run 'asd-cli help' for usage information.");

        return 1;
    }

    private function error(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}
