<?php

declare(strict_types=1);

/**
 * Generates the typed per-block factory classes in src/Builder/Blocks/ from the
 * enriched opcode metadata in {@see CodeClubLuxembourg\Sb3Toolkit\Validator\OpcodeRegistry}.
 *
 * Run via `composer generate-blocks`. The output is committed; runtime users
 * never need to run this. Re-run whenever the opcode metadata changes.
 */

use CodeClubLuxembourg\Sb3Toolkit\Validator\OpcodeRegistry;
use Symfony\Component\Process\Process;

require __DIR__ . '/../vendor/autoload.php';

/** Opcode prefix => category class name. */
const CATEGORIES = [
    'motion' => 'Motion',
    'looks' => 'Looks',
    'sound' => 'Sound',
    'event' => 'Events',
    'control' => 'Control',
    'sensing' => 'Sensing',
    'operator' => 'Operators',
    'data' => 'Data',
    'pen' => 'Pen',
];

const OUTPUT_DIR = __DIR__ . '/../src/Builder/Blocks';
const BLOCKS_NS = 'CodeClubLuxembourg\\Sb3Toolkit\\Builder\\Blocks';

/**
 * Convert a SCRATCH_INPUT name to a camelCase PHP parameter name.
 */
function paramName(string $name): string
{
    $parts = explode('_', strtolower($name));
    $first = array_shift($parts);

    return $first . implode('', array_map('ucfirst', $parts));
}

/**
 * @param array{type: string, doc?: string} $param
 *
 * @return array{params: list<array{type: string, name: string, variadic?: bool, doc?: string}>, calls: list<string>, uses: list<string>}
 */
function buildMethodParts(string $opcode, array $spec): array
{
    $inputs = $spec['i'] ?? [];
    $fields = $spec['f'] ?? [];

    $params = [];
    $calls = [];
    $uses = [];

    $substacks = [];
    foreach ($inputs as $name => $role) {
        if ($role === 'substack') {
            $substacks[] = $name;

            continue;
        }
        if (str_starts_with($role, 'menu:')) {
            // role format: menu:<menuOpcode>:<menuField>[:enum:<EnumName>]
            $parts = explode(':', $role, 5);
            $menuOpcode = $parts[1] ?? '';
            $menuField = $parts[2] ?? '';
            $enum = (($parts[3] ?? '') === 'enum') ? ($parts[4] ?? '') : null;

            $p = paramName($name);
            $type = $enum !== null ? "{$enum}|string|Block" : 'string|Block';
            $valueExpr = $enum !== null
                ? \sprintf('$%s instanceof %s ? $%s->value : $%s', $p, $enum, $p, $p)
                : '$' . $p;
            $params[] = ['type' => $type, 'name' => $p];
            $calls[] = \sprintf("->input('%s', Input::menu('%s', '%s', %s))", $name, $menuOpcode, $menuField, $valueExpr);
            $uses[] = 'Input';
            if ($enum !== null) {
                $uses[] = 'Field\\' . $enum;
            }

            continue;
        }

        $p = paramName($name);
        $type = match ($role) {
            'number' => 'int|float|Block|Input',
            'text' => 'string|Block|Input',
            'any' => 'int|float|string|Block|Input',
            'boolean' => 'Block',
            'color' => 'string|Input',
            default => throw new RuntimeException("Unknown input role '{$role}' on {$opcode}"),
        };
        $params[] = ['type' => $type, 'name' => $p];
        if ($role === 'color') {
            $calls[] = \sprintf("->input('%s', \\is_string(\$%s) ? Input::color(\$%s) : \$%s)", $name, $p, $p, $p);
            $uses[] = 'Input';
        } else {
            $calls[] = \sprintf("->input('%s', \$%s)", $name, $p);
        }
        if (str_contains($type, 'Input')) {
            $uses[] = 'Input';
        }
    }

    foreach ($fields as $name => $kind) {
        if (str_starts_with($kind, 'enum:')) {
            $enum = substr($kind, 5);
            $p = paramName($name);
            $params[] = ['type' => $enum, 'name' => $p];
            $calls[] = \sprintf("->field('%s', \$%s->value)", $name, $p);
            $uses[] = 'Field\\' . $enum;

            continue;
        }
        if (\in_array($kind, ['variable', 'list', 'broadcast'], true)) {
            $refType = ucfirst($kind) . 'Ref';
            $params[] = ['type' => $refType, 'name' => $kind];
            $calls[] = \sprintf("->field('%s', \$%s->name, \$%s->id)", $name, $kind, $kind);
            $uses[] = $refType;

            continue;
        }
        // plain string field
        $p = paramName($name);
        $params[] = ['type' => 'string', 'name' => $p];
        $calls[] = \sprintf("->field('%s', \$%s)", $name, $p);
    }

    // Substacks (1 or 2) always use list<Block> arrays so call sites are uniform:
    // Control::if($cond, [$s1, $s2]) and Control::ifElse($cond, [$s1], [$s2])
    // share the same shape, no need to remember which uses variadic.
    if ($substacks !== []) {
        $names = ['body', 'else'];
        foreach ($substacks as $i => $substack) {
            $pn = $names[$i] ?? 'branch' . $i;
            $params[] = ['type' => 'array', 'name' => $pn, 'doc' => 'list<Block>'];
            $calls[] = \sprintf("->input('%s', Block::stack(...\$%s))", $substack, $pn);
        }
    }

    return ['params' => $params, 'calls' => $calls, 'uses' => $uses];
}

