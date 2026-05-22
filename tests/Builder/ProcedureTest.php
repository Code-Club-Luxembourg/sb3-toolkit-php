<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Block;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Operators;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Procedure;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;

final class ProcedureTest extends TestCase
{
    public function testProccodeForArglessProcedure(): void
    {
        $proc = Procedure::define('greet');
        self::assertSame('greet', $proc->proccode());
    }

    public function testProccodeForArgs(): void
    {
        $proc = Procedure::define('doThing', [
            Procedure::stringArg('count'),
            Procedure::booleanArg('flag'),
        ]);
        self::assertSame('doThing %s %b', $proc->proccode());
    }

    public function testDefineRejectsDuplicateArgNames(): void
    {
        $this->expectException(RuntimeException::class);
        Procedure::define('p', [Procedure::stringArg('a'), Procedure::stringArg('a')]);
    }

    public function testDefinitionBlockCarriesMutation(): void
    {
        $proc = Procedure::define('p', [Procedure::stringArg('n')], warp: true);
        $blocks = $proc->definition()->toBlocks();

        $defBlock = null;
        $protoBlock = null;
        foreach ($blocks as $block) {
            if ($block['opcode'] === 'procedures_definition') $defBlock = $block;
            if ($block['opcode'] === 'procedures_prototype') $protoBlock = $block;
        }

        self::assertNotNull($defBlock);
        self::assertNotNull($protoBlock);
        self::assertTrue($protoBlock['shadow']);
        self::assertSame('p %s', $protoBlock['mutation']['proccode']);
        self::assertSame('true', $protoBlock['mutation']['warp']);
        self::assertJson($protoBlock['mutation']['argumentids']);
        self::assertJson($protoBlock['mutation']['argumentnames']);
        self::assertJson($protoBlock['mutation']['argumentdefaults']);
    }

    public function testArgReporterHasFieldValue(): void
    {
        $proc = Procedure::define('p', [Procedure::stringArg('count')]);
        $reporter = $proc->arg('count');
        $blocks = $reporter->toBlocks();
        $r = $blocks[$reporter->id];
        self::assertSame('argument_reporter_string_number', $r['opcode']);
        self::assertSame(['count', null], $r['fields']['VALUE']);
    }

    public function testArgReporterIsBooleanWhenKindIsBoolean(): void
    {
        $proc = Procedure::define('p', [Procedure::booleanArg('flag')]);
        $reporter = $proc->arg('flag');
        self::assertSame('argument_reporter_boolean', $reporter->toBlocks()[$reporter->id]['opcode']);
    }

    public function testCallBlockUsesArgIdsAsSlotKeys(): void
    {
        $proc = Procedure::define('p', [
            Procedure::stringArg('count'),
            Procedure::booleanArg('flag'),
        ]);
        $cond = Operators::equals(1, 1);
        $call = $proc->call(42, $cond);
        $blocks = $call->toBlocks();
        $callBlock = $blocks[$call->id];
        $argIds = json_decode($callBlock['mutation']['argumentids'], true);
        self::assertCount(2, $argIds);

        // The inputs map should be keyed by the arg ids, with the supplied values.
        self::assertArrayHasKey($argIds[0], $callBlock['inputs']);
        self::assertArrayHasKey($argIds[1], $callBlock['inputs']);
        self::assertSame([1, [4, 42]], $callBlock['inputs'][$argIds[0]]);
        self::assertSame(2, $callBlock['inputs'][$argIds[1]][0]); // NO_SHADOW for the boolean Block
    }

    public function testCallRejectsBoolValueForStringArg(): void
    {
        $proc = Procedure::define('p', [Procedure::stringArg('n')]);
        $this->expectException(RuntimeException::class);
        $proc->call(true);
    }

    public function testCallRejectsNonBlockForBooleanArg(): void
    {
        $proc = Procedure::define('p', [Procedure::booleanArg('flag')]);
        $this->expectException(RuntimeException::class);
        $proc->call('not a block');
    }

    public function testCallRejectsWrongArgCount(): void
    {
        $proc = Procedure::define('p', [Procedure::stringArg('a'), Procedure::stringArg('b')]);
        $this->expectException(RuntimeException::class);
        $proc->call('only-one');
    }

    public function testEndToEndProcedureBuildsValidProject(): void
    {
        $project = ScratchProject::create();
        $cat = $project->addSprite('Cat');

        $proc = Procedure::define('move-and-say', [
            Procedure::stringArg('msg'),
        ]);

        // Body: move 10 steps, then say($msg).
        $cat->addScript(
            $proc->definition()
                ->then(Motion::moveSteps(10))
                ->then(Looks::say($proc->arg('msg')))
        );

        // Caller: when flag clicked → call procedure with 'hi'.
        $cat->addScript(Events::whenFlagClicked()->then($proc->call('hi')));

        $result = ScratchValidator::validate($project);
        self::assertTrue($result->isValid, 'project should validate; errors: ' . implode(', ', array_map(
            static fn ($e) => (string) $e->path . ' ' . $e->code->value . ' ' . $e->message,
            $result->errors,
        )));
    }
}
