<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Tests\Builder;

use PHPUnit\Framework\TestCase;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Block;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\CommentRef;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

final class SoundCommentBroadcastTest extends TestCase
{
    public function testAddSoundWithExplicitRateAndSampleCount(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $idx = $sprite->addSound('beep', 'rawbytes', 'mp3', rate: 22_050, sampleCount: 1024);

        $data = $sprite->data();
        self::assertSame(0, $idx);
        self::assertCount(1, $data['sounds']);
        self::assertSame('beep', $data['sounds'][0]['name']);
        self::assertSame('mp3', $data['sounds'][0]['dataFormat']);
        self::assertSame(22_050, $data['sounds'][0]['rate']);
        self::assertSame(1024, $data['sounds'][0]['sampleCount']);
        self::assertTrue($project->assets->has($data['sounds'][0]['md5ext']));
    }

    public function testAddSoundParsesWavHeader(): void
    {
        // Minimal valid PCM WAV: 22050 Hz, mono, 16-bit, 4 samples (8 bytes of data).
        $wav = "RIFF" . pack('V', 36 + 8) . 'WAVE'
            . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1) . pack('V', 22_050)
            . pack('V', 44_100) . pack('v', 2) . pack('v', 16)
            . 'data' . pack('V', 8) . str_repeat("\x00", 8);

        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $sprite->addSound('beep', $wav, 'wav');

        $sound = $sprite->data()['sounds'][0];
        self::assertSame(22_050, $sound['rate']);
        self::assertSame(4, $sound['sampleCount']);
    }

    public function testStageCanAddSounds(): void
    {
        $project = ScratchProject::create();
        $idx = $project->stage()->addSound('intro', 'bytes', 'mp3', 44_100, 100);
        self::assertSame(0, $idx);
        self::assertCount(1, $project->getStage()['sounds']);
    }

    public function testAddCommentFreeFloating(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $comment = $sprite->addComment('hello', 10, 20);

        self::assertInstanceOf(CommentRef::class, $comment);
        self::assertSame('hello', $comment->text());
        self::assertNull($comment->blockId());
        self::assertSame(10, $sprite->data()['comments'][$comment->id]['x']);
    }

    public function testAddCommentAnchoredToBlock(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $hat = Events::whenFlagClicked();
        $sprite->addScript($hat);

        $comment = $sprite->addComment('explain this hat', blockId: $hat->id);
        self::assertSame($hat->id, $comment->blockId());

        $found = $sprite->findComments($hat->id);
        self::assertCount(1, $found);
        self::assertSame($comment->id, $found[0]->id);
    }

    public function testCommentRefSetters(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $comment = $sprite->addComment('hi');

        $comment
            ->setText('updated')
            ->setPosition(5, 6)
            ->setSize(50, 60)
            ->setMinimized(true);

        $data = $sprite->data()['comments'][$comment->id];
        self::assertSame('updated', $data['text']);
        self::assertSame(5, $data['x']);
        self::assertSame(6, $data['y']);
        self::assertSame(50, $data['width']);
        self::assertSame(60, $data['height']);
        self::assertTrue($data['minimized']);
    }

    public function testCommentDelete(): void
    {
        $project = ScratchProject::create();
        $sprite = $project->addSprite('Cat');
        $comment = $sprite->addComment('temp');
        $comment->delete();
        self::assertSame([], $sprite->findComments());
    }

    public function testAddBroadcastReturnsRefAndStoresOnStage(): void
    {
        $project = ScratchProject::create();
        $ref = $project->addBroadcast('start');
        self::assertSame('start', $ref->name);
        self::assertSame('start', $project->getStage()['broadcasts'][$ref->id]);
    }

    public function testFindBroadcastByName(): void
    {
        $project = ScratchProject::create();
        $ref = $project->addBroadcast('reset');
        $found = $project->findBroadcast('reset');
        self::assertNotNull($found);
        self::assertSame($ref->id, $found->id);
        self::assertNull($project->findBroadcast('unknown'));
    }
}
