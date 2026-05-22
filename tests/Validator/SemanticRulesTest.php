<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Validator;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Data;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\OrphanedBlocksRule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\UnusedVariablesRule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;

final class SemanticRulesTest extends TestCase
{
    public function testOrphanedBlockReportedAsWarning(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addScript(Events::whenFlagClicked()->then(Motion::moveSteps(10)));

        // Inject an unreachable block directly into the live target's blocks
        // map. `$project->data` is a public mutable array, so we can splice
        // arbitrary entries in for the rule to find.
        $spriteIndex = null;
        foreach ($project->data['targets'] as $i => $target) {
            if (($target['name'] ?? null) === 'Cat') {
                $spriteIndex = $i;
                break;
            }
        }
        self::assertNotNull($spriteIndex);
        $project->data['targets'][$spriteIndex]['blocks']['orphan-id'] = [
            'opcode' => 'motion_movesteps',
            'next' => null,
            'parent' => null,
            'inputs' => ['STEPS' => [1, [4, 5]]],
            'fields' => [],
            'shadow' => false,
            'topLevel' => false,
        ];

        $validator = (new ScratchValidator())->withRule(new OrphanedBlocksRule());
        $errors = $validator->run($project)->errors;
        $orphans = array_values(array_filter(
            $errors,
            static fn ($e) => $e->code === ErrorCode::ORPHANED_BLOCK,
        ));
        self::assertCount(1, $orphans);
        self::assertSame('orphan-id', $orphans[0]->path->blockId);
    }

    public function testCleanProjectHasNoOrphans(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addScript(Events::whenFlagClicked()->then(Motion::moveSteps(10)));

        $validator = (new ScratchValidator())->withRule(new OrphanedBlocksRule());
        $errors = $validator->run($project)->errors;
        self::assertSame([], array_values(array_filter(
            $errors,
            static fn ($e) => $e->code === ErrorCode::ORPHANED_BLOCK,
        )));
    }

    public function testUnusedVariableReported(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addVariable('unused', 0);
        $cat->addScript(Events::whenFlagClicked());

        $validator = (new ScratchValidator())->withRule(new UnusedVariablesRule());
        $errors = $validator->run($project)->errors;
        $unused = array_filter($errors, static fn ($e) => $e->code === ErrorCode::UNUSED_VARIABLE);
        self::assertCount(1, $unused);
    }

    public function testUsedVariableNotReported(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $score = $cat->addVariable('score', 0);
        $cat->addScript(Events::whenFlagClicked()->then(Data::setVariableTo(5, $score)));

        $validator = (new ScratchValidator())->withRule(new UnusedVariablesRule());
        $errors = $validator->run($project)->errors;
        $unused = array_filter($errors, static fn ($e) => $e->code === ErrorCode::UNUSED_VARIABLE);
        self::assertSame([], array_values($unused));
    }

    public function testUnusedListReported(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');
        $cat->addList('inventory');
        $cat->addScript(Events::whenFlagClicked());

        $validator = (new ScratchValidator())->withRule(new UnusedVariablesRule());
        $errors = $validator->run($project)->errors;
        $unused = array_filter($errors, static fn ($e) => $e->code === ErrorCode::UNUSED_LIST);
        self::assertCount(1, $unused);
    }

    public function testStageVariableReferencedFromSpriteCountsAsUsed(): void
    {
        $project = ScratchProject::create();
        $score = $project->addVariable('score', 0);
        $cat = $project->addSprite('Cat');
        $cat->addScript(Events::whenFlagClicked()->then(Data::setVariableTo(5, $score)));

        $validator = (new ScratchValidator())->withRule(new UnusedVariablesRule());
        $errors = $validator->run($project)->errors;
        $unused = array_filter($errors, static fn ($e) => $e->code === ErrorCode::UNUSED_VARIABLE);
        self::assertSame([], array_values($unused));
    }
}
