<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Scratch;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\Key;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

/**
 * Verifies that the Builder navigation/edit API works against projects
 * loaded from `.sb3` — the object tree is the raw blocks map by reference,
 * so loaded projects accept the same operations as freshly built ones.
 */
final class RoundTripTest extends TestCase
{
    private string $tmpFile = '';

    protected function tearDown(): void
    {
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testFindsAndRewritesScriptsOnLoadedProject(): void
    {
        // Build, save, load, mutate, save again, reload.
        $original = ScratchProject::create();
        $cat = $original->addSprite('Cat');
        $cat->addScript(
            Events::whenFlagClicked()
                ->then(Motion::moveSteps(10))
                ->then(Looks::say('hello'))
        );

        $this->tmpFile = tempnam(sys_get_temp_dir(), 'sb3') . '.sb3';
        $original->saveToSb3File($this->tmpFile);

        // Load fresh — none of the original Block builder objects survive.
        $loaded = ScratchProject::fromSb3File($this->tmpFile);
        $cat = $loaded->sprite('Cat');

        // Find every move-steps and double it via the BlockRef API.
        foreach ($cat->findBlocks('motion_movesteps') as $ref) {
            $current = $ref->getInputLiteralValue('STEPS');
            self::assertIsInt($current);
            $ref->input('STEPS', $current * 2);
        }

        // Walk the loaded script with chain() to assert structure round-tripped.
        $hat = $cat->findScripts('event_whenflagclicked')[0];
        $opcodes = [];
        foreach ($hat->chain() as $blockRef) {
            $opcodes[] = $blockRef->opcode();
        }
        self::assertSame(['event_whenflagclicked', 'motion_movesteps', 'looks_say'], $opcodes);

        // Save again, reload, confirm the mutation persisted.
        $loaded->saveToSb3File($this->tmpFile);
        $again = ScratchProject::fromSb3File($this->tmpFile);
        $moves = $again->sprite('Cat')->findBlocks('motion_movesteps');
        self::assertCount(1, $moves);
        self::assertSame(20, $moves[0]->getInputLiteralValue('STEPS'));
    }

    public function testFindAllScriptsOnLoadedProject(): void
    {
        $project = ScratchProject::create();
        $project->stage()->addScript(Events::whenFlagClicked());
        $project->addSprite('Cat')->addScript(Events::whenKeyPressed(Key::Space));

        $this->tmpFile = tempnam(sys_get_temp_dir(), 'sb3') . '.sb3';
        $project->saveToSb3File($this->tmpFile);
        $loaded = ScratchProject::fromSb3File($this->tmpFile);

        $locations = iterator_to_array($loaded->findAllScripts(), false);
        self::assertCount(2, $locations);
    }

    public function testRemoveScriptOnLoadedProject(): void
    {
        $original = ScratchProject::create();
        $cat = $original->addSprite('Cat');
        $cat->addScript(Events::whenFlagClicked()->then(Motion::moveSteps(5)));
        $cat->addScript(Events::whenKeyPressed(Key::Space)->then(Looks::say('hi')));

        $this->tmpFile = tempnam(sys_get_temp_dir(), 'sb3') . '.sb3';
        $original->saveToSb3File($this->tmpFile);
        $loaded = ScratchProject::fromSb3File($this->tmpFile);
        $cat = $loaded->sprite('Cat');

        // Drop the key-pressed script entirely.
        foreach ($cat->findScripts('event_whenkeypressed') as $script) {
            $script->removeScript();
        }

        self::assertCount(1, $cat->findScripts());
        self::assertCount(0, $cat->findBlocks('looks_say'));
    }
}
