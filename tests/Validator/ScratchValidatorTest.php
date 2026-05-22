<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Validator;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchAssetIndex;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;
use CodeClubLuxembourg\Sb3Toolkit\Tests\Support\Fixtures;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ErrorCode;
use CodeClubLuxembourg\Sb3Toolkit\Validator\Rule\Rule;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ScratchValidator;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationError;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationPath;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationResult;
use CodeClubLuxembourg\Sb3Toolkit\Validator\ValidationSeverity;

final class ScratchValidatorTest extends TestCase
{
    public function testPassesAMinimalValidProject(): void
    {
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage()]));
        self::assertTrue($result->isValid);
        self::assertSame(0, $this->countErrors($result));
    }

    public function testReportsMissingStage(): void
    {
        $project = new ScratchProject(Fixtures::projectData([Fixtures::sprite('Cat')]), new ScratchAssetIndex());
        $result = ScratchValidator::validate($project);
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::MISSING_STAGE));
    }

    public function testReportsDuplicateStage(): void
    {
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), Fixtures::stage()]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::DUPLICATE_STAGE));
    }

    public function testReportsWrongTypeForInvalidRotationStyle(): void
    {
        $sprite = Fixtures::sprite('Cat', ['rotationStyle' => 'spin']);
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::WRONG_TYPE, 'rotationStyle'));
    }

    public function testReportsMissingFieldForMissingCostumeAssetId(): void
    {
        $costume = Fixtures::costume('stage-bg');
        unset($costume['assetId']);
        $project = Fixtures::makeProject([Fixtures::stage([$costume])]);
        $result = ScratchValidator::validate($project);
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::MISSING_FIELD, 'assetId'));
    }

    public function testUnknownOpcodeIsAWarningByDefault(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks']['abc'] = $this->topLevelBlock('custom_unknown_opcode');
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));

        self::assertTrue($this->hasCodeWithSeverity($result, ErrorCode::UNKNOWN_OPCODE, ValidationSeverity::Warning));
        self::assertTrue($result->isValid);
    }

    public function testReportsDanglingNext(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks']['abc'] = [
            'opcode' => 'motion_movesteps',
            'next' => 'nonexistent',
            'parent' => null,
            'inputs' => ['STEPS' => [1, [4, 10]]],
            'fields' => [],
            'shadow' => false,
            'topLevel' => true,
            'x' => 0,
            'y' => 0,
        ];
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::DANGLING_NEXT));
    }

    public function testReportsInconsistentNextParent(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks'] = [
            'a' => ['opcode' => 'event_whenflagclicked', 'next' => 'b', 'parent' => null, 'inputs' => [], 'fields' => [], 'shadow' => false, 'topLevel' => true, 'x' => 0, 'y' => 0],
            'b' => ['opcode' => 'motion_movesteps', 'next' => null, 'parent' => 'WRONG', 'inputs' => ['STEPS' => [1, [4, 10]]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
        ];
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::INCONSISTENT_NEXT_PARENT));
    }

    public function testReportsBlockCycle(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks'] = [
            'a' => ['opcode' => 'event_whenflagclicked', 'next' => 'b', 'parent' => null, 'inputs' => [], 'fields' => [], 'shadow' => false, 'topLevel' => true, 'x' => 0, 'y' => 0],
            'b' => ['opcode' => 'motion_movesteps', 'next' => 'a', 'parent' => 'a', 'inputs' => ['STEPS' => [1, [4, 10]]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
        ];
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::BLOCK_CYCLE));
    }

    public function testCycleMessageIncludesFullChain(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks'] = [
            'a' => ['opcode' => 'event_whenflagclicked', 'next' => 'b', 'parent' => null, 'inputs' => [], 'fields' => [], 'shadow' => false, 'topLevel' => true, 'x' => 0, 'y' => 0],
            'b' => ['opcode' => 'motion_movesteps', 'next' => 'c', 'parent' => 'a', 'inputs' => ['STEPS' => [1, [4, 10]]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
            'c' => ['opcode' => 'motion_movesteps', 'next' => 'b', 'parent' => 'b', 'inputs' => ['STEPS' => [1, [4, 5]]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
        ];
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        $cycleErrors = array_values(array_filter(
            $result->errors,
            static fn(ValidationError $e): bool => $e->code === ErrorCode::BLOCK_CYCLE,
        ));
        self::assertCount(1, $cycleErrors);
        self::assertStringContainsString('b → c → b', $cycleErrors[0]->message);
    }

    public function testCycleReportedOnceRegardlessOfEntryOrder(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks'] = [
            'a' => ['opcode' => 'event_whenflagclicked', 'next' => 'b', 'parent' => null, 'inputs' => [], 'fields' => [], 'shadow' => false, 'topLevel' => true, 'x' => 0, 'y' => 0],
            'b' => ['opcode' => 'motion_movesteps', 'next' => 'c', 'parent' => 'a', 'inputs' => ['STEPS' => [1, [4, 10]]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
            'c' => ['opcode' => 'motion_movesteps', 'next' => 'd', 'parent' => 'b', 'inputs' => ['STEPS' => [1, [4, 5]]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
            'd' => ['opcode' => 'motion_movesteps', 'next' => 'b', 'parent' => 'c', 'inputs' => ['STEPS' => [1, [4, 1]]], 'fields' => [], 'shadow' => false, 'topLevel' => false],
            'e' => ['opcode' => 'event_whenflagclicked', 'next' => 'c', 'parent' => null, 'inputs' => [], 'fields' => [], 'shadow' => false, 'topLevel' => true, 'x' => 100, 'y' => 100],
        ];
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        $cycleErrors = array_values(array_filter(
            $result->errors,
            static fn(ValidationError $e): bool => $e->code === ErrorCode::BLOCK_CYCLE,
        ));
        self::assertCount(1, $cycleErrors);
    }

    public function testNonStringNextDoesNotCrashCycleDetector(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks'] = [
            'a' => ['opcode' => 'event_whenflagclicked', 'next' => 42, 'parent' => null, 'inputs' => [], 'fields' => [], 'shadow' => false, 'topLevel' => true, 'x' => 0, 'y' => 0],
        ];
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        // Detector must terminate; INCONSISTENT_NEXT_PARENT or DANGLING_NEXT is fine,
        // we just don't want a TypeError.
        self::assertFalse($result->isValid);
    }

    public function testSkipsNullBlockEntries(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks']['dangling'] = null;
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        self::assertTrue($result->isValid);
    }

    public function testUnknownOpcodeIsAnErrorInStrictMode(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks']['abc'] = $this->topLevelBlock('totally_fake');
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]), strictOpcodes: true);
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeWithSeverity($result, ErrorCode::UNKNOWN_OPCODE, ValidationSeverity::Error));
    }

    public function testReportsMissingInput(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks']['abc'] = $this->topLevelBlock('motion_movesteps');
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::MISSING_INPUT, 'STEPS'));
    }

    public function testEmitsTopLevelNoCoordsWarning(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks']['abc'] = [
            'opcode' => 'event_whenflagclicked',
            'next' => null,
            'parent' => null,
            'inputs' => [],
            'fields' => [],
            'shadow' => false,
            'topLevel' => true,
        ];
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        self::assertTrue($this->hasCodeWithSeverity($result, ErrorCode::TOPLEVEL_NO_COORDS, ValidationSeverity::Warning));
        self::assertTrue($result->isValid);
    }

    public function testReportsMissingAsset(): void
    {
        $assets = new ScratchAssetIndex();
        $project = new ScratchProject(Fixtures::projectData([Fixtures::stage()]), $assets);
        $result = ScratchValidator::validate($project, assets: $assets);
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::MISSING_ASSET));
    }

    public function testSkipsAssetChecksWhenAssetsNotProvided(): void
    {
        $project = new ScratchProject(Fixtures::projectData([Fixtures::stage()]), new ScratchAssetIndex());
        $result = ScratchValidator::validate($project);
        self::assertFalse($this->hasCode($result, ErrorCode::MISSING_ASSET));
    }

    public function testReportsDanglingVariableRef(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks']['abc'] = [
            'opcode' => 'data_setvariableto',
            'next' => null,
            'parent' => null,
            'inputs' => ['VALUE' => [1, [10, '0']]],
            'fields' => ['VARIABLE' => ['myVar', 'nonexistent-id']],
            'shadow' => false,
            'topLevel' => true,
            'x' => 0,
            'y' => 0,
        ];
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::DANGLING_VARIABLE_REF));
    }

    public function testAcceptsVariableDefinedOnStageFromSpriteBlock(): void
    {
        $stage = Fixtures::stage();
        $stage['variables']['stage-var-id'] = ['score', 0];
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks']['abc'] = [
            'opcode' => 'data_setvariableto',
            'next' => null,
            'parent' => null,
            'inputs' => ['VALUE' => [1, [10, '5']]],
            'fields' => ['VARIABLE' => ['score', 'stage-var-id']],
            'shadow' => false,
            'topLevel' => true,
            'x' => 0,
            'y' => 0,
        ];
        $result = ScratchValidator::validate(Fixtures::makeProject([$stage, $sprite]));
        self::assertFalse($this->hasCode($result, ErrorCode::DANGLING_VARIABLE_REF));
    }

    public function testFractionalCurrentCostumeIsRejected(): void
    {
        $stage = Fixtures::stage();
        $stage['currentCostume'] = 0.5;
        $result = ScratchValidator::validate(Fixtures::makeProject([$stage]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::WRONG_TYPE, 'currentCostume'));
    }

    public function testOutOfRangeCurrentCostumeIsRejected(): void
    {
        $stage = Fixtures::stage();
        $stage['currentCostume'] = 5;
        $result = ScratchValidator::validate(Fixtures::makeProject([$stage]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::WRONG_TYPE, 'currentCostume'));
    }

    public function testStringIsStageIsNotTreatedAsStage(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['isStage'] = 'true';
        $result = ScratchValidator::validate(Fixtures::makeProject([$sprite]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::MISSING_STAGE));
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::WRONG_TYPE, 'isStage'));
    }

    public function testSpriteLayerOrderZeroIsRejected(): void
    {
        $sprite = Fixtures::sprite('Cat', ['layerOrder' => 0]);
        $result = ScratchValidator::validate(Fixtures::makeProject([Fixtures::stage(), $sprite]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::WRONG_TYPE, 'layerOrder'));
    }

    public function testNegativeVolumeIsRejected(): void
    {
        $stage = Fixtures::stage();
        $stage['volume'] = -10;
        $result = ScratchValidator::validate(Fixtures::makeProject([$stage]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::WRONG_TYPE, 'volume'));
    }

    public function testUnknownCostumeDataFormatIsRejected(): void
    {
        $stage = Fixtures::stage([Fixtures::costume('stage-bg') + ['dataFormat' => 'tiff']]);
        $stage['costumes'][0]['dataFormat'] = 'tiff';
        $result = ScratchValidator::validate(Fixtures::makeProject([$stage]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::WRONG_TYPE, 'dataFormat'));
    }

    public function testCustomRuleFindingsAppearAlongsideBuiltIns(): void
    {
        // A trivial Rule that flags every sprite with whitespace in its name.
        $rule = new class implements Rule {
            public function validate(ScratchProject $project): iterable
            {
                foreach ($project->data['targets'] as $i => $t) {
                    if (($t['isStage'] ?? false) === true) {
                        continue;
                    }
                    if (\is_string($t['name'] ?? null) && preg_match('/\s/', $t['name']) === 1) {
                        yield new ValidationError(
                            ErrorCode::WRONG_TYPE,
                            new ValidationPath(\sprintf('targets[%d].name', $i), $i),
                            \sprintf('Sprite name "%s" contains whitespace', $t['name']),
                            ValidationSeverity::Warning,
                        );
                    }
                }
            }
        };

        $project = Fixtures::makeProject([Fixtures::stage(), Fixtures::sprite('Bad Name')]);
        $result = ScratchValidator::validate($project, rules: [$rule]);

        $custom = array_values(array_filter(
            $result->errors,
            static fn(ValidationError $e): bool => str_contains($e->message, 'whitespace'),
        ));
        self::assertCount(1, $custom);
        self::assertSame(1, $custom[0]->path->targetIndex);
    }

    public function testWithRuleReturnsImmutableValidator(): void
    {
        $base = new ScratchValidator();
        $rule = new class implements Rule {
            public function validate(ScratchProject $project): iterable
            {
                yield new ValidationError(
                    ErrorCode::WRONG_TYPE,
                    'custom',
                    'always fires',
                    ValidationSeverity::Warning,
                );
            }
        };
        $extended = $base->withRule($rule);

        self::assertNotSame($base, $extended);

        $project = Fixtures::makeProject([Fixtures::stage()]);
        $baseErrors = $base->run($project)->errors;
        $extendedErrors = $extended->run($project)->errors;

        self::assertCount(\count($baseErrors) + 1, $extendedErrors);
    }

    public function testValidationPathExposesTargetIndexAndBlockId(): void
    {
        $sprite = Fixtures::sprite('Cat');
        $sprite['blocks']['abc'] = $this->topLevelBlock('totally_fake');
        $result = ScratchValidator::validate(
            Fixtures::makeProject([Fixtures::stage(), $sprite]),
            strictOpcodes: true,
        );

        $blockErrors = array_values(array_filter(
            $result->errors,
            static fn(ValidationError $e): bool => $e->code === ErrorCode::UNKNOWN_OPCODE,
        ));
        self::assertCount(1, $blockErrors);
        self::assertSame(1, $blockErrors[0]->path->targetIndex);
        self::assertSame('abc', $blockErrors[0]->path->blockId);
        self::assertSame('targets[1].blocks.abc.opcode', (string) $blockErrors[0]->path);
    }

    public function testValidationPathHasNullTargetIndexForProjectLevelErrors(): void
    {
        $project = new ScratchProject(['monitors' => [], 'meta' => ['semver' => '3.0.0']], new ScratchAssetIndex());
        $result = ScratchValidator::validate($project);
        $missing = array_values(array_filter(
            $result->errors,
            static fn(ValidationError $e): bool => $e->code === ErrorCode::MISSING_FIELD && (string) $e->path === 'targets',
        ));
        self::assertCount(1, $missing);
        self::assertNull($missing[0]->path->targetIndex);
        self::assertNull($missing[0]->path->blockId);
    }

    public function testNonArrayTargetsDoesNotCrashValidator(): void
    {
        $project = new ScratchProject(['targets' => 'oops', 'monitors' => [], 'meta' => ['semver' => '3.0.0']], new ScratchAssetIndex());
        $result = ScratchValidator::validate($project);
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::MISSING_FIELD));
    }

    public function testScalarTargetIsRejectedWithoutCrash(): void
    {
        $project = new ScratchProject(
            ['targets' => [Fixtures::stage(), 'not-a-target'], 'monitors' => [], 'meta' => ['semver' => '3.0.0']],
            new ScratchAssetIndex(),
        );
        $result = ScratchValidator::validate($project);
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCode($result, ErrorCode::WRONG_TYPE));
    }

    public function testNegativeSoundRateIsRejected(): void
    {
        $sound = Fixtures::sound('s1');
        $sound['rate'] = -1;
        $stage = Fixtures::stage(null, [$sound]);
        $result = ScratchValidator::validate(Fixtures::makeProject([$stage]));
        self::assertFalse($result->isValid);
        self::assertTrue($this->hasCodeAtPath($result, ErrorCode::WRONG_TYPE, 'rate'));
    }

    /**
     * @return array<string, mixed>
     */
    private function topLevelBlock(string $opcode): array
    {
        return [
            'opcode' => $opcode,
            'next' => null,
            'parent' => null,
            'inputs' => [],
            'fields' => [],
            'shadow' => false,
            'topLevel' => true,
            'x' => 0,
            'y' => 0,
        ];
    }

    private function countErrors(ValidationResult $result): int
    {
        $count = 0;
        foreach ($result->errors as $error) {
            if ($error->severity === ValidationSeverity::Error) {
                ++$count;
            }
        }

        return $count;
    }

    private function hasCode(ValidationResult $result, ErrorCode $code): bool
    {
        foreach ($result->errors as $error) {
            if ($error->code === $code) {
                return true;
            }
        }

        return false;
    }

    private function hasCodeAtPath(ValidationResult $result, ErrorCode $code, string $pathFragment): bool
    {
        foreach ($result->errors as $error) {
            if ($error->code === $code && str_contains($error->path->path, $pathFragment)) {
                return true;
            }
        }

        return false;
    }

    private function hasCodeWithSeverity(ValidationResult $result, ErrorCode $code, ValidationSeverity $severity): bool
    {
        foreach ($result->errors as $error) {
            if ($error->code === $code && $error->severity === $severity) {
                return true;
            }
        }

        return false;
    }
}
