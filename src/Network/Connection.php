<?php


namespace Kicken\Gearman\Network;


use Kicken\Gearman\Exception\NotConnectedException;
use Kicken\Gearman\Protocol\Packet;

class Connection implements ConnectionInterface {
    private $url;
    private $stream;
    private $timeout;
    private $readBuffer = '';

    public function __construct(string $url, ?int $timeout = -1){
        $this->url = $url;
        $this->timeout = $timeout;
        if ($timeout < 0){
            $this->timeout = ini_get('default_socket_timeout');
        }
    }

    public function __destruct(){
        if ($this->stream){
            fclose($this->stream);
        }
    }

    public function beginConnect() : callable{
        $this->stream = stream_socket_client($this->url, $errNumber, $errString, null, STREAM_CLIENT_ASYNC_CONNECT);

        return function(bool $success){
            if (!$success){
                fclose($this->stream);
                $this->stream = null;
            } else {
                stream_set_blocking($this->stream, false);
                stream_set_read_buffer($this->stream, 0);
                stream_set_write_buffer($this->stream, 0);
            }
        };
    }

    public function getStreamResource(){
        return $this->stream;
    }

    public function writePacket(Packet $packet){
        $data = $packet->__toString();
        if ($this->timeout === null){
            do {
                $data = $this->writeData($data);
            } while ($data);
        } else {
            $start = time();
            do {
                $r = $o = [];
                $w = [$this->stream];
                $timeout = $this->timeout - (time() - $start);
                if (stream_select($r, $w, $o, $timeout, 0) === 1){
                    $data = $this->writeData($data);
                }
            } while ($data && (time() - $start) < $this->timeout);
        }
    }

    private function writeData(string $data) : string{
        $written = fwrite($this->stream, $data);
        if ($written === strlen($data)){
            $data = '';
        } else {
            $data = substr($data, $written);
        }

        return $data;
    }

    public function readPacket() : Packet{
        if ($this->timeout === null){
            do {
                $this->readData(null);
                $packet = Packet::fromString($this->readBuffer);
            } while (!$packet);
        } else {
            $start = time();
            do {
                $timeout = $this->timeout - (time() - $start);
                $this->readData($timeout);
                $packet = Packet::fromString($this->readBuffer);
            } while (!$packet && (time() - $start) < $this->timeout);
        }

        if ($packet){
            $this->readBuffer = '';
        }

        return $packet;
    }

    private function readData($timeout){
        $r = [$this->stream];
        $w = $o = [];
        if (stream_select($r, $w, $o, $timeout, 0) === 1){
            $data = fread($this->stream, 8192);
            if ($data !== false && $data !== ''){
                $this->readBuffer .= $data;
            }
        }
    }
}
