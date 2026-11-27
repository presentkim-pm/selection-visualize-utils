<?php

declare(strict_types=1);

namespace kim\present\utils\selectionvisualize\tests;

use kim\present\utils\selectionvisualize\BlockPreview;
use kim\present\utils\selectionvisualize\PacketDispatcher;
use kim\present\utils\selectionvisualize\PreviewEntry;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\world\Position;
use pocketmine\world\World;

#[CoversNothing]
final class BlockPreviewTest extends TestCase{
    /** @var list<array{Player, array}> */
    private array $sent = [];
    /** @var ClosureTask[] */
    private array $tasks = [];
    private BlockPreview $preview;

    protected function setUp() : void{
        $this->sent = [];
        $this->tasks = [];
        PacketDispatcher::setHandler(function(Player $p, array $packets) : void{
            $this->sent[] = [$p, $packets];
        });
        $scheduler = $this->createStub(TaskScheduler::class);
        $scheduler->method('scheduleDelayedTask')->willReturnCallback(function(ClosureTask $t){
            $this->tasks[] = $t;
            return $this->createStub(TaskHandler::class);
        });
        $plugin = $this->createStub(PluginBase::class);
        $plugin->method('getScheduler')->willReturn($scheduler);
        $this->preview = new BlockPreview($plugin);
    }

    protected function tearDown() : void{
        PacketDispatcher::setHandler(null);
    }

    private function world() : World{
        $world = $this->createStub(World::class);
        $world->method('isLoaded')->willReturn(true);
        $world->method('createBlockUpdatePackets')->willReturnCallback(
            fn(array $b) => array_map(fn() => UpdateBlockPacket::create(
                new \pocketmine\network\mcpe\protocol\types\BlockPosition(0, 0, 0), 1,
                UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_NORMAL
            ), $b)
        );
        return $world;
    }

    private function player(World &$world) : Player{
        $player = $this->createStub(TestPlayer::class);
        $player->method('getWorld')->willReturnCallback(function() use (&$world){ return $world; });
        $player->method('isConnected')->willReturn(true);
        $player->method('isOnline')->willReturn(true);
        return $player;
    }

    private function runTasks() : void{
        $tasks = $this->tasks;
        $this->tasks = [];
        foreach($tasks as $t){
            $t->onRun();
        }
    }

    public function testShowIsDelayedAndFlushed() : void{
        $world = $this->world();
        $player = $this->player($world);
        $this->preview->show($player, new PreviewEntry(new Position(1, 2, 3, $world), VanillaBlocks::STONE()));
        self::assertCount(1, $this->tasks);
        self::assertSame([], $this->sent);
        $this->runTasks();
        self::assertCount(1, $this->sent);
        self::assertCount(1, $this->sent[0][1]);
        self::assertCount(1, $this->preview->getPreviewPositions($player));
    }

    public function testShowThenClearSameTickShowsNothing() : void{
        $world = $this->world();
        $player = $this->player($world);
        $this->preview->show($player, new PreviewEntry(new Position(1, 2, 3, $world), VanillaBlocks::STONE()));
        $this->preview->clear($player);
        $this->runTasks();
        self::assertSame([], $this->sent);
        self::assertSame([], $this->preview->getPreviewPositions($player));
    }

    public function testShowTwiceSchedulesOnceAndUsesLast() : void{
        $world = $this->world();
        $player = $this->player($world);
        $this->preview->show($player, new PreviewEntry(new Position(1, 2, 3, $world), VanillaBlocks::STONE()));
        $this->preview->show($player,
            new PreviewEntry(new Position(1, 2, 3, $world), VanillaBlocks::STONE()),
            new PreviewEntry(new Position(4, 2, 3, $world), VanillaBlocks::STONE()),
        );
        self::assertCount(1, $this->tasks);
        $this->runTasks();
        self::assertCount(2, $this->preview->getPreviewPositions($player));
    }

    public function testNoLiquidPacketWithoutLiquidBlock() : void{
        $world = $this->world();
        $player = $this->player($world);
        $this->preview->show($player,
            new PreviewEntry(new Position(1, 2, 3, $world), VanillaBlocks::STONE()),
            new PreviewEntry(new Position(2, 2, 3, $world), VanillaBlocks::STONE(), VanillaBlocks::GLASS()),
        );
        $this->runTasks();
        $layers = array_map(fn($p) => $p->dataLayerId, $this->sent[0][1]);
        self::assertSame([0, 0, 1], $layers);
    }

    public function testClearRestoresInSameWorld() : void{
        $world = $this->world();
        $player = $this->player($world);
        $this->preview->show($player, new PreviewEntry(new Position(1, 2, 3, $world), VanillaBlocks::STONE()));
        $this->runTasks();
        $this->preview->clear($player);
        self::assertCount(2, $this->sent);
        self::assertCount(1, $this->sent[1][1], 'only the real block, no liquid clear without liquid overlay');
        self::assertSame([], $this->preview->getPreviewPositions($player));
    }

    public function testClearAfterWorldChangeSendsNothing() : void{
        $world = $this->world();
        $player = $this->player($world);
        $this->preview->show($player, new PreviewEntry(new Position(1, 2, 3, $world), VanillaBlocks::STONE()));
        $this->runTasks();
        $world = $this->world();
        $this->preview->clear($player);
        self::assertCount(1, $this->sent);
        self::assertSame([], $this->preview->getPreviewPositions($player));
    }

    public function testEntriesInOtherWorldAreSkipped() : void{
        $world = $this->world();
        $other = $this->world();
        $player = $this->player($world);
        $this->preview->show($player, new PreviewEntry(new Position(1, 2, 3, $other), VanillaBlocks::STONE()));
        $this->runTasks();
        self::assertSame([], $this->sent);
    }
}
