<?php

declare(strict_types=1);

namespace kim\present\utils\selectionvisualize\tests;

use kim\present\utils\selectionvisualize\PacketDispatcher;
use kim\present\utils\selectionvisualize\Selection;
use kim\present\utils\selectionvisualize\SelectionBlockData;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\world\World;
use RuntimeException;

#[CoversNothing]
final class SelectionTest extends TestCase{
    /** @var list<array{Player, array}> */
    private array $sent = [];

    protected function setUp() : void{
        $this->sent = [];
        PacketDispatcher::setHandler(function(Player $p, array $packets) : void{
            $this->sent[] = [$p, $packets];
        });
    }

    protected function tearDown() : void{
        PacketDispatcher::setHandler(null);
    }

    private function mockPlayer() : Player{
        return $this->createStub(TestPlayer::class);
    }

    private function world() : World{
        $world = $this->createStub(World::class);
        $world->method('createBlockUpdatePackets')->willReturnCallback(
            fn(array $blocks) => [UpdateBlockPacket::create(
                \pocketmine\network\mcpe\protocol\types\BlockPosition::fromVector3($blocks[0]), 5,
                UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_NORMAL
            )]
        );
        return $world;
    }

    private function player(World $world, bool $connected = true) : Player{
        $current = $world;
        $player = $this->mockPlayer();
        $player->method('getWorld')->willReturnCallback(function() use (&$current){ return $current; });
        $player->method('isConnected')->willReturnCallback(function() use (&$connected){ return $connected; });
        return $player;
    }

    public function testMinMax() : void{
        $s = new Selection(new Vector3(5, 10, -3), new Vector3(-2, 20, 4));
        self::assertEquals(new Vector3(-2, 10, -3), $s->getMin());
        self::assertEquals(new Vector3(5, 20, 4), $s->getMax());
        $f = new Selection(new Vector3(-0.5, 0, 0), new Vector3(1.5, 0, 0));
        self::assertEquals(-1, $f->getMin()->x);
        self::assertEquals(1, $f->getMax()->x);
    }

    public function testSendToPacketsAndNbt() : void{
        $world = $this->world();
        $player = $this->player($world);
        $s = new Selection(new Vector3(10, 70, 20), new Vector3(12, 73, 25));
        $s->sendTo($player);

        self::assertTrue($s->hasViewer($player));
        self::assertCount(1, $this->sent);
        [$p, [$update, $actor]] = $this->sent[0];
        self::assertSame($player, $p);
        self::assertInstanceOf(UpdateBlockPacket::class, $update);
        self::assertInstanceOf(BlockActorDataPacket::class, $actor);
        self::assertSame(10, $update->blockPosition->getX());
        self::assertSame(World::Y_MIN, $update->blockPosition->getY());
        self::assertSame(20, $update->blockPosition->getZ());

        $nbt = $actor->nbt->getRoot();
        self::assertSame(1, $nbt->getByte('showBoundingBox'));
        self::assertSame(3, $nbt->getInt('xStructureSize'));
        self::assertSame(4, $nbt->getInt('yStructureSize'));
        self::assertSame(6, $nbt->getInt('zStructureSize'));
        self::assertSame(0, $nbt->getInt('xStructureOffset'));
        self::assertSame(70 - World::Y_MIN, $nbt->getInt('yStructureOffset'));
        self::assertSame(0, $nbt->getInt('zStructureOffset'));
    }

    public function testSlotAllocationAndRelease() : void{
        $world = $this->world();
        $player = $this->player($world);
        $a = new Selection(new Vector3(0, 70, 0), new Vector3(1, 71, 1));
        $b = new Selection(new Vector3(0, 80, 0), new Vector3(1, 81, 1));
        $a->sendTo($player);
        $b->sendTo($player);
        self::assertSame(World::Y_MIN, $this->sent[0][1][0]->blockPosition->getY());
        self::assertSame(World::Y_MIN + 1, $this->sent[1][1][0]->blockPosition->getY());

        // resending reuses the freed slot after restore
        $a->sendTo($player);
        self::assertSame(World::Y_MIN, $this->sent[3][1][0]->blockPosition->getY());

        $a->restoreFrom($player);
        self::assertFalse($a->hasViewer($player));
        $c = new Selection(new Vector3(0, 90, 0), new Vector3(1, 91, 1));
        $c->sendTo($player);
        self::assertSame(World::Y_MIN, end($this->sent)[1][0]->blockPosition->getY());
    }

