# Scratch Validator

`ScratchValidator` validates a `ScratchProject` and returns structured error
messages. Designed for validating generated Scratch code before use.

## Basic usage

```php
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;

$project = ScratchProject::fromSb3File('project.sb3');
$result = ScratchValidator::validate($project);

if ($result->isValid) {
    echo "Project is valid\n";
} else {
    foreach ($result->errors as $error) {
        printf("[%s] %s at %s: %s\n", $error->severity->value, $error->code->value, $error->path, $error->message);
    }
}
```

Filter by code with the typed enum (no string comparisons):

```php
use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;

$missingAssets = array_filter(
    $result->errors,
    static fn($e) => $e->code === ErrorCode::MISSING_ASSET,
);
```

## `ValidationResult` and `ValidationError`

```php
final class ValidationResult
{
    public readonly bool $isValid;          // true iff no error-severity issues
    /** @var list<ValidationError> */
    public readonly array $errors;
}

final class ValidationError
{
    public readonly ErrorCode $code;        // typed enum case (see below)
    public readonly ValidationPath $path;   // dot-notation path plus structured target/block fields
    public readonly string $message;        // human-readable description
    public readonly ValidationSeverity $severity; // ValidationSeverity::Error | ::Warning
}

final class ValidationPath implements Stringable
{
    public readonly string $path;           // "targets[1].blocks.abc.inputs.STEPS"
    public readonly ?int $targetIndex;      // 1 (null for project-level paths)
    public readonly ?string $blockId;       // "abc" (null when not inside a block)
    public function __toString(): string;   // returns $path
}
```

Filter findings without parsing strings:

```php
$blockErrors = array_filter(
    $result->errors,
    static fn($e) => $e->path->blockId === 'abc',
);
```

`isValid` is `true` only when there are zero `ValidationSeverity::Error` entries.
Warnings are informational and do not affect validity.

## Options

```php
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;
use CodeClubLuxembourg\Sb3Toolkit\Validator\OpcodeRegistry;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;

$validator = new ScratchValidator(
    assets: $assetIndex,    // optional: checks costume/sound md5ext presence
    strictOpcodes: false,   // default false: unknown opcodes are warnings; true makes them errors
    registry: null,         // optional: override the built-in opcode registry
);
$result = $validator->run($project);

// Or as a one-off static call:
$result = ScratchValidator::validate($project, strictOpcodes: true);
```

## Validation rules

### Structural (`MISSING_FIELD`, `WRONG_TYPE`, `MISSING_STAGE`, `DUPLICATE_STAGE`)

- Project must have exactly one `isStage: true` target.
- Each target must have a non-empty `name` and correctly typed `variables`, `lists`,
  `broadcasts`, `blocks`, `comments`, `costumes`, `sounds`, `volume`.
- Each costume must have `name`, `assetId`, `md5ext`, `dataFormat` as strings plus
  numeric `rotationCenterX`/`Y`.
- Each sound must have `name`, `assetId`, `md5ext`, `dataFormat` as strings plus
  numeric `rate` and `sampleCount`.
- Sprites must have finite numeric `x`, `y`, `size`, `direction`; boolean `visible`;
  non-negative integer `layerOrder`; and a valid `rotationStyle`.

### Block graph (`DANGLING_NEXT`, `DANGLING_PARENT`, `INCONSISTENT_NEXT_PARENT`, `BLOCK_CYCLE`, `DANGLING_INPUT_REF`)

- `next` and `parent` must reference IDs that exist in the same `blocks` map.
- If `A.next = B` then `B.parent` must equal `A`.
- No cycles in `next`-chains.
- Block-reference inputs must point at existing blocks.
- Null block entries (`blocks[id] = null`) are allowed and skipped.

### Opcode registry (`UNKNOWN_OPCODE`, `MISSING_INPUT`, `MISSING_FIELD_VALUE`)

- Block opcodes are checked against the built-in registry (~140 opcodes).
- Unknown opcodes are a `Warning` by default, or an `Error` with `strictOpcodes: true`.
- Required inputs and fields for known opcodes must be present.
- `procedures_call` inputs are not checked (they depend on `mutation`).

### Asset references (`MISSING_ASSET`)

Only checked when `assets` is provided. Each costume and sound `md5ext` must be
present in the asset index.