/**
 * @param array{params: list<array{type: string, name: string, variadic?: bool, doc?: string}>, calls: list<string>} $parts
 */
function renderMethod(string $opcode, string $methodName, array $parts): string
{
    $sigParams = [];
    $docLines = [];
    foreach ($parts['params'] as $param) {
        $variadic = ($param['variadic'] ?? false) ? '...' : '';
        $sigParams[] = \sprintf('%s %s$%s', $param['type'], $variadic, $param['name']);
        if (isset($param['doc'])) {
            $docLines[] = \sprintf('     * @param %s $%s', $param['doc'], $param['name']);
        }
    }

    $body = "        return Block::make('{$opcode}')";
    if ($parts['calls'] === []) {
        $body .= ';';
    } else {
        $body .= "\n";
        $last = \count($parts['calls']) - 1;
        foreach ($parts['calls'] as $i => $call) {
            $body .= '            ' . $call . ($i === $last ? ';' : '') . "\n";
        }
        $body = rtrim($body, "\n");
    }

    $doc = $docLines === [] ? '' : "    /**\n" . implode("\n", $docLines) . "\n     */\n";
    $signature = \sprintf('    public static function %s(%s): Block', $methodName, implode(', ', $sigParams));

    return $doc . $signature . "\n    {\n" . $body . "\n    }";
}

function renderClass(string $class, array $opcodes): string
{
    $methods = [];
    $uses = [];
    foreach ($opcodes as $opcode => $spec) {
        $parts = buildMethodParts($opcode, $spec);
        foreach ($parts['uses'] as $u) {
            $uses[$u] = true;
        }
        $methods[] = renderMethod($opcode, $spec['name'], $parts);
    }

    $imports = ['CodeClubLuxembourg\\Sb3Toolkit\\Builder\\Block'];
    if (isset($uses['Input'])) {
        $imports[] = 'CodeClubLuxembourg\\Sb3Toolkit\\Builder\\Input';
    }
    foreach (['VariableRef', 'ListRef', 'BroadcastRef'] as $refType) {
        if (isset($uses[$refType])) {
            $imports[] = 'CodeClubLuxembourg\\Sb3Toolkit\\Builder\\' . $refType;
        }
    }
    foreach (array_keys($uses) as $u) {
        if (str_starts_with($u, 'Field\\')) {
            $imports[] = 'CodeClubLuxembourg\\Sb3Toolkit\\Builder\\' . $u;
        }
    }
    sort($imports);

    $useBlock = implode("\n", array_map(static fn (string $i): string => "use {$i};", $imports));

    $header = "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . BLOCKS_NS . ";\n\n"
        . $useBlock . "\n\n"
        . "/**\n * Typed factories for Scratch \"{$class}\" blocks.\n *\n"
        . " * Generated by bin/generate-blocks.php — do not edit by hand.\n */\n"
        . "final class {$class}\n{\n";

    return $header . implode("\n\n", $methods) . "\n}\n";
}

// --- main ---------------------------------------------------------------------

$byCategory = [];
foreach (OpcodeRegistry::specs() as $opcode => $spec) {
    if (!empty($spec['internal']) || !isset($spec['name'])) {
        continue;
    }
    $prefix = substr($opcode, 0, (int) strpos($opcode, '_'));
    if (!isset(CATEGORIES[$prefix])) {
        fwrite(STDERR, "No category mapping for opcode '{$opcode}'\n");
        exit(1);
    }
    $byCategory[CATEGORIES[$prefix]][$opcode] = $spec;
}

if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0o755, true);
}

$written = [];
foreach ($byCategory as $class => $opcodes) {
    $path = OUTPUT_DIR . "/{$class}.php";
    file_put_contents($path, renderClass($class, $opcodes));
    $written[] = $path;
    echo "Generated {$class} (" . \count($opcodes) . " blocks)\n";
}

// Canonicalize formatting so re-runs are byte-stable.
$fixer = __DIR__ . '/../vendor/bin/php-cs-fixer';
if (is_file($fixer)) {
    $process = new Process([$fixer, 'fix', OUTPUT_DIR, '--quiet'], __DIR__ . '/..');
    $process->run();
    echo $process->isSuccessful() ? "Formatted with php-cs-fixer.\n" : "php-cs-fixer skipped/failed.\n";
}

echo 'Done: ' . \count($written) . " classes.\n";
