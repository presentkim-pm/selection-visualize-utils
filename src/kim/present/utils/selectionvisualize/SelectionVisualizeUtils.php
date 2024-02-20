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

use pocketmine\block\BlockBreakInfo as BreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockTypeIds as Ids;
use pocketmine\block\BlockTypeInfo as TypeInfo;
use pocketmine\block\Opaque;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\block\BlockStateStringValues;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

final class SelectionVisualizeUtils{
	private static int $blockNetworkId;

	private function __construct(){
		// NOOP
	}

	/** @internal */
	public static function getBlockNetworkId() : int{
		if(isset(self::$blockNetworkId)){
			return self::$blockNetworkId;
		}

		$block = new Opaque(new BID(Ids::newId()), "Structure Block", new TypeInfo(BreakInfo::instant()));
		GlobalBlockStateHandlers::getSerializer()->map(
			$block,
			fn() => BlockStateWriter::create(BlockTypeNames::STRUCTURE_BLOCK)->writeString(
				BlockStateNames::STRUCTURE_BLOCK_TYPE,
				BlockStateStringValues::STRUCTURE_BLOCK_TYPE_DATA
			)
		);
		RuntimeBlockStateRegistry::getInstance()->register($block);

		$blockTranslator = TypeConverter::getInstance()->getBlockTranslator();
		return self::$blockNetworkId = $blockTranslator->internalIdToNetworkId($block->getStateId());
	}
}
