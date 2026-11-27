<?php

declare(strict_types=1);

namespace kim\present\utils\selectionvisualize\tests;

use pocketmine\player\Player;

/**
 * Mockable Player whose destructor does nothing: the real one touches properties that are never
 * initialized because mocks skip the constructor.
 */
class TestPlayer extends Player{
    public function __destruct(){
    }
}
