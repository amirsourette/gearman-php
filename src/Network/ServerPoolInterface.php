<?php


namespace Kicken\Gearman\Network;


use Kicken\Gearman\Protocol\Packet;

interface ServerPoolInterface {
    /**
     * ServerPoolInterface constructor.
     *
     * @param ConnectionInterface|ConnectionInterface[] $serverList
     */
    public function __construct($serverList);

    public function connect(?int $timeout);

    /**
     * Set a callback function to handle incoming packets.  Handler signature is
     *
     * void fn(Packet $packet, ConnectionInterface $connection)
     *
     * @param callable $fn
     *
     * @return void
     */
    public function setPacketHandler(callable $fn);

    /**
     * Send a packet to
     *
     * @param Packet $packet
     *
     * @return mixed
     */
    public function writePacket(Packet $packet);

    /**
     * Monitor server connections for new data.
     *
     * @param ?int $timeout
     *
     * @return void
     */
    public function monitor(?int $timeout);
}
