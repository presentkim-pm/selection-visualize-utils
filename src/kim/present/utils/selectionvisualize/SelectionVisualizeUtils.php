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
use kim\present\utils\selectionvisualize\task\RegisterStructureBlockTask;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\block\BlockStateStringValues;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\Server;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

final class SelectionVisualizeUtils{
	private static StructureBlock $block;

	private function __construct(){
		// NOOP
	}

	public static function registerStructureBlock() : void{
		if(isset(self::$block)){
			return;
		}
		self::$block = new StructureBlock();
		GlobalBlockStateHandlers::getSerializer()->map(self::$block, function(Block $block) : BlockStateWriter{
			if(!($block instanceof StructureBlock)){
				return BlockStateWriter::create(BlockTypeNames::STRUCTURE_BLOCK);
			}
			return BlockStateWriter::create(BlockTypeNames::STRUCTURE_BLOCK)->writeString(
				BlockStateNames::STRUCTURE_BLOCK_TYPE,
				BlockStateStringValues::STRUCTURE_BLOCK_TYPE_DATA
			);
		});

		RuntimeBlockStateRegistry::getInstance()->register(self::$block);
	}

	public static function getStructureBlock() : StructureBlock{
		if(!isset(self::$block)){
			self::registerStructureBlock();
			$asyncPool = Server::getInstance()->getAsyncPool();
			$asyncPool->addWorkerStartHook(static function(int $workerId) use ($asyncPool) : void{
				$asyncPool->submitTaskToWorker(new RegisterStructureBlockTask(), $workerId);
			});
		}
		return clone self::$block;
	}
}
