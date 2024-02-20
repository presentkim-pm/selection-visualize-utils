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

use kim\present\utils\selectionvisualize\block\StructureBlock;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\world\World;

use function max;
use function min;
use function morton3d_encode;
use function spl_object_id;

/** @phpstan-type PlayerObjectId int */
final class Selection{
	/**
	 * @var int[]
	 * @phpstan-var array<int, true>
	 */
	private static array $usedYMap = [];

	/**
	 * @var StructureBlock[] $overrided
	 * @phpstan-var array<PlayerObjectId, StructureBlock>
	 */
	private array $overrided = [];

	/**
	 * @var Player[]
	 * @phpstan-var array<PlayerObjectId, Player>
	 */
	private array $viewers = [];

	public function __construct(
		public Vector3 $pos1,
		public Vector3 $pos2
	){}

	public function sendTo(Player $player) : void{
		$id = spl_object_id($player);
		if(isset($this->overrided[$id])){
			$this->restoreFrom($player);
		}

		$min = new Vector3(
			(int) min($this->pos1->x, $this->pos2->x),
			(int) min($this->pos1->y, $this->pos2->y),
			(int) min($this->pos1->z, $this->pos2->z)
		);
		$max = new Vector3(
			(int) max($this->pos1->x, $this->pos2->x),
			(int) max($this->pos1->y, $this->pos2->y),
			(int) max($this->pos1->z, $this->pos2->z)
		);

		$minY = self::getMinY($id, $min->x, $min->z);
		$block = SelectionVisualizeUtils::getStructureBlock();
		$block->position($player->getWorld(), $min->x, $minY, $min->z);
		$block->setOffset(new Vector3(0, $min->y - $minY, 0));
		$block->setSize($max->subtractVector($min)->add(1, 1, 1));

		$blockPos = BlockPosition::fromVector3($block->getPosition());
		$player->getNetworkSession()->sendDataPacket(UpdateBlockPacket::create(
			$blockPos,
			TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($block->getStateId()),
			UpdateBlockPacket::FLAG_NETWORK,
			UpdateBlockPacket::DATA_LAYER_NORMAL
		));
		$player->getNetworkSession()->sendDataPacket(BlockActorDataPacket::create(
			$blockPos,
			new CacheableNbt($block->getTileData())
		));

		$this->overrided[$id] = $block;
		$this->viewers[$id] = $player;
	}

	public function restoreFrom(Player $player) : void{
		$id = spl_object_id($player);
		if(!isset($this->overrided[$id])){
			return;
		}

		$pos = $this->overrided[$id]->getPosition();
		NetworkBroadcastUtils::broadcastPackets([$player], $player->getWorld()->createBlockUpdatePackets([$pos]));

		self::releaseY($id, $pos->x, $pos->y, $pos->z);
		unset($this->overrided[$id], $this->viewers[$id]);
	}

	public function restoreFromAll() : void{
		foreach($this->viewers as $player){
			$this->restoreFrom($player);
		}
	}

	private static function getMinY(int $objectId, int $x, int $z) : int{
		for($y = World::Y_MIN; $y < World::Y_MAX; ++$y){
			$key = morton3d_encode($x, $y, $z);
			if(!isset(self::$usedYMap[$objectId][$key])){
				self::$usedYMap[$objectId][$key] = true;
				return $y;
			}
		}
		return World::Y_MAX;
	}

	private static function releaseY(int $objectId, int $x, int $y, int $z) : void{
		unset(self::$usedYMap[$objectId][morton3d_encode($x, $y, $z)]);
		if(empty(self::$usedYMap[$objectId])){
			unset(self::$usedYMap[$objectId]);
		}
	}
}