### Variable/list references (`DANGLING_VARIABLE_REF`, `DANGLING_LIST_REF`)

For data blocks, the variable/list ID in the block's field must exist on the
target sprite or the stage (stage variables are global).

### Warnings only (`TOPLEVEL_NO_COORDS`, `SHADOW_NO_PARENT`)

- Top-level blocks without `x`/`y` coordinates produce a warning.
- Shadow blocks without a parent produce a warning.

## Error codes reference

| Code | Severity | Description |
|------|----------|-------------|
| `MISSING_FIELD` | error | Required field absent or wrong type |
| `WRONG_TYPE` | error | Field present but wrong type |
| `MISSING_STAGE` | error | No stage target found |
| `DUPLICATE_STAGE` | error | More than one `isStage: true` target |
| `UNKNOWN_OPCODE` | warning/error | Opcode not in registry |
| `MISSING_INPUT` | error | Required input for opcode absent |
| `MISSING_FIELD_VALUE` | error | Required field for opcode absent |
| `DANGLING_NEXT` | error | `next` references a non-existent ID |
| `DANGLING_INPUT_REF` | error | Input block reference points to non-existent ID |
| `DANGLING_PARENT` | error | `parent` points to non-existent ID |
| `INCONSISTENT_NEXT_PARENT` | error | `A.next = B` but `B.parent ≠ A` |
| `BLOCK_CYCLE` | error | Cycle detected in `next`-chain |
| `MISSING_ASSET` | error | Costume/sound `md5ext` not in asset index |
| `DANGLING_VARIABLE_REF` | error | Variable ID referenced in block not defined |
| `DANGLING_LIST_REF` | error | List ID referenced in block not defined |
| `TOPLEVEL_NO_COORDS` | warning | Top-level block missing `x`/`y` |
| `SHADOW_NO_PARENT` | warning | Shadow block has no parent |

## Custom opcode registry

```php
use CodeClubLuxembourg\Sb3Toolkit\Validator\OpcodeRegistry;
use CodeClubLuxembourg\Sb3Toolkit\Validator\OpcodeSpec;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;

$registry = OpcodeRegistry::default()->withSpecs([
    'myextension_doSomething' => new OpcodeSpec(requiredInputs: ['VALUE'], requiredFields: []),
]);

$result = ScratchValidator::validate($project, registry: $registry);
```

## Writing a custom rule

`ScratchValidator` ships with four built-in rules (`StructureRule`,
`BlocksRule`, `AssetsRule`, `VariablesRule`). To layer your own check on top,
implement the `Rule` contract and register it via `withRule()` (immutable —
returns a new validator) or pass it through the `rules:` argument on the
static `validate()` convenience.

```php
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\Rule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationError;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationPath;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationSeverity;

/**
 * Flag every sprite whose `name` contains whitespace (a tooling-specific rule
 * that the built-in StructureRule doesn't enforce).
 */
final class NoWhitespaceInSpriteNames implements Rule
{
    public function validate(ScratchProject $project): iterable
    {
        foreach ($project->data['targets'] as $index => $target) {
            if (($target['isStage'] ?? false) === true) {
                continue;
            }
            $name = $target['name'] ?? '';
            if (\is_string($name) && preg_match('/\s/', $name) === 1) {
                yield new ValidationError(
                    ErrorCode::WRONG_TYPE,
                    new ValidationPath(\sprintf('targets[%d].name', $index), $index),
                    \sprintf('Sprite name "%s" contains whitespace', $name),
                    ValidationSeverity::Warning,
                );
            }
        }
    }
}

// Either call style works:
$result = ScratchValidator::validate($project, rules: [new NoWhitespaceInSpriteNames()]);

// or, when reusing a configured validator across many projects:
$validator = (new ScratchValidator(assets: $project->assets, strictOpcodes: true))
    ->withRule(new NoWhitespaceInSpriteNames());
$result = $validator->run($project);
```

User rules run *after* the built-ins, so they see the project in the same
state. Wrap paths in `ValidationPath` to expose structured `targetIndex` /
`blockId` for downstream filtering:

```php
$onSprite0 = array_filter($result->errors, static fn($e) => $e->path->targetIndex === 0);
```
