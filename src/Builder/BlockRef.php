<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

use Generator;
use RuntimeException;

/**
 * A reference to a single block within a live blocks map, for in-place edits.
 *
 * Works equally on freshly built projects and on projects loaded from `.sb3`,
 * because both expose the same flat `blocks` map; this class just walks and
 * mutates that map by reference.
 */
final class BlockRef
{
    /**
     * @var array<string, mixed>
     */
    private array $blocks;

    /**
     * @param array<string, mixed> $blocks the live blocks map (by reference)
     */
    public function __construct(array &$blocks, public readonly string $id)
    {
        $this->blocks = &$blocks;
    }

    /**
     * Return the referenced block, throwing if it is missing or null.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $block = $this->blocks[$this->id] ?? null;
        if (!\is_array($block)) {
            throw new RuntimeException(\sprintf('Block "%s" not found or is null', $this->id));
        }

        return $block;
    }

    /**
     * The block's opcode.
     */
    public function opcode(): string
    {
        $opcode = $this->data()['opcode'] ?? null;
        if (!\is_string($opcode)) {
            throw new RuntimeException(\sprintf('Block "%s" has no opcode', $this->id));
        }

        return $opcode;
    }

    /**
     * The raw input slot tuple at `$name` (e.g. `[1, [4, 10]]`, `[2, "blockId"]`,
     * `[3, "blockId", "shadowId"]`), or null when the input is absent.
     *
     * @return list<mixed>|null
     */
    public function getInputSlot(string $name): ?array
    {
        $slot = $this->data()['inputs'][$name] ?? null;
        if (!\is_array($slot)) {
            return null;
        }

        /** @var list<mixed> $slot */
        return array_values($slot);
    }

    /**
     * The literal value at input `$name` (e.g. `10` for `[1, [4, 10]]`,
     * `"Hi!"` for `[1, [10, "Hi!"]]`). Returns null when the input is absent or
     * carries a block reference instead of a literal — use {@see getInputSlot()}
     * for the raw encoding.
     */
    public function getInputLiteralValue(string $name): mixed
    {
        $slot = $this->getInputSlot($name);
        if ($slot === null) {
            return null;
        }
        // Literal slot: [1, [kind, value, …]]. Block-ref slots have a string at slot[1].
        $payload = $slot[1] ?? null;
        if (!\is_array($payload)) {
            return null;
        }

        return $payload[1] ?? null;
    }

    /**
     * The raw `[value, id]` field tuple at `$name`, or null when absent.
     *
     * @return array{0: mixed, 1: mixed}|null
     */
    public function getField(string $name): ?array
    {
        $field = $this->data()['fields'][$name] ?? null;
        if (!\is_array($field) || !\array_key_exists(0, $field)) {
            return null;
        }

        return [$field[0], $field[1] ?? null];
    }

    /**
     * Just the string value of a field (the dropdown selection); null when the
     * field is absent or its value is not a string.
     */
    public function getFieldValue(string $name): ?string
    {
        $field = $this->getField($name);
        if ($field === null || !\is_string($field[0])) {
            return null;
        }

        return $field[0];
    }

    /**
     * Set an input. Plain int/float/string values and {@see Block} reporters are
     * wrapped automatically; pass an {@see Input} for a specific encoding.
     *
     * If the slot previously held nested blocks (a reporter or shadow menu),
     * those blocks and their descendants are removed from the map.
     *
     * Passing `null` is a no-op (use this to defer optional inputs at build time).
     */
    public function input(string $name, Input|Block|int|float|string|null $value): self
    {
        if ($value === null) {
            return $this;
        }

        $existing = $this->blocks[$this->id]['inputs'][$name] ?? null;
        if (\is_array($existing)) {
            foreach ($this->referencedBlockIds($existing) as $oldChildId) {
                $this->removeSubtree($oldChildId);
            }
        }

        $input = Input::wrap($value);

        foreach ($input->childBlocks() as $child) {
            $subBlocks = $child->toBlocks(0, 0);
            $subBlocks[$child->id]['topLevel'] = false;
            $subBlocks[$child->id]['parent'] = $this->id;
            foreach ($subBlocks as $subId => $subBlock) {
                $this->blocks[$subId] = $subBlock;
            }
        }

        $this->blocks[$this->id]['inputs'][$name] = $input->toSlot();

        return $this;
    }

    /**
     * Set a field. The optional id is used by fields that reference an entity.
     */
    public function field(string $name, string $value, ?string $id = null): self
    {
        $this->blocks[$this->id]['fields'][$name] = [$value, $id];

        return $this;
    }