    public function testRestoreSendsRealBlock() : void{
        $world = $this->world();
        $player = $this->player($world);
        $s = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $s->sendTo($player);
        $s->restoreFrom($player);
        self::assertCount(2, $this->sent);
        self::assertInstanceOf(UpdateBlockPacket::class, $this->sent[1][1][0]);
        $s->restoreFrom($player);
        self::assertCount(2, $this->sent);
    }

    public function testRestoreAfterWorldChangeOnlyReleasesSlot() : void{
        $world = $this->world();
        $other = $this->world();
        $current = $world;
        $player = $this->mockPlayer();
        $player->method('getWorld')->willReturnCallback(function() use (&$current){ return $current; });
        $player->method('isConnected')->willReturn(true);

        $s = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $s->sendTo($player);
        $current = $other;
        $s->restoreFrom($player);
        self::assertCount(1, $this->sent, 'no restore packet for the other world');
        self::assertFalse($s->hasViewer($player));

        $t = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $t->sendTo($player);
        self::assertSame(World::Y_MIN, end($this->sent)[1][0]->blockPosition->getY(), 'slot was released');
    }

    public function testSlotsAreSeparatePerWorld() : void{
        $w1 = $this->world();
        $w2 = $this->world();
        $current = $w1;
        $player = $this->mockPlayer();
        $player->method('getWorld')->willReturnCallback(function() use (&$current){ return $current; });
        $player->method('isConnected')->willReturn(true);

        $a = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $b = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $a->sendTo($player);
        $current = $w2;
        $b->sendTo($player);
        self::assertSame(World::Y_MIN, $this->sent[1][1][0]->blockPosition->getY());
    }

    public function testForgetAndReleaseAll() : void{
        $world = $this->world();
        $player = $this->player($world);
        $a = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $a->sendTo($player);
        $a->forget($player);
        self::assertFalse($a->hasViewer($player));
        self::assertCount(1, $this->sent, 'forget sends nothing');

        $b = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $c = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $b->sendTo($player);
        $c->sendTo($player);
        self::assertSame(World::Y_MIN + 1, end($this->sent)[1][0]->blockPosition->getY());
        Selection::releaseAll($player);
        $d = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $d->sendTo($player);
        self::assertSame(World::Y_MIN, end($this->sent)[1][0]->blockPosition->getY());
    }

    public function testNewPlayerObjectDoesNotInheritSlots() : void{
        $world = $this->world();
        $a = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $p1 = $this->player($world);
        $a->sendTo($p1);
        unset($p1);
        gc_collect_cycles();
        $p2 = $this->player($world);
        $b = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $b->sendTo($p2);
        self::assertSame(World::Y_MIN, end($this->sent)[1][0]->blockPosition->getY());
    }

    public function testRestoreFromAll() : void{
        $world = $this->world();
        $p1 = $this->player($world);
        $p2 = $this->player($world);
        $s = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
        $s->sendTo($p1);
        $s->sendTo($p2);
        $s->restoreFromAll();
        self::assertFalse($s->hasViewer($p1));
        self::assertFalse($s->hasViewer($p2));
        self::assertCount(4, $this->sent);
    }

    public function testSizeIsNotClamped() : void{
        $world = $this->world();
        $player = $this->player($world);
        $s = new Selection(new Vector3(0, 0, 0), new Vector3(200, 99, 63));
        $s->sendTo($player);
        $nbt = $this->sent[0][1][1]->nbt->getRoot();
        self::assertSame(201, $nbt->getInt('xStructureSize'));
        self::assertSame(100, $nbt->getInt('yStructureSize'));
        self::assertSame(64, $nbt->getInt('zStructureSize'));
    }

    public function testFullColumnThrows() : void{
        $world = $this->world();
        $player = $this->player($world);
        $keep = [];
        $count = World::Y_MAX - World::Y_MIN;
        for($i = 0; $i < $count; ++$i){
            $keep[$i] = new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0));
            $keep[$i]->sendTo($player);
        }
        $this->expectException(RuntimeException::class);
        (new Selection(new Vector3(0, 70, 0), new Vector3(0, 70, 0)))->sendTo($player);
    }
}
