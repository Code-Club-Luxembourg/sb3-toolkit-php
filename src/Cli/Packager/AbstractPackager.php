<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Packager;

use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\Paths;

/**
 * Shared packaging logic: reads the project, writes the optional checksum file,
 * and assembles the injected custom JS (bridge + countdown). Concrete packagers
 * implement {@see emit()} to produce the actual HTML output.
 */
abstract class AbstractPackager implements PackagerInterface
{
    public function pack(PackRequest $request): void
    {
        $projectData = $request->projectBytes;
        if ($projectData === null) {
            $projectData = file_get_contents($request->projectPath);
            if ($projectData === false) {
                throw new RuntimeException(\sprintf('Failed to read project: %s', $request->projectPath));
            }
        }

        $this->emit($request, $projectData, $this->buildCustomJs($request, $projectData));
    }

    /**
     * Produce the packaged HTML file for the given project and assembled custom JS.
     */
    abstract protected function emit(PackRequest $request, string $projectData, ?string $customJs): void;

    /**
     * Assemble the injected custom JS, writing the checksum sidecar when a
     * countdown is requested. Returns null when nothing should be injected.
     */
    protected function buildCustomJs(PackRequest $request, string $projectData): ?string
    {
        $jsParts = [];
        if ($request->customJs !== null && $request->customJs !== '') {
            $jsParts[] = $request->customJs;
        }

        if ($request->countdownSeconds !== null && $request->countdownSeconds > 0) {
            $checksum = hash('sha256', $projectData);
            $checksumFile = Paths::toAbsolute($request->checksumFile ?? $request->outputPath . '.sha256');
            $checksumUrl = $request->checksumUrl ?? basename($checksumFile);

            $jsParts[] = CountdownScript::build($request->countdownSeconds, $checksumUrl, $checksum);

            file_put_contents($checksumFile, $checksum . "\n");
        }

        return $jsParts === [] ? null : implode("\n\n", $jsParts);
    }
}
