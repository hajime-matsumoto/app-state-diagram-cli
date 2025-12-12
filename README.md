# asd-cli

ALPS CLI tool and MCP server - standalone binary, no PHP required.

## Installation

### Quick Install (Recommended)

```bash
curl -fsSL https://raw.githubusercontent.com/hajime-matsumoto/app-state-diagram-cli/main/install.sh | bash
```

Custom install directory:

```bash
INSTALL_DIR=~/.local/bin curl -fsSL https://raw.githubusercontent.com/hajime-matsumoto/app-state-diagram-cli/main/install.sh | bash
```

### Manual Download

Download from [Releases](https://github.com/hajime-matsumoto/app-state-diagram-cli/releases):

| Platform | Binary |
|----------|--------|
| Linux x86_64 | `asd-cli-linux-x86_64` |
| Linux ARM64 | `asd-cli-linux-aarch64` |
| macOS Intel | `asd-cli-macos-x86_64` |
| macOS Apple Silicon | `asd-cli-macos-aarch64` |

### Using Phar (requires PHP)

```bash
curl -L -o asd-cli.phar https://github.com/hajime-matsumoto/app-state-diagram-cli/releases/latest/download/asd-cli.phar
php asd-cli.phar --help
```

## CLI Usage

```bash
asd-cli validate profile.json       # Validate ALPS profile
asd-cli alps2dot profile.xml        # Convert to DOT format
asd-cli alps2dot --title profile.json  # Use human-readable titles
asd-cli guide                       # Show best practices
asd-cli serve                       # Start MCP server
```

### Convert DOT to SVG

```bash
asd-cli alps2dot profile.json | dot -Tsvg > diagram.svg
```

## MCP Server

### Claude Desktop

Add to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS):

```json
{
  "mcpServers": {
    "alps": {
      "command": "/usr/local/bin/asd-cli",
      "args": ["serve"]
    }
  }
}
```

### Tools

| Tool | Description |
|------|-------------|
| `validate_alps` | Validate ALPS profile |
| `alps2dot` | Convert ALPS to DOT |
| `alps_guide` | Best practices guide |

## Development

```bash
git clone https://github.com/hajime-matsumoto/app-state-diagram-cli.git
cd app-state-diagram-cli
composer install
php bin/asd-cli --help
```

### Build

```bash
composer install --no-dev
box compile          # build/asd-cli.phar
```

## License

MIT
