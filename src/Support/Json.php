<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Support;

use stdClass;

/**
 * JSON helpers that preserve Scratch `project.json` fidelity.
 *
 * Scratch stores `variables`, `lists`, `broadcasts`, `blocks`, `comments`,
 * block `inputs`/`fields` and monitor `params` as JSON objects. When decoded
 * into PHP associative arrays an empty map is indistinguishable from an empty
 * list, so this helper re-casts the known map fields to objects before
 * encoding to keep `{}` from collapsing into `[]`.
 */
final class Json
{
    private const ENCODE_FLAGS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;

    /**
     * Decode `project.json` text into an associative array.
     *
     * @return array<string, mixed>
     */
    public static function decodeProjectData(string $text): array
    {
        /** @var array<string, mixed> $parsed */
        $parsed = json_decode($text, true, 512, \JSON_THROW_ON_ERROR);

        return $parsed;
    }

    /**
     * Encode project data, keeping empty Scratch maps as JSON objects.
     *
     * @param array<string, mixed> $data
     */
    public static function encodeProjectData(array $data): string
    {
        return json_encode(self::normalizeProjectData($data), self::ENCODE_FLAGS);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function normalizeProjectData(array $data): array
    {
        if (isset($data['targets']) && \is_array($data['targets'])) {
            foreach ($data['targets'] as $index => $target) {
                if (\is_array($target)) {
                    $data['targets'][$index] = self::normalizeTarget($target);
                }
            }
        }

        if (isset($data['monitors']) && \is_array($data['monitors'])) {
            foreach ($data['monitors'] as $index => $monitor) {
                if (\is_array($monitor) && \array_key_exists('params', $monitor)) {
                    $data['monitors'][$index]['params'] = self::emptyMap($monitor['params']);
                }
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $target
     *
     * @return array<string, mixed>
     */
    private static function normalizeTarget(array $target): array
    {
        foreach (['variables', 'lists', 'broadcasts', 'comments'] as $field) {
            if (\array_key_exists($field, $target)) {
                $target[$field] = self::emptyMap($target[$field]);
            }
        }

        if (\array_key_exists('blocks', $target)) {
            $target['blocks'] = self::normalizeBlocks($target['blocks']);
        }

        return $target;
    }

    private static function normalizeBlocks(mixed $blocks): mixed
    {
        if (!\is_array($blocks) || $blocks === []) {
            return self::emptyMap($blocks);
        }

        foreach ($blocks as $id => $block) {
            if (!\is_array($block)) {
                continue;
            }
            foreach (['inputs', 'fields'] as $field) {
                if (\array_key_exists($field, $block)) {
                    $block[$field] = self::emptyMap($block[$field]);
                }
            }
            $blocks[$id] = $block;
        }

        return $blocks;
    }

    /**
     * Replace an empty array with an object so it serializes as `{}` not `[]`.
     */
    private static function emptyMap(mixed $value): mixed
    {
        return ($value === []) ? new stdClass() : $value;
    }
}
