<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

use RuntimeException;

/**
 * A typed helper for building Scratch custom blocks (procedures).
 *
 * A procedure spans three kinds of blocks on the sb3 wire:
 *
 * - `procedures_definition` — the hat block of the procedure body.
 * - `procedures_prototype` — a shadow block under the definition that carries
 *   the procedure signature (proccode, argument ids/names/defaults, warp).
 * - `argument_reporter_string_number` / `argument_reporter_boolean` — reporters
 *   used inside the body to read the procedure's arguments.
 *
 * `Procedure` hides those details: define once, then call `definition()` for
 * the hat, `arg($name)` for argument reporters to use in the body, and
 * `call(...$values)` to invoke the procedure from anywhere.
 */
final class Procedure
{
    /** Argument kind for string/number ("%s" in proccode). */
    public const string KIND_STRING_NUMBER = 'string_number';

    /** Argument kind for boolean ("%b" in proccode). */
    public const string KIND_BOOLEAN = 'boolean';

    private readonly string $proccode;

    /**
     * @param array<string, string> $argIds   arg name => generated id (used as
     *                                        the inputs slot key on prototype
     *                                        and call blocks)
     * @param array<string, string> $argKinds arg name => `string_number`|`boolean`
     */
    private function __construct(
        public readonly string $name,
        private readonly array $argIds,
        private readonly array $argKinds,
        private readonly bool $warp,
    ) {
        $proccode = $name;
        foreach ($argKinds as $kind) {
            $proccode .= $kind === self::KIND_BOOLEAN ? ' %b' : ' %s';
        }
        $this->proccode = $proccode;
    }

    /**
     * Define a new procedure. Each argument is a tuple `[name, kind]` where
     * `kind` is one of `Procedure::KIND_*`. Use {@see stringArg()} and
     * {@see booleanArg()} for readability.
     *
     * @param list<array{0: string, 1: string}> $args
     */
    public static function define(string $name, array $args = [], bool $warp = false): self
    {
        $argIds = [];
        $argKinds = [];
        foreach ($args as $arg) {
            [$argName, $kind] = $arg;
            if (!\in_array($kind, [self::KIND_STRING_NUMBER, self::KIND_BOOLEAN], true)) {
                throw new RuntimeException(\sprintf('Unknown procedure argument kind: %s', $kind));
            }
            if (isset($argIds[$argName])) {
                throw new RuntimeException(\sprintf('Duplicate procedure argument name: %s', $argName));
            }
            $argIds[$argName] = Id::generate();
            $argKinds[$argName] = $kind;
        }

        return new self($name, $argIds, $argKinds, $warp);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function stringArg(string $name): array
    {
        return [$name, self::KIND_STRING_NUMBER];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function booleanArg(string $name): array
    {
        return [$name, self::KIND_BOOLEAN];
    }

    /**
     * The procedure's on-wire signature, e.g. `doThing %s %b`.
     */
    public function proccode(): string
    {
        return $this->proccode;
    }

    /**
     * Build the `procedures_definition` hat. Returns the hat as a `Block` with
     * the prototype already attached as its `custom_block` input. Chain the
     * body onto it with `->then(...)` and call `addScript()` as usual.
     */
    public function definition(): Block
    {
        $argIdsJson = self::jsonEncode(array_values($this->argIds));
        $argNamesJson = self::jsonEncode(array_keys($this->argIds));
        $argDefaults = [];
        foreach ($this->argKinds as $kind) {
            $argDefaults[] = $kind === self::KIND_BOOLEAN ? 'false' : '';
        }
        $argDefaultsJson = self::jsonEncode($argDefaults);

        $prototype = Block::make('procedures_prototype')
            ->asShadow()
            ->mutation([
                'tagName' => 'mutation',
                'children' => [],
                'proccode' => $this->proccode,
                'argumentids' => $argIdsJson,
                'argumentnames' => $argNamesJson,
                'argumentdefaults' => $argDefaultsJson,
                'warp' => $this->warp ? 'true' : 'false',
            ]);

        // The prototype carries one shadow reporter per argument so the editor
        // can render the arg shape inside the procedure-block preview. The
        // shadow reporter ids don't matter; only the inputs slot keys (the arg
        // ids) do — they must match `argumentids` and the `call` inputs.
        foreach ($this->argIds as $argName => $argId) {
            $reporterOpcode = $this->argKinds[$argName] === self::KIND_BOOLEAN
                ? 'argument_reporter_boolean'
                : 'argument_reporter_string_number';
            $shadowReporter = Block::make($reporterOpcode)
                ->asShadow()
                ->field('VALUE', $argName);
            $prototype->input($argId, $shadowReporter);
        }

        return Block::make('procedures_definition')
            ->input('custom_block', $prototype);
    }

    /**
     * Build an argument reporter block to read this argument inside the
     * procedure body. Returns a new `Block` on each call so it can be embedded
     * in multiple places (each call places a distinct reporter in the map).
     */
    public function arg(string $name): Block
    {
        $kind = $this->argKinds[$name] ?? null;
        if ($kind === null) {
            throw new RuntimeException(\sprintf(
                'Procedure "%s" has no argument named "%s"',
                $this->name,
                $name,
            ));
        }
        $opcode = $kind === self::KIND_BOOLEAN
            ? 'argument_reporter_boolean'
            : 'argument_reporter_string_number';

        return Block::make($opcode)->field('VALUE', $name);
    }

    /**
     * Build a `procedures_call` block invoking this procedure. Values are
     * supplied in declaration order; `string_number` args accept the usual
     * `int|float|string|Block|Input` and `boolean` args require a `Block`.
     */
    public function call(int|float|string|bool|Block|Input ...$values): Block
    {
        $argNames = array_keys($this->argIds);
        if (\count($values) !== \count($argNames)) {
            throw new RuntimeException(\sprintf(
                'Procedure "%s" expects %d argument(s), got %d',
                $this->name,
                \count($argNames),
                \count($values),
            ));
        }

        $call = Block::make('procedures_call')
            ->mutation([
                'tagName' => 'mutation',
                'children' => [],
                'proccode' => $this->proccode,
                'argumentids' => self::jsonEncode(array_values($this->argIds)),
                'warp' => $this->warp ? 'true' : 'false',
            ]);

        $i = 0;
        foreach ($argNames as $argName) {
            $argId = $this->argIds[$argName];
            $kind = $this->argKinds[$argName];
            $value = $values[$i++];
            if ($kind === self::KIND_BOOLEAN) {
                if (!$value instanceof Block) {
                    throw new RuntimeException(\sprintf(
                        'Procedure "%s" boolean argument "%s" requires a Block, got %s',
                        $this->name,
                        $argName,
                        \get_debug_type($value),
                    ));
                }
                $call->input($argId, $value);
            } else {
                if (\is_bool($value)) {
                    throw new RuntimeException(\sprintf(
                        'Procedure "%s" string/number argument "%s" cannot be a bool',
                        $this->name,
                        $argName,
                    ));
                }
                $call->input($argId, $value);
            }
        }

        return $call;
    }

    /**
     * @param array<int, mixed> $value
     */
    private static function jsonEncode(array $value): string
    {
        return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }
}