    /**
     * The block this one chains to via `next`, or null when this is the tail.
     */
    public function next(): ?self
    {
        $nextId = $this->data()['next'] ?? null;
        if (!\is_string($nextId) || !isset($this->blocks[$nextId])) {
            return null;
        }

        return new self($this->blocks, $nextId);
    }

    /**
     * The block this one is attached to. For chain blocks this is the previous
     * statement; for blocks that live inside an input slot (reporters, substack
     * heads) it is the containing block. Use {@see previous()} when you only
     * want chain predecessors.
     */
    public function parent(): ?self
    {
        $parentId = $this->data()['parent'] ?? null;
        if (!\is_string($parentId) || !isset($this->blocks[$parentId])) {
            return null;
        }

        return new self($this->blocks, $parentId);
    }

    /**
     * The predecessor in the chain — the block whose `next` points to this one.
     * Returns null for hats and for blocks that live inside an input slot
     * (their `parent` is the containing block, not a chain predecessor).
     */
    public function previous(): ?self
    {
        $parent = $this->parent();
        if (!$parent instanceof \CodeClubLuxembourg\Sb3Toolkit\Builder\BlockRef) {
            return null;
        }
        if (($parent->data()['next'] ?? null) !== $this->id) {
            return null;
        }

        return $parent;
    }

    /**
     * The block referenced by input `$name`, or null when the slot is absent
     * or carries only a literal value. Works for both nested reporters and
     * substack roots (kinds 2 + 3, plus shadow-menu refs in kind 1).
     */
    public function getInputBlock(string $name): ?self
    {
        $slot = $this->data()['inputs'][$name] ?? null;
        if (!\is_array($slot)) {
            return null;
        }
        $id = $slot[1] ?? null;
        if (!\is_string($id) || !isset($this->blocks[$id])) {
            return null;
        }

        return new self($this->blocks, $id);
    }

    /**
     * Walk this block and every successor via `next`, yielding each as a
     * `BlockRef`. Safe to break out of mid-walk; the iterator is lazy.
     *
     * @return Generator<BlockRef>
     */
    public function chain(): Generator
    {
        $cur = $this;
        while ($cur instanceof \CodeClubLuxembourg\Sb3Toolkit\Builder\BlockRef) {
            yield $cur;
            $cur = $cur->next();
        }
    }

    /**
     * Walk the chain of blocks rooted at the substack input `$name`. Yields
     * nothing when the input is empty or absent (e.g. an empty `if` body).
     *
     * @return Generator<BlockRef>
     */
    public function substack(string $name = 'SUBSTACK'): Generator
    {
        $head = $this->getInputBlock($name);
        if (!$head instanceof \CodeClubLuxembourg\Sb3Toolkit\Builder\BlockRef) {
            return;
        }
        yield from $head->chain();
    }

    /**
     * Depth-first walk of every block reachable from this one: this block,
     * then each input-referenced block's subtree (reporters and substack
     * heads), then the next block's subtree. Calling this on a hat yields the
     * entire script.
     *
     * @return Generator<BlockRef>
     */
    public function descendants(): Generator
    {
        yield $this;
        foreach (($this->data()['inputs'] ?? []) as $slot) {
            if (!\is_array($slot)) {
                continue;
            }
            foreach ($this->referencedBlockIds($slot) as $childId) {
                if (isset($this->blocks[$childId])) {
                    yield from (new self($this->blocks, $childId))->descendants();
                }
            }
        }
        $next = $this->next();
        if ($next instanceof \CodeClubLuxembourg\Sb3Toolkit\Builder\BlockRef) {
            yield from $next->descendants();
        }
    }

    /**
     * Insert a block immediately after this one in the next-chain:
     * `this → newBlock(chain) → old-next`.
     */
    public function insertAfter(Block $newBlock): void
    {
        $oldNextId = $this->blocks[$this->id]['next'] ?? null;

        $newBlocks = $newBlock->toBlocks(0, 0);
        $newBlocks[$newBlock->id]['topLevel'] = false;
        $newBlocks[$newBlock->id]['parent'] = $this->id;

        $tailId = $newBlock->id;
        while (($newBlocks[$tailId]['next'] ?? null) !== null) {
            $tailId = $newBlocks[$tailId]['next'];
        }

        foreach ($newBlocks as $id => $block) {
            $this->blocks[$id] = $block;
        }

        $this->blocks[$tailId]['next'] = $oldNextId;
        if ($oldNextId !== null && isset($this->blocks[$oldNextId])) {
            $this->blocks[$oldNextId]['parent'] = $tailId;
        }

        $this->blocks[$this->id]['next'] = $newBlock->id;
    }

