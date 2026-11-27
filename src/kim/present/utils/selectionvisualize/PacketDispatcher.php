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

use Closure;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\player\Player;

/**
 * Single exit point for every packet this library sends.
 *
 * @internal Only exists as a seam so that tests can capture packets without a real network session.
 */
final class PacketDispatcher{

    /**
     * @var (Closure(Player, ClientboundPacket[]) : void)|null
     */
    private static ?Closure $handler = null;

    private function __construct(){
        // NOOP
    }

    /**
     * Replaces the sending logic (pass null to restore the default).
     *
     * @param (Closure(Player, ClientboundPacket[]) : void)|null $handler
     *
     * @internal
     */
    public static function setHandler(?Closure $handler) : void{
        self::$handler = $handler;
    }

    /**
     * @param ClientboundPacket[] $packets
     */
    public static function send(Player $player, array $packets) : void{
        if($packets === []){
            return;
        }
        if(self::$handler !== null){
            (self::$handler)($player, $packets);
            return;
        }
        NetworkBroadcastUtils::broadcastPackets([$player], $packets);
    }
}
