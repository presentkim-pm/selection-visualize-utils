<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\utils\selectionvisualize;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use WeakMap;
use WeakReference;

/**
 * Renders a temporary block preview to a player by:
 * - faking the install blocks on the normal layer
 * - optionally overlaying a block on the liquid layer (PreviewEntry::$liquidBlock)
 * - delaying packets by one tick to avoid being overwritten by server sync
 *
 * Typical usage:
 *
 * $preview = new BlockPreview($plugin);
 * $preview->show($player,
 *     new PreviewEntry($pos1, $block),
 *     new PreviewEntry($pos2, $block),
 * );
 *
 * // later
 * $preview->clear($player);
 */
final class BlockPreview{
    /**
     * Currently shown preview per player: the positions and the world they were sent in.
     *
     * @var WeakMap<Player, array{BlockPosition[], WeakReference<World>, bool}>
     */
    private WeakMap $lastPreview;

    /**
     * Entries waiting for the delayed flush.
     *
     * @var WeakMap<Player, PreviewEntry[]>
     */
    private WeakMap $pendingEntries;

    /**
     * Players that already have a flush task scheduled.
     *
     * @var WeakMap<Player, true>
     */
    private WeakMap $pendingTasks;

    /**
     * @param PluginBase $plugin Owner plugin used to schedule delayed preview tasks.
     */
    public function __construct(
        private readonly PluginBase $plugin,
    ){
        $this->lastPreview = new WeakMap();
        $this->pendingEntries = new WeakMap();
        $this->pendingTasks = new WeakMap();
    }

    /**
     * Returns the last previewed block positions for the given player.
     * This can be used for custom ray-casting (e.g. air-click targeting).
     *
     * @param Player $player Target player.
     *
     * @return BlockPosition[] Positions currently used to show the preview to this player.
     */
    public function getPreviewPositions(Player $player) : array{
        return ($this->lastPreview[$player] ?? [[]])[0];
    }

    /**
     * Clears any active or pending preview for the given player.
     *
     * Behaviour:
     * - Cancels a preview that was requested with show() but not flushed yet.
     * - Restores the NORMAL data layer to the real world block at each previewed position and clears the
     *   LIQUID data layer overlay (only if a liquid overlay was sent), but only if the player is still
     *   in the world the preview was shown in; after a world change just the bookkeeping is dropped.
     * - Forgets the stored preview positions for this player.
     *
     * @param Player $player Target player to clear preview for.
     */
    public function clear(Player $player) : void{
        // A preview that was not flushed yet must never appear after clear()
        unset($this->pendingEntries[$player]);

        if(!isset($this->lastPreview[$player])){
            return;
        }

        [$positions, $worldRef, $hasLiquid] = $this->lastPreview[$player];
        unset($this->lastPreview[$player]);

        $world = $worldRef->get();
        if($positions === [] || $world === null || !$player->isConnected() || $player->getWorld() !== $world){
            return;
        }

        $packets = [];
        $blocks = [];
        $airRuntimeId = $hasLiquid ? $this->getAirRuntimeId() : 0;
        foreach($positions as $blockPosition){
            if($hasLiquid){
                $packets[] = UpdateBlockPacket::create(
                    $blockPosition,
                    $airRuntimeId,
                    UpdateBlockPacket::FLAG_NETWORK,
                    UpdateBlockPacket::DATA_LAYER_LIQUID
                );
            }

            $blocks[] = new Vector3($blockPosition->getX(), $blockPosition->getY(), $blockPosition->getZ());
        }
        PacketDispatcher::send($player, [...$packets, ...$world->createBlockUpdatePackets($blocks)]);
    }

    /**
     * Shows a block preview to the given player.
     *
     * Behaviour:
     * - Immediately clears any existing (or still pending) preview for the player.
     * - Stores the provided PreviewEntry list as a pending preview.
     * - Schedules a 1-tick delayed task to actually send UpdateBlockPacket(s), to avoid
     *   being overwritten by PocketMine-MP's own resync packets on the same tick.
     * - Entries whose position is in another world than the player's are skipped.
     *
     * Each PreviewEntry is rendered as:
     * - NORMAL layer: entry->block (fake install block)
     * - LIQUID layer: entry->liquidBlock if provided; nothing is sent on this layer otherwise.
     *
     * @param Player       $player     Target player.
     * @param PreviewEntry ...$entries One or more preview entries to render.
     */
    public function show(Player $player, PreviewEntry ...$entries) : void{
        $this->clear($player);

        if($entries === []){
            return;
        }

        $this->pendingEntries[$player] = $entries;

        if(isset($this->pendingTasks[$player])){
            return;
        }

        $this->pendingTasks[$player] = true;

        // NOTE:
        // We delay preview packets by 1 tick instead of sending them immediately.
        // PocketMine-MP, on receiving a block interaction from the client, often sends its own
        // UpdateBlockPacket(s) around the clicked position to resync the client with the server state.
        // If we sent preview packets in the same tick, those automatic sync packets could overwrite
        // our fake NORMAL-layer blocks, causing missing or flickering preview (especially on corners).
        // Sending preview one tick later guarantees that the server has finished its resync and
        // our preview safely "wins" as the last writer for the client view.
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player) : void{
            unset($this->pendingTasks[$player]);

            $entries = $this->pendingEntries[$player] ?? null;
            if($entries === null){
                return;
            }
            unset($this->pendingEntries[$player]);

            if(!$player->isOnline()){
                return;
            }

            $this->flush($player, $entries);
        }), 1);
    }

    /**
     * Sends the collected preview packets for the given player.
     *
     * @param Player         $player  Target player.
     * @param PreviewEntry[] $entries Entries to render for this flush.
     */
    private function flush(Player $player, array $entries) : void{
        $packets = [];
        $positions = [];
        $hasLiquid = false;
        $world = $player->getWorld();
        foreach($entries as $entry){
            $pos = $entry->position;
            if($pos->getWorld() !== $world){
                continue;
            }

            $blockPosition = BlockPosition::fromVector3($pos);
            $positions[] = $blockPosition;

            $installRuntimeId = $this->getRuntimeIdFromBlock($entry->block);
            $packets[] = UpdateBlockPacket::create(
                $blockPosition,
                $installRuntimeId,
                UpdateBlockPacket::FLAG_NETWORK,
                UpdateBlockPacket::DATA_LAYER_NORMAL
            );

            if($entry->liquidBlock !== null){
                $hasLiquid = true;
                $packets[] = UpdateBlockPacket::create(
                    $blockPosition,
                    $this->getRuntimeIdFromBlock($entry->liquidBlock),
                    UpdateBlockPacket::FLAG_NETWORK,
                    UpdateBlockPacket::DATA_LAYER_LIQUID
                );
            }
        }

        if($positions !== []){
            $this->lastPreview[$player] = [$positions, WeakReference::create($world), $hasLiquid];
            PacketDispatcher::send($player, $packets);
        }
    }

    private function getAirRuntimeId() : int{
        /** @var int|null $runtimeId */
        static $runtimeId = null;
        return $runtimeId ??= $this->getRuntimeIdFromBlock(VanillaBlocks::AIR());
    }

    private function getRuntimeIdFromBlock(Block $block) : int{
        return TypeConverter::getInstance()
                            ->getBlockTranslator()
                            ->internalIdToNetworkId($block->getStateId());
    }
}

