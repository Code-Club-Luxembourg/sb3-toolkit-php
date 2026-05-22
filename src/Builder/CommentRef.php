<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder;

use RuntimeException;

/**
 * A reference to a single comment within a live comments map, for in-place
 * reads and edits. Comments are either free-floating (`blockId === null`) or
 * anchored to a specific block.
 */
final class CommentRef
{
    /**
     * @var array<string, mixed>
     */
    private array $comments;

    /**
     * @param array<string, mixed> $comments the live comments map (by reference)
     */
    public function __construct(array &$comments, public readonly string $id)
    {
        $this->comments = &$comments;
    }

    /**
     * Return the referenced comment, throwing if it is missing.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $comment = $this->comments[$this->id] ?? null;
        if (!\is_array($comment)) {
            throw new RuntimeException(\sprintf('Comment "%s" not found', $this->id));
        }

        return $comment;
    }

    public function text(): string
    {
        $text = $this->data()['text'] ?? '';

        return \is_string($text) ? $text : '';
    }

    public function setText(string $text): self
    {
        $this->comments[$this->id]['text'] = $text;

        return $this;
    }

    /**
     * The block this comment is anchored to, or null for free-floating comments.
     */
    public function blockId(): ?string
    {
        $blockId = $this->data()['blockId'] ?? null;

        return \is_string($blockId) ? $blockId : null;
    }

    public function setBlockId(?string $blockId): self
    {
        $this->comments[$this->id]['blockId'] = $blockId;

        return $this;
    }

    public function setPosition(int|float $x, int|float $y): self
    {
        $this->comments[$this->id]['x'] = $x;
        $this->comments[$this->id]['y'] = $y;

        return $this;
    }

    public function setSize(int|float $width, int|float $height): self
    {
        $this->comments[$this->id]['width'] = $width;
        $this->comments[$this->id]['height'] = $height;

        return $this;
    }

    public function setMinimized(bool $minimized): self
    {
        $this->comments[$this->id]['minimized'] = $minimized;

        return $this;
    }

    /**
     * Remove this comment from the comments map.
     */
    public function delete(): void
    {
        unset($this->comments[$this->id]);
    }
}
