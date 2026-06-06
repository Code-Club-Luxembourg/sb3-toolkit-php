<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Scratch;

/**
 * In-memory index of Scratch asset binaries keyed by `md5ext`.
 */
final class ScratchAssetIndex
{
    /**
     * @var array<string, string>
     */
    private array $byMd5ext = [];

    /**
     * @param array<string, string> $byMd5ext initial `md5ext => binary` entries
     */
    public function __construct(array $byMd5ext = [])
    {
        $this->byMd5ext = $byMd5ext;
    }

    /**
     * Add an asset binary if the `md5ext` key is not already present.
     */
    public function add(string $md5ext, string $data): void
    {
        if (!isset($this->byMd5ext[$md5ext])) {
            $this->byMd5ext[$md5ext] = $data;
        }
    }

    /**
     * Check whether an asset exists for the given `md5ext`.
     */
    public function has(string $md5ext): bool
    {
        return isset($this->byMd5ext[$md5ext]);
    }

    /**
     * Retrieve the asset binary for the given `md5ext`, or null when absent.
     */
    public function get(string $md5ext): ?string
    {
        return $this->byMd5ext[$md5ext] ?? null;
    }

    /**
     * Number of assets tracked by this index.
     */
    public function size(): int
    {
        return \count($this->byMd5ext);
    }

    /**
     * Remove the asset at `$md5ext`, returning true when an entry was removed.
     */
    public function remove(string $md5ext): bool
    {
        if (!isset($this->byMd5ext[$md5ext])) {
            return false;
        }
        unset($this->byMd5ext[$md5ext]);

        return true;
    }

    /**
     * Return all `[md5ext => binary]` entries in insertion order.
     *
     * @return array<string, string>
     */
    public function entries(): array
    {
        return $this->byMd5ext;
    }
}
