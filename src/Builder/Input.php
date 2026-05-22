<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

/**
 * A typed value for a block input slot.
 *
 * Wraps Scratch's internal tuple encoding so callers never build raw arrays.
 * Literals and reporters carry an inner tuple (e.g. `[4, 10]`, `[12, name, id]`);
 * nested {@see Block}s carry the child block itself; menu slots may carry both
 * a primary block and a fallback shadow menu.
 */
final class Input
{
    /** Input slot kind 1: a shadow value (literal, reporter, or shadow menu). */
    public const int SHADOW = 1;

    /** Input slot kind 2: a block reference with no shadow. */
    public const int NO_SHADOW = 2;

    /** Input slot kind 3: a block reference with a fallback shadow underneath. */
    public const int BLOCK_AND_SHADOW = 3;

    /**
     * @param array<int, mixed>|null $tuple inner tuple for a literal/reporter
     */
    private function __construct(
        private readonly ?array $tuple,
        private readonly ?Block $block,
        private readonly ?Block $shadow,
    ) {}

    /**
     * Number literal: encodes as `[4, value]`.
     */
    public static function number(int|float|string $value): self
    {
        return new self([4, $value], null, null);
    }

    /**
     * String literal: encodes as `[10, value]`.
     */
    public static function text(string $value): self
    {
        return new self([10, $value], null, null);
    }

    /**
     * Variable reporter: encodes as `[12, name, id]`.
     */
    public static function variable(VariableRef $ref): self
    {
        return new self([12, $ref->name, $ref->id], null, null);
    }

    /**
     * List reporter: encodes as `[13, name, id]`.
     */
    public static function list(ListRef $ref): self
    {
        return new self([13, $ref->name, $ref->id], null, null);
    }

    /**
     * Broadcast reference: encodes as `[11, name, id]`.
     */
    public static function broadcast(BroadcastRef $ref): self
    {
        return new self([11, $ref->name, $ref->id], null, null);
    }

    /**
     * Color value: encodes as `[9, value]`.
     */
    public static function color(string $value): self
    {
        return new self([9, $value], null, null);
    }

    /**
     * A nested block used as a reporter or substack.
     */
    public static function block(Block $block): self
    {
        return new self(null, $block, null);
    }

    /**
     * A dropdown menu input.
     *
     * A string `$value` builds the shadow menu block (with its selection field)
     * and references it directly. A {@see Block} `$value` references the block
     * as the primary value and keeps an empty shadow menu underneath so the
     * slot round-trips correctly when the reporter is detached in the editor.
     * `$shadowDefault` overrides the placeholder field value for that fallback.
     */
    public static function menu(
        string $menuOpcode,
        string $fieldName,
        string|Block $value,
        string $shadowDefault = '',
    ): self {
        if ($value instanceof Block) {
            $shadow = Block::make($menuOpcode)->field($fieldName, $shadowDefault)->asShadow();

            return new self(null, $value, $shadow);
        }

        $shadow = Block::make($menuOpcode)->field($fieldName, $value)->asShadow();

        return new self(null, null, $shadow);
    }

    /**
     * Normalize a loose value into an {@see Input}.
     *
     * An {@see Input} passes through unchanged; a {@see Block} becomes a block
     * input; an int/float becomes a number literal; a string becomes a text
     * literal.
     */
    public static function wrap(self|Block|int|float|string $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if ($value instanceof Block) {
            return self::block($value);
        }
        if (\is_string($value)) {
            return self::text($value);
        }

        return self::number($value);
    }

    /**
     * The nested blocks this input references (primary value, optional shadow),
     * in declaration order, so the flattener can collect them.
     *
     * @return list<Block>
     */
    public function childBlocks(): array
    {
        $children = [];
        if ($this->block instanceof Block) {
            $children[] = $this->block;
        }
        if ($this->shadow instanceof Block) {
            $children[] = $this->shadow;
        }

        return $children;
    }

    /**
     * Encode this input as a block-input slot:
     * - `[1, tuple]` for a literal/reporter,
     * - `[1, shadowId]` for a shadow menu,
     * - `[2, blockId]` for a nested block alone,
     * - `[3, blockId, shadowId]` for a nested block over a shadow menu.
     *
     * @return array<int, mixed>
     */
    public function toSlot(): array
    {
        if ($this->block instanceof Block && $this->shadow instanceof Block) {
            return [self::BLOCK_AND_SHADOW, $this->block->id, $this->shadow->id];
        }
        if ($this->block instanceof Block) {
            return [self::NO_SHADOW, $this->block->id];
        }
        if ($this->shadow instanceof Block) {
            return [self::SHADOW, $this->shadow->id];
        }

        return [self::SHADOW, $this->tuple];
    }
}
