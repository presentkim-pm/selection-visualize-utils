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

use kim\present\utils\selectionvisualize\task\RegisterStructureBlockTask;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Opaque;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\block\BlockStateStringValues;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\Server;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

final class SelectionVisualizeUtils{
	private static int $structureBlockNetworkId;

	private function __construct(){
		// NOOP
	}

	/** @internal */
	public static function registerStructureBlock() : void{
		if(isset(self::$structureBlockNetworkId)){
			return;
		}
		$block = new Opaque(
			new BlockIdentifier(BlockTypeIds::newId()),
			"Structure Block",
			new BlockTypeInfo(BlockBreakInfo::instant())
		);

		GlobalBlockStateHandlers::getSerializer()->map(
			$block,
			fn() => BlockStateWriter::create(BlockTypeNames::STRUCTURE_BLOCK)->writeString(
				BlockStateNames::STRUCTURE_BLOCK_TYPE,
				BlockStateStringValues::STRUCTURE_BLOCK_TYPE_DATA
			)
		);
		RuntimeBlockStateRegistry::getInstance()->register($block);

		self::$structureBlockNetworkId = TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId(
			$block->getStateId()
		);
	}

	/** @internal */
	public static function getStructureBlockNetworkId() : int{
		if(!isset(self::$structureBlockNetworkId)){
			self::registerStructureBlock();
			$asyncPool = Server::getInstance()->getAsyncPool();
			$asyncPool->addWorkerStartHook(static function(int $workerId) use ($asyncPool) : void{
				$asyncPool->submitTaskToWorker(new RegisterStructureBlockTask(), $workerId);
			});
		}
		return self::$structureBlockNetworkId;
	}
}
