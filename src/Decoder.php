<?php

namespace Lianhua\BadApple;

class Decoder
{
    /**
     * The video file location (unused at the moment)
     * @var string
     */
    private $videoFile;

    private $videoW;

    private $ips;

    private $videoPointer;

    private $map;

    private $drawCmd;

    private $lastFrame;

    private function processHeader($header)
    {
        $this->videoW = $header["w"];
        $this->ips = $header["ips"];
    }

    private function getUTime()
    {
        return round(microtime(true) * 1000000);
    }

    private function syncFrame()
    {
        while (($this->getUTime() - $this->lastFrame) < floor(1000000 / $this->ips)) {
            usleep(50);
        }

        $this->lastFrame += floor(1000000 / $this->ips);
    }

    private function printMap()
    {
        $this->syncFrame();

        echo($this->drawCmd);
        file_put_contents("debug", $this->drawCmd . "\n", FILE_APPEND);
    }

    private function printKFrame($kframe)
    {
        $this->drawCmd = "\e[H";
        $this->drawCmd .= implode("\n", str_split($kframe["map"], $this->videoW * 3));
        $this->drawCmd .= "\e[999,0H";

        $this->map = $kframe["map"];

        $this->printMap();
    }

    private function printIFrame($iframe)
    {
        //$this->drawCmd = "";
        foreach ($iframe["diff"] as $pos => $diff) {
            $y = floor($pos / ($this->videoW * 3));

            $x = $pos;
            $x -= $y * ($this->videoW * 3);
            $x = floor($x / 3);

            $this->map[intval($pos)] = chr(ord($this->map[intval($pos)]) + $diff);

            $charStart = floor($pos / 3) * 3;
            $char = $this->map[$charStart] . $this->map[$charStart + 1] . $this->map[$charStart + 2];

            //$this->drawCmd .= "\e[" . $y . ";" . $x . "H" . $char;
        }

        $this->drawCmd = "\e[H";
        $this->drawCmd .= implode("\n", str_split($this->map, $this->videoW * 3));
        $this->drawCmd .= "\e[999,0H";
        $this->printMap();
    }

    public function play()
    {
        echo("\e[1;1H\e[2J");

        $this->videoPointer = fopen($this->videoFile, "r");

        while ($input = fgets($this->videoPointer)) {
            $frame = json_decode($input, true);

            switch ($frame["type"]) {
                case "header":
                    $this->processHeader($frame);
                    break;

                case "kframe":
                    $this->printKFrame($frame);
                    break;

                case "iframe":
                    $this->printIFrame($frame);
                    break;
            }
        }
    }

    public function __construct()
    {
        $this->videoFile = "/Users/camille/Downloads/BadApple.jvf";
        $this->lastFrame = $this->getUTime();
    }
}
