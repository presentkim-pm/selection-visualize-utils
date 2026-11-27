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

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\world\World;
use RuntimeException;
use WeakMap;
use WeakReference;

use function floor;
use function max;
use function min;

/**
 * Visualizes an axis-aligned 3D region (pos1-pos2) to players using structure blocks.
 * Each player gets at most one structure block per Selection; sending again replaces
 * that player's block and reuses the same slot (position is reallocated per player/world/column).
 *
 * Bookkeeping never keeps a Player alive (WeakMap / WeakReference), and slots are bound to the
 * Player object itself, so a new Player can never inherit the slots of a previous one.
 * Players that leave without restoreFrom() should be passed to {@see Selection::releaseAll()} (or
 * {@see Selection::forget()}) to free their slots immediately; otherwise they are freed once the
 * Player object is garbage collected.
 *
 * Size limit: the size is sent as is. The Bedrock client may not draw bounding boxes larger than
 * its structure block limit, so very large selections may not be visualized completely.
 */
final class Selection{

    /**
     * Used structure block slots ("x:y:z" keys) per player and per world.
     *
     * @var WeakMap<Player, WeakMap<World, array<string, true>>>|null
     */
    private static ?WeakMap $usedSlots = null;

    /**
     * Per viewer: the block data and the world it was sent in.
     *
     * @var WeakMap<Player, array{SelectionBlockData, WeakReference<World>}>
     */
    private WeakMap $entries;

    /**
     * @param Vector3 $pos1 First corner of the selection region.
     * @param Vector3 $pos2 Second corner (axis-aligned box with pos1).
     */
    public function __construct(
        public Vector3 $pos1,
        public Vector3 $pos2
    ){
        $this->entries = new WeakMap();
    }

    public function getMin() : Vector3{
        return new Vector3(
            (int) floor(min($this->pos1->x, $this->pos2->x)),
            (int) floor(min($this->pos1->y, $this->pos2->y)),
            (int) floor(min($this->pos1->z, $this->pos2->z))
        );
    }

    public function getMax() : Vector3{
        return new Vector3(
            (int) floor(max($this->pos1->x, $this->pos2->x)),
            (int) floor(max($this->pos1->y, $this->pos2->y)),
            (int) floor(max($this->pos1->z, $this->pos2->z))
        );
    }

    /**
     * Sends this selection visualization to the given player.
     *
     * Behavior: one structure block per player per Selection. If this player already
     * had a block for this Selection, it is restored first and the same block data
     * is reused at a newly allocated position (per-player Y slot, per world). Then the block
     * and tile NBT are sent so the client shows the bounding box.
     *
     * @param Player $player Target to show the selection to.
     * Side effects: sends UpdateBlockPacket and BlockActorDataPacket; registers
     * this player as a viewer and allocates a Y slot for the structure block.
     *
     * @throws RuntimeException If every Y slot of the target column is already in use for this player.
     */
    public function sendTo(Player $player) : void{
        if(isset($this->entries[$player])){
            $data = $this->entries[$player][0];
            $this->restoreFrom($player);
        }else{
            $data = new SelectionBlockData();
        }

        $world = $player->getWorld();
        $min = $this->getMin();
        $max = $this->getMax();
        $minY = self::allocateY($player, $world, (int) $min->x, (int) $min->z);

        $data->pos = new Vector3($min->x, $minY, $min->z);
        $data->setOffset(new Vector3(0, $min->y - $minY, 0));
        $data->setSize($max->subtractVector($min)->add(1, 1, 1));

        $blockPos = BlockPosition::fromVector3($data->pos);
        PacketDispatcher::send($player, [
            UpdateBlockPacket::create($blockPos,
                $data->networkId,
                UpdateBlockPacket::FLAG_NETWORK,
                UpdateBlockPacket::DATA_LAYER_NORMAL
            ),
            BlockActorDataPacket::create($blockPos, new CacheableNbt($data->tileNbt))
        ]);

        $this->entries[$player] = [$data, WeakReference::create($world)];
    }

