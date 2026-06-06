# sb3-toolkit-php

PHP toolkit for working with Scratch `.sb3` projects. It is a **library first** —
the programmatic API is the product, and a CLI ships as an optional convenience
on top.

- **Builder API** — create projects programmatically, build block scripts, and query/modify existing blocks
- **Validator** — check a project for structural and semantic errors, with precise error messages
- **CLI tools** *(optional)* — merge sprites, arrange projects in a grid, package to HTML, download from Scratch

The core library (`Builder`, `Validator`, `ScratchProject`) depends only on PHP
and its `zip`/`json`/`hash` extensions — **no third-party packages**. The CLI and
the Scratch download features pull in [Symfony Components](https://symfony.com/components)
only when you opt into them (see [Optional features](#optional-features)).

Tested with PHPUnit and kept clean with Rector and PHP-CS-Fixer. All source uses
`declare(strict_types=1)`. Node.js is **not required at runtime** — see
[Maintaining the packager runtime](#maintaining-the-packager-runtime) for the
single exception, which only matters to maintainers.

## Requirements
- PHP 8.3+ with the `zip`, `json` and `hash` extensions (the core library)
- [Composer](https://getcomposer.org/)

## Install

### As a dependency (private VCS repository)

This package is not published on Packagist. Add it as a Composer
[VCS repository](https://getcomposer.org/doc/05-repositories.md#vcs) in the
consuming project's `composer.json`, pointing at wherever you host this repo:

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:Code-Club-Luxembourg/sb3-toolkit-php.git" }
    ],
    "require": {
        "code-club-luxembourg/sb3-toolkit": "^1.0"
    }
}
```

Then:

```bash
composer require code-club-luxembourg/sb3-toolkit:^1.0
```

Composer resolves `^1.0` against the package's git tags, so cut a tag
(`git tag v1.0.0 && git push --tags`) before requiring it. To track the branch
instead, require `dev-main` (aliased to `1.0.x-dev`).

The core install is dependency-free beyond the library itself. To use the
optional CLI or download features, also require the relevant Symfony components —
see [Optional features](#optional-features).

### For local development

```bash
git clone <this-repo> && cd sb3-toolkit-php
composer install
```

## Optional features

The CLI and Scratch-download layers are **opt-in**. `composer install` in this
repo enables them via `require-dev`; consumers add only what they use:

| Feature | Add to your project |
|---------|---------------------|
| `bin/sb3` CLI (merge, grid, pack, validate, …) | `composer require symfony/console:^7.0` |
| Downloading projects/studios from Scratch | `composer require symfony/http-client:^7.0` |
| PSR-3 logging during studio downloads | `composer require psr/log:^3.0` |

Running `bin/sb3` without `symfony/console` prints an actionable message
rather than failing with a class-not-found error.

## Quickstart

### Build a project programmatically

```php
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

$project = ScratchProject::create();
$cat = $project->addSprite('Cat');

$cat->addScript(
    Events::whenFlagClicked()
        ->then(Motion::moveSteps(10))
        ->then(Looks::say('Hello!')),
);

$project->saveToSb3File('output.sb3');
```

Typed factories (`Motion`, `Looks`, `Control`, …) cover every Scratch block;
`Block::make()` remains for custom/extension blocks. See
[docs/builder.md](docs/builder.md) for the full API, enums, and manipulation examples.

### Validate an existing `.sb3`

```php
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;

$project = ScratchProject::fromSb3File('project.sb3');
$result = ScratchValidator::validate($project, assets: $project->assets);

foreach ($result->errors as $error) {
    printf("[%s] %s at %s: %s\n", $error->severity->value, $error->code->value, $error->path, $error->message);
}
```

See [docs/validator.md](docs/validator.md) for the full API, all error codes,
the structured `ValidationPath`, and how to write a custom rule.

### Work with project parts in memory

When you already hold a project's parts — a decoded `project.json` array and its
assets keyed by `md5ext` — build a `ScratchProject` directly, with no `.sb3`
file on disk. Serialize back to bytes with `toSb3Bytes()`, and package straight
from those bytes via `PackRequest::$projectBytes`:

```php
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\HtmlPackager;
use CodeClubLuxembourg\Sb3Toolkit\Cli\Packager\PackRequest;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

// $projectJson: array (decoded project.json); $assetsByMd5ext: [md5ext => bytes]
$project = ScratchProject::fromParts($projectJson, $assetsByMd5ext);

$bytes = $project->toSb3Bytes();
(new HtmlPackager())->pack(new PackRequest(
    projectPath: '',          // ignored when projectBytes is given
    outputPath: 'player.html',
    stageWidth: 480,
    stageHeight: 360,
    projectBytes: $bytes,
));
```

This avoids round-tripping through temporary `.sb3` files when merging or
packaging projects loaded from a database or other store. `mergeSprites()` and
`gridProjects()` already operate purely in memory, so a whole merge-and-package
pipeline can run without touching disk for the inputs.

### Manipulate or download projects from the CLI

The CLI is optional — install `symfony/console` (and `symfony/http-client` for
the download commands) as shown in [Optional features](#optional-features). Then
run via the `bin/sb3` console:

| Command | Description |
|---------|-------------|
| `bin/sb3 merge <base> <sources...> -o <out>` | Merge sprites from source projects into a base project |
| `bin/sb3 grid <sources...> --columns <n> -o <out>` | Arrange projects side by side in a grid |
| `bin/sb3 pack <project> -o <out> --stage-width <px> --stage-height <px>` | Package a project into a standalone HTML file |
| `bin/sb3 project-download <project_id>` | Download a single project by ID |
| `bin/sb3 studio-download <studio_id>` | Download all projects from a Scratch studio |

Run any command with `--help` for its full option list.

## Quality tooling

```bash
composer test        # phpunit
composer lint        # php-cs-fixer (dry-run)
composer lint:fix    # php-cs-fixer (apply)
composer rector      # rector (dry-run)
composer rector:fix  # rector (apply)
```

## Project structure
- `src/Scratch/` — `ScratchProject`, `ScratchAssetIndex`, `Paths`
- `src/Validator/` — `ScratchValidator`, opcode registry, `ErrorCode` enum, `ValidationPath`, and validation rules
- `src/Builder/` — `Block`, `Input`, `Sprite`, `BlockRef`, `Id`, `VariableRef`/`ListRef`/`BroadcastRef`, generated typed factories in `Blocks/`, and dropdown enums in `Field/` (project creation lives on `ScratchProject`)
- `src/Studio/` — `ScratchApi`, `ScratchApiException`, `StudioDownloader`, `DownloadResult` (sealed: `RawDownload` / `JsonDownload`)
- `src/Cli/` — Symfony Console commands and the pure-PHP `HtmlPackager`
- `src/Support/` — `Json` (preserves Scratch's empty-map fidelity)
- `resources/scaffolding/` — vendored TurboWarp runtime inlined by the packager
- `tests/` — PHPUnit test suite

## The `pack` command

The `pack` command bundles a `.sb3` project into a standalone HTML player in
pure PHP:

```bash
bin/sb3 pack project.sb3 -o project.html --stage-width 480 --stage-height 360 --autoplay
```

A Scratch project runs as JavaScript in the browser, so the output necessarily
embeds a JS runtime (the Scratch VM, renderer, audio, storage). The packager
inlines the vendored [TurboWarp](https://packager.turbowarp.org/) scaffolding
runtime (`resources/scaffolding/scaffolding-min.js`, MPL-2.0), embeds the project
as base64, and drives it through the runtime's stable public API. The
Scratch-specific work (`--bridge`, `--countdown-seconds`, checksum file) is all
PHP.

### Maintaining the packager runtime

The vendored bundle is refreshed with
[`bin/refresh-scaffolding.sh`](bin/refresh-scaffolding.sh) — the only place
Node.js is used, and only at maintenance time, never at runtime.

## Implementation notes

- Project data is held as nested associative arrays mirroring `project.json`, so
  unknown fields round-trip faithfully. `CodeClubLuxembourg\Sb3Toolkit\Support\Json` re-casts
  Scratch's empty maps (`variables`, `blocks`, …) back to `{}` on save.
- The builder's reference objects bind to the live project data by reference, so
  mutations through `Sprite`/`BlockRef` update the project in place.
- The opcode registry, validation rules, and error codes cover the standard
  Scratch 3 block set.
