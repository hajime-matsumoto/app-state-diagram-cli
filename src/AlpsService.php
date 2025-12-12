<?php

declare(strict_types=1);

namespace AsdCli;

use Koriym\AppStateDiagram\DrawDiagram;
use Koriym\AppStateDiagram\LabelName;
use Koriym\AppStateDiagram\LabelNameTitle;
use Koriym\AppStateDiagram\Profile;
use Throwable;

final class AlpsService
{
    /**
     * Validate ALPS profile
     *
     * @return array{valid: bool, message: string, descriptors?: int, links?: int}
     */
    public function validate(string $alpsContent): array
    {
        $tempFile = $this->createTempFile($alpsContent);

        try {
            $profile = new Profile($tempFile, new LabelName());
            $descriptorCount = count($profile->descriptors);
            $linkCount = count($profile->links);

            return [
                'valid' => true,
                'message' => 'ALPS profile is valid',
                'descriptors' => $descriptorCount,
                'links' => $linkCount,
            ];
        } catch (Throwable $e) {
            return [
                'valid' => false,
                'message' => $e->getMessage(),
            ];
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Convert ALPS to DOT format
     *
     * @return array{success: bool, dot?: string, error?: string}
     */
    public function alps2dot(string $alpsContent, bool $useTitle = false): array
    {
        $tempFile = $this->createTempFile($alpsContent);

        try {
            $labelName = $useTitle ? new LabelNameTitle() : new LabelName();
            $profile = new Profile($tempFile, $labelName);
            $draw = new DrawDiagram();
            $dot = $draw($profile, $labelName);

            return [
                'success' => true,
                'dot' => $dot,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Get ALPS guide content
     */
    public function guide(): string
    {
        return $this->getEmbeddedGuide();
    }

    private function createTempFile(string $content): string
    {
        $trimmed = trim($content);
        $extension = ($trimmed !== '' && $trimmed[0] === '{') ? '.json' : '.xml';
        $tempFile = tempnam(sys_get_temp_dir(), 'alps_') . $extension;
        file_put_contents($tempFile, $content);

        return $tempFile;
    }

    private function getEmbeddedGuide(): string
    {
        return <<<'GUIDE'
# ALPS Best Practices

## What Makes a Good ALPS

1. **States = What the user sees** (e.g., ProductList, ProductDetail, Cart)
2. **Transitions = What the user does** (e.g., goProductDetail, doAddToCart)
3. **Self-documenting** - title explains purpose, doc describes behavior
4. **No unreachable states** - every state has an entry point
5. **Necessary and sufficient** - no over-abstraction

## Naming Conventions

| Type | Prefix | Example |
|------|--------|---------|
| Safe transition | `go` | `goProductList`, `goHome` |
| Unsafe transition | `do` | `doCreateUser`, `doAddToCart` |
| Idempotent transition | `do` | `doUpdateUser`, `doDeleteItem` |
| State/Page | PascalCase | `HomePage`, `ProductDetail` |
| Semantic field | camelCase | `userId`, `productName` |

## Three Layers

1. **Ontology** - Semantic descriptors (data fields)
2. **Taxonomy** - State descriptors (screens/pages)
3. **Choreography** - Transition descriptors (safe/unsafe/idempotent)

## Output Format (JSON)

```json
{
  "$schema": "https://alps-io.github.io/schemas/alps.json",
  "alps": {
    "title": "Application Title",
    "doc": {"value": "Description"},
    "descriptor": [
      {"id": "fieldName", "title": "Human Title"},
      {"id": "StateName", "title": "State Title", "descriptor": [
        {"href": "#fieldName"},
        {"href": "#goNextState"}
      ]},
      {"id": "goNextState", "type": "safe", "rt": "#TargetState", "title": "Navigate"}
    ]
  }
}
```

## Important Rules

- Safe transitions (go*) MUST include target state name: `rt="#ProductList"` -> `goProductList`
- Always validate after generation using validate command
- Tags are space-separated strings, not arrays
GUIDE;
    }
}