    /**
     * Remove this block from the next-chain and the blocks map.
     *
     * Blocks reachable through this block's inputs (nested reporters and
     * substacks) are removed too; the `next` chain is re-parented onto this
     * block's parent (or promoted to top-level if this block was a hat).
     */
    public function delete(): void
    {
        $block = $this->data();
        $parentId = $block['parent'] ?? null;
        $nextId = $block['next'] ?? null;

        if (!empty($block['topLevel'])) {
            if ($nextId !== null && isset($this->blocks[$nextId])) {
                $this->blocks[$nextId]['topLevel'] = true;
                $this->blocks[$nextId]['parent'] = null;
                if (\array_key_exists('x', $block)) {
                    $this->blocks[$nextId]['x'] = $block['x'];
                }
                if (\array_key_exists('y', $block)) {
                    $this->blocks[$nextId]['y'] = $block['y'];
                }
            }
        } else {
            if (
                $parentId !== null
                && isset($this->blocks[$parentId])
                && ($this->blocks[$parentId]['next'] ?? null) === $this->id
            ) {
                $this->blocks[$parentId]['next'] = $nextId;
            }
            if ($nextId !== null && isset($this->blocks[$nextId])) {
                $this->blocks[$nextId]['parent'] = $parentId;
            }
        }

        foreach (($block['inputs'] ?? []) as $slot) {
            if (!\is_array($slot)) {
                continue;
            }
            foreach ($this->referencedBlockIds($slot) as $childId) {
                $this->removeSubtree($childId);
            }
        }

        unset($this->blocks[$this->id]);
    }

    /**
     * Insert a chain in *before* this block: `[...newBlock chain] → this`.
     *
     * If this block is top-level, the inserted chain's head becomes the new
     * top-level hat at the same `x`/`y` and this block is demoted to mid-chain.
     * Otherwise the inserted head takes this block's previous slot (chain
     * predecessor's `next`, or the input slot of a parent reporter/substack).
     */
    public function insertBefore(Block $newBlock): void
    {
        $thisBlock = $this->data();
        $parentId = $thisBlock['parent'] ?? null;
        $isTopLevel = !empty($thisBlock['topLevel']);
        $x = $thisBlock['x'] ?? null;
        $y = $thisBlock['y'] ?? null;

        $newBlocks = $newBlock->toBlocks(0, 0);
        $tailId = $newBlock->id;
        while (($newBlocks[$tailId]['next'] ?? null) !== null) {
            $tailId = $newBlocks[$tailId]['next'];
        }

        // Tail of the inserted chain links to this block.
        $newBlocks[$tailId]['next'] = $this->id;

        if ($isTopLevel) {
            // Inserted head becomes the new hat at the original coords.
            $newBlocks[$newBlock->id]['topLevel'] = true;
            $newBlocks[$newBlock->id]['parent'] = null;
            if ($x !== null) {
                $newBlocks[$newBlock->id]['x'] = $x;
            }
            if ($y !== null) {
                $newBlocks[$newBlock->id]['y'] = $y;
            }
            // This block drops to mid-chain.
            unset($this->blocks[$this->id]['x'], $this->blocks[$this->id]['y']);
            $this->blocks[$this->id]['topLevel'] = false;
        } else {
            $newBlocks[$newBlock->id]['topLevel'] = false;
            $newBlocks[$newBlock->id]['parent'] = $parentId;
            if ($parentId !== null && isset($this->blocks[$parentId])) {
                if (($this->blocks[$parentId]['next'] ?? null) === $this->id) {
                    $this->blocks[$parentId]['next'] = $newBlock->id;
                }
                foreach (($this->blocks[$parentId]['inputs'] ?? []) as $name => $slot) {
                    if (!\is_array($slot)) {
                        continue;
                    }
                    $modified = false;
                    if (($slot[1] ?? null) === $this->id) {
                        $slot[1] = $newBlock->id;
                        $modified = true;
                    }
                    if (($slot[2] ?? null) === $this->id) {
                        $slot[2] = $newBlock->id;
                        $modified = true;
                    }
                    if ($modified) {
                        $this->blocks[$parentId]['inputs'][$name] = $slot;
                    }
                }
            }
        }

        // This block's predecessor is now the inserted chain's tail.
        $this->blocks[$this->id]['parent'] = $tailId;

        foreach ($newBlocks as $id => $b) {
            $this->blocks[$id] = $b;
        }
    }

