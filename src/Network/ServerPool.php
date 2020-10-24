<?php


namespace Kicken\Gearman\Network;


use Kicken\Gearman\Protocol\Packet;

class ServerPool implements ServerPoolInterface {
    /** @var ConnectionInterface[] */
    private $serverList;
    private $packetHandler;

    public function __construct($serverList){
        $this->serverList = $serverList;
    }

    public function setPacketHandler(callable $fn){
        $this->packetHandler = $fn;
    }

    public function connect(?int $timeout = -1){
        if ($timeout < 0){
            $timeout = ini_get('default_socket_timeout');
        }

        $streamList = [];
        $callbackList = [];
        foreach ($this->serverList as $item){
            $callbackList[] = $item->beginConnect();
            $streamList[] = $item->getStreamResource();
        }

        $start = time();
        do {
            $wait = null;
            if ($timeout !== null){
                $wait = $timeout - (time() - $start);
            }
            $r = [];
            $w = $streamList;
            $o = $streamList;
            if (stream_select($r, $w, $o, $wait, null)){
                foreach ($streamList as $key => $stream){
                    $inW = in_array($stream, $w, true);
                    if ($inW || in_array($stream, $o, true)){
                        call_user_func($callbackList[$key], $inW);
                        unset($streamList[$key], $callbackList[$key]);
                    }
                }
            }
        } while ($streamList && ($timeout === null || time() - $start < $timeout));

        foreach ($streamList as $key => $stream){
            call_user_func($callbackList[$key], false);
        }
    }

    public function writePacket(Packet $packet){
        foreach ($this->serverList as $item){
            $item->writePacket($packet);
        }
    }

    public function monitor(?int $timeout){
        $r = $w = $o = [];
        foreach ($this->serverList as $item){
            $stream = $item->getStreamResource();
            if ($stream){
                $r[] = $stream;
            }
        }

        if (stream_select($r, $w, $o, $timeout, null) > 0){
            foreach ($this->serverList as $item){
                $stream = $item->getStreamResource();
                if ($stream && in_array($stream, $r, true)){
                    $this->dispatchPackets($item);
                }
            }
        }
    }

    private function dispatchPackets(ConnectionInterface $connection){
        while ($packet = $connection->readPacket()){
            call_user_func($this->packetHandler, $packet, $connection);
        }
    }
}