    /**
     * Restores the original block for this selection for the given player.
     *
     * Removes the structure block shown to this player and frees the per-player
     * Y slot so it can be reused. Restore packets are only sent if the player is still
     * connected and still in the world the block was sent in; otherwise the slot is just released.
     * No-op if the player was not viewing this selection.
     *
     * @param Player $player Player whose visualization to remove.
     * Side effects: may send block update packets; unregisters viewer and releases Y slot.
     */
    public function restoreFrom(Player $player) : void{
        if(!isset($this->entries[$player])){
            return;
        }

        [$data, $worldRef] = $this->entries[$player];
        unset($this->entries[$player]);

        $pos = $data->pos;
        self::releaseY($player, $worldRef, $pos);

        $world = $worldRef->get();
        if($world !== null && $player->isConnected() && $player->getWorld() === $world){
            PacketDispatcher::send($player, $world->createBlockUpdatePackets([$pos]));
        }
    }

    /**
     * Stops tracking the given player for this selection and frees its slot WITHOUT sending
     * any packet. Use it when the player is gone (quit, transferred) or the client is already resynced.
     */
    public function forget(Player $player) : void{
        if(!isset($this->entries[$player])){
            return;
        }

        [$data, $worldRef] = $this->entries[$player];
        unset($this->entries[$player]);
        self::releaseY($player, $worldRef, $data->pos);
    }

    /**
     * Frees every structure block slot of the given player, across all selections, without sending
     * packets. Call it when the player quits (e.g. from PlayerQuitEvent) so no state lingers.
     * Selection instances that still track the player stay harmless: releasing an already freed slot is a no-op.
     */
    public static function releaseAll(Player $player) : void{
        if(self::$usedSlots !== null){
            unset(self::$usedSlots[$player]);
        }
    }

    /**
     * Returns whether this selection is currently shown to the given player.
     *
     * @param Player $player Player to check.
     * @return bool True if sendTo() was called for this player and restoreFrom()/forget() has not been called since.
     */
    public function hasViewer(Player $player) : bool{
        return isset($this->entries[$player]);
    }

    /**
     * Restores this selection visualization for all current viewers.
     *
     * Equivalent to calling restoreFrom() for each player that received sendTo();
     * each player's structure block is replaced with the original block and their
     * Y slot is released.
     *
     * Side effects: same as restoreFrom() for every registered viewer.
     */
    public function restoreFromAll() : void{
        $viewers = [];
        foreach($this->entries as $player => $_){
            $viewers[] = $player;
        }
        foreach($viewers as $player){
            $this->restoreFrom($player);
        }
    }

    /**
     * Allocates the lowest free Y for the given player/world/(x, z) column and marks it as used.
     *
     * @throws RuntimeException If the whole column (World::Y_MIN until World::Y_MAX) is used up. A silent
     *                          fallback would collide with another selection's slot, so this fails loudly.
     */
    private static function allocateY(Player $player, World $world, int $x, int $z) : int{
        self::$usedSlots ??= new WeakMap();
        $perWorld = self::$usedSlots[$player] ??= new WeakMap();
        $used = $perWorld[$world] ?? [];
        for($y = World::Y_MIN; $y < World::Y_MAX; ++$y){
            $key = self::slotKey($x, $y, $z);
            if(!isset($used[$key])){
                $used[$key] = true;
                $perWorld[$world] = $used;
                return $y;
            }
        }
        throw new RuntimeException("No free structure block slot left in column ($x, $z)");
    }

    /**
     * Plain string key instead of morton3d_encode(): no native extension dependency and safe for negative coordinates.
     */
    private static function slotKey(int $x, int $y, int $z) : string{
        return "$x:$y:$z";
    }

    /**
     * @param WeakReference<World> $worldRef
     */
    private static function releaseY(Player $player, WeakReference $worldRef, Vector3 $pos) : void{
        $world = $worldRef->get();
        if($world === null || self::$usedSlots === null || !isset(self::$usedSlots[$player][$world])){
            return;
        }
        $perWorld = self::$usedSlots[$player];
        $used = $perWorld[$world];
        unset($used[self::slotKey((int) $pos->x, (int) $pos->y, (int) $pos->z)]);
        if($used === []){
            unset($perWorld[$world]);
        }else{
            $perWorld[$world] = $used;
        }
    }
}