    /**
     * Swap this block out for a new one (chain). Preserves `parent`, `next`,
     * top-level status, and coordinates. Blocks reachable through this block's
     * inputs are removed; the `next` tail is preserved and re-parented onto
     * the inserted chain's tail.
     */
    public function replace(Block $newBlock): void
    {
        $thisBlock = $this->data();
        $oldNextId = $thisBlock['next'] ?? null;
        $oldParentId = $thisBlock['parent'] ?? null;
        $isTopLevel = !empty($thisBlock['topLevel']);
        $x = $thisBlock['x'] ?? null;
        $y = $thisBlock['y'] ?? null;

        $newBlocks = $newBlock->toBlocks(0, 0);
        $newRootId = $newBlock->id;
        $tailId = $newRootId;
        while (($newBlocks[$tailId]['next'] ?? null) !== null) {
            $tailId = $newBlocks[$tailId]['next'];
        }

        if ($isTopLevel) {
            $newBlocks[$newRootId]['topLevel'] = true;
            $newBlocks[$newRootId]['parent'] = null;
            if ($x !== null) {
                $newBlocks[$newRootId]['x'] = $x;
            }
            if ($y !== null) {
                $newBlocks[$newRootId]['y'] = $y;
            }
        } else {
            $newBlocks[$newRootId]['topLevel'] = false;
            $newBlocks[$newRootId]['parent'] = $oldParentId;
        }
        $newBlocks[$tailId]['next'] = $oldNextId;

        if (!$isTopLevel && $oldParentId !== null && isset($this->blocks[$oldParentId])) {
            if (($this->blocks[$oldParentId]['next'] ?? null) === $this->id) {
                $this->blocks[$oldParentId]['next'] = $newRootId;
            }
            foreach (($this->blocks[$oldParentId]['inputs'] ?? []) as $name => $slot) {
                if (!\is_array($slot)) {
                    continue;
                }
                $modified = false;
                if (($slot[1] ?? null) === $this->id) {
                    $slot[1] = $newRootId;
                    $modified = true;
                }
                if (($slot[2] ?? null) === $this->id) {
                    $slot[2] = $newRootId;
                    $modified = true;
                }
                if ($modified) {
                    $this->blocks[$oldParentId]['inputs'][$name] = $slot;
                }
            }
        }

        if ($oldNextId !== null && isset($this->blocks[$oldNextId])) {
            $this->blocks[$oldNextId]['parent'] = $tailId;
        }

        foreach (($thisBlock['inputs'] ?? []) as $slot) {
            if (!\is_array($slot)) {
                continue;
            }
            foreach ($this->referencedBlockIds($slot) as $childId) {
                $this->removeSubtree($childId);
            }
        }
        unset($this->blocks[$this->id]);

        foreach ($newBlocks as $id => $b) {
            $this->blocks[$id] = $b;
        }
    }

    /**
     * Remove this block and the *entire* chain reachable from it: every `next`
     * block onwards, and every nested input/substack subtree. Useful when
     * called on a hat to delete a whole script; on a mid-chain block, the
     * chain up to (but not including) this one is preserved.
     */
    public function removeScript(): void
    {
        $block = $this->data();
        $parentId = $block['parent'] ?? null;

        if (empty($block['topLevel']) && $parentId !== null && isset($this->blocks[$parentId])) {
            if (($this->blocks[$parentId]['next'] ?? null) === $this->id) {
                $this->blocks[$parentId]['next'] = null;
            }
            foreach (($this->blocks[$parentId]['inputs'] ?? []) as $name => $slot) {
                if (!\is_array($slot)) {
                    continue;
                }
                if (($slot[1] ?? null) === $this->id) {
                    unset($this->blocks[$parentId]['inputs'][$name]);
                }
            }
        }

        $this->removeSubtree($this->id);
    }

    /**
     * Block IDs referenced by an input slot, in `[kind, primary, shadow?]` shape.
     *
     * @param array<int, mixed> $slot
     *
     * @return list<string>
     */
    private function referencedBlockIds(array $slot): array
    {
        $ids = [];
        if (isset($slot[1]) && \is_string($slot[1])) {
            $ids[] = $slot[1];
        }
        if (isset($slot[2]) && \is_string($slot[2])) {
            $ids[] = $slot[2];
        }

        return $ids;
    }

    /**
     * Recursively remove a block, its nested inputs and any chained `next` blocks.
     */
    private function removeSubtree(string $id): void
    {
        $block = $this->blocks[$id] ?? null;
        unset($this->blocks[$id]);
        if (!\is_array($block)) {
            return;
        }

        foreach (($block['inputs'] ?? []) as $slot) {
            if (!\is_array($slot)) {
                continue;
            }
            foreach ($this->referencedBlockIds($slot) as $childId) {
                $this->removeSubtree($childId);
            }
        }

        if (isset($block['next']) && \is_string($block['next'])) {
            $this->removeSubtree($block['next']);
        }
    }
}
