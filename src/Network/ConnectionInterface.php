<?php


namespace Kicken\Gearman\Network;


use Kicken\Gearman\Protocol\Packet;

interface ConnectionInterface {
    public function __construct(string $url, ?int $timeout = null);

    public function beginConnect() : callable;

    public function getStreamResource();

    public function writePacket(Packet $packet);

    public function readPacket() : Packet;
}
