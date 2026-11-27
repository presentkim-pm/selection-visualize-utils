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
use pocketmine\world\Position;

/**
 * Immutable data holder for a single preview cell.
 *
 * - $position: world position where the preview is shown.
 * - $block: block that will be faked on the NORMAL data layer (install candidate).
 * - $liquidBlock: optional block faked on the LIQUID data layer as an overlay.
 *   If null, no liquid layer overlay is sent (only the NORMAL layer is faked).
 */
final class PreviewEntry{
    public function __construct(
        public Position $position,
        public Block $block,
        public ?Block $liquidBlock = null,
    ){}
}

