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
use pocketmine\nbt\tag\CompoundTag;

use function max;
use function min;

/**
 * Holds data for a single structure block used to visualize a selection:
 * network ID, tile NBT (bounding box), position, offset and size.
 * Used per-player by Selection to send/reuse one block per viewer.
 */
final class SelectionBlockData{

    /** Network ID of the structure block used for visualization. */
    public readonly int $networkId;

    /** Tile NBT containing bounding box configuration (showBoundingBox, offsets, size). */
    public readonly CompoundTag $tileNbt;

    /** World position where the structure block is placed (updated by Selection). */
    public Vector3 $pos;

    private Vector3 $offset;
    private Vector3 $size;

    /**
     * Builds a new block data with shared network ID and default NBT.
     * Side effect: first call may trigger block type registration via SelectionVisualizeUtils.
     */
    public function __construct(){
        $this->networkId = SelectionVisualizeUtils::getBlockNetworkId();

        $this->tileNbt = new CompoundTag();
        $this->tileNbt->setByte("showBoundingBox", 1);

        $this->pos = Vector3::zero();
        $this->setOffset(Vector3::zero());
        $this->setSize(new Vector3(1, 1, 1));
    }

    /**
     * Returns a copy of the structure offset (NBT x/y/zStructureOffset).
     * No side effects.
     */
    public function getOffset() : Vector3{
        return clone $this->offset;
    }

    /**
     * Sets the structure offset and writes it to tile NBT (x/y/zStructureOffset).
     *
     * @param Vector3 $offset Offset applied to the bounding box.
     * Side effect: mutates $this->tileNbt.
     */
    public function setOffset(Vector3 $offset) : self{
        $this->offset = $offset;
        $this->tileNbt
            ->setInt("xStructureOffset", (int) $this->offset->x)
            ->setInt("yStructureOffset", (int) $this->offset->y)
            ->setInt("zStructureOffset", (int) $this->offset->z);
        return $this;
    }

    /**
     * Returns a copy of the structure size (NBT x/y/zStructureSize).
     * No side effects.
     */
    public function getSize() : Vector3{
        return clone $this->size;
    }

    /**
     * Sets the structure size and writes it to tile NBT (x/y/zStructureSize).
     * Each axis is at least 1.
     *
     * @param Vector3 $size Width, height and depth of the bounding box.
     * Side effect: mutates $this->tileNbt.
     */
    public function setSize(Vector3 $size) : self{
        $this->size = new Vector3(
            max(1, (int) $size->x),
            max(1, (int) $size->y),
            max(1, (int) $size->z)
        );
        $this->tileNbt
            ->setInt("xStructureSize", (int) $this->size->x)
            ->setInt("yStructureSize", (int) $this->size->y)
            ->setInt("zStructureSize", (int) $this->size->z);
        return $this;
    }
}
