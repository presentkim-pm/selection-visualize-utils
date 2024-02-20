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

final class SelectionBlockData{
	public readonly int $networkId;

	public readonly CompoundTag $tileNbt;

	public Vector3 $pos;
	private Vector3 $offset;
	private Vector3 $size;

	public function __construct(){
		$this->networkId = SelectionVisualizeUtils::getBlockNetworkId();

		$this->tileNbt = new CompoundTag();
		$this->tileNbt->setByte("showBoundingBox", 1);

		$this->pos = Vector3::zero();
		$this->setOffset(Vector3::zero());
		$this->setSize(new Vector3(1, 1, 1));
	}

	public function getOffset() : Vector3{
		return clone $this->offset;
	}

	public function setOffset(Vector3 $offset) : self{
		$this->offset = $offset;
		$this->tileNbt
			->setInt("xStructureOffset", $this->offset->x)
			->setInt("yStructureOffset", $this->offset->y)
			->setInt("zStructureOffset", $this->offset->z);
		return $this;
	}

	public function getSize() : Vector3{
		return clone $this->size;
	}

	public function setSize(Vector3 $size) : self{
		$this->size = $size;
		$this->tileNbt
			->setInt("xStructureSize", $this->size->x)
			->setInt("yStructureSize", $this->size->y)
			->setInt("zStructureSize", $this->size->z);
		return $this;
	}
}
