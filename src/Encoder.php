<?php

namespace Lianhua\BadApple;

class Encorder
{
    /**
     * The work directory
     * @var string
     */
    private $workDir;

    /**
     * The video file location (unused at the moment)
     * @var string
     */
    private $videoFile;

    /**
     * The n-1th frame
     * @var string
     */
    private $lastFrame;

    /**
     * The nth frame
     * @var string
     */
    private $currentFrame;

    /**
     * The frame difference
     * @var array
     */
    private $deltaFrame;

    /**
     * The output file location
     * @var string
     */
    private $videoOutput;

    private $videoOutputHandler;

    private $videoH;

    private $lineSpacing;

    private $videoW;

    private $columnSpacing;

    private $preview;

    private function extractFrameBlock($frameFile, $h, $w)
    {
        $dot = [];
        $i = 0;

        $x = $w * (2 + $this->columnSpacing);
        $y = $h * (4 + $this->lineSpacing);

        $dot[1] = imagecolorat($frameFile, $x, $y);
        $dot[2] = imagecolorat($frameFile, $x, $y + 1);
        $dot[3] = imagecolorat($frameFile, $x, $y + 2);
        $dot[4] = imagecolorat($frameFile, $x + 1, $y);
        $dot[5] = imagecolorat($frameFile, $x + 1, $y + 1);
        $dot[6] = imagecolorat($frameFile, $x + 1, $y + 2);
        $dot[7] = imagecolorat($frameFile, $x, $y + 3);
        $dot[8] = imagecolorat($frameFile, $x + 1, $y + 3);

        for ($i = 1; ($i <= 8); $i++) {
            $r = ($dot[$i] >> 16) & 0xFF;
            $g = ($dot[$i] >> 8) & 0xFF;
            $b = $dot[$i] & 0xFF;

            if (($r + $g + $b) >= 384) {
                $dot[$i] = 1;
            } else {
                $dot[$i] = 0;
            }
        }

        $char = 0x2800;
        $char += $dot[1];
        $char += (0x2 * $dot[2]);
        $char += (0x4 * $dot[3]);
        $char += (0x8 * $dot[4]);
        $char += (0x10 * $dot[5]);
        $char += (0x20 * $dot[6]);
        $char += (0x40 * $dot[7]);
        $char += (0x80 * $dot[8]);

        $block = mb_convert_encoding('&#' . intval($char) . ';', 'UTF-8', 'HTML-ENTITIES');
        $this->currentFrame .= $block;

        if ($this->preview) {
            echo($block);
        }
    }

    private function extract($frameFile)
    {
        $i = 0;
        $j = 0;
        $image = imagecreatefrompng($frameFile);

        $this->lastFrame = $this->currentFrame;
        $this->currentFrame = "";

        for ($i = 0; ($i < $this->videoH); $i++) {
            if ($this->preview) {
                echo("\n");
            }

            for ($j = 0; ($j < $this->videoW); $j++) {
                $this->extractFrameBlock($image, $i, $j);
            }
        }

        if ($this->preview) {
            echo("\n");
        }
    }

    private function createDeltaFrame()
    {
        $i = 0;
        $size = strlen($this->currentFrame);
        $this->deltaFrame = [];

        for ($i = 0; ($i < $size); $i ++) {
            if ($this->currentFrame[$i] != ($this->lastFrame[$i] ?? "")) {
                $this->deltaFrame[$i] = ord($this->currentFrame[$i]) - ord($this->lastFrame[$i]);
            }
        }
    }

    private function writeFileHeader()
    {
        $res = ["type" => "header", "h" => $this->videoH, "w" => $this->videoW, "ips" => 25];
        fwrite($this->videoOutputHandler, json_encode($res, JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function writeKFrame()
    {
        $res = ["type" => "kframe", "map" => $this->currentFrame];
        fwrite($this->videoOutputHandler, json_encode($res, JSON_UNESCAPED_UNICODE) . "\n");
        echo("KFrame written\n");
    }

    private function writeIFrame()
    {
        $res = ["type" => "iframe", "diff" => $this->deltaFrame];
        fwrite($this->videoOutputHandler, json_encode($res, JSON_UNESCAPED_UNICODE) . "\n");
        echo("IFrame with " . count($this->deltaFrame) . " differences written\n");
    }

    private function writeFrame()
    {
        if (count($this->deltaFrame) > (strlen($this->currentFrame) / 10)) {
            $this->writeKFrame();
        } else {
            $this->writeIFrame();
        }
    }

    private function writeFileFooter()
    {
        $res = ["type" => "footer"];
        fwrite($this->videoOutputHandler, json_encode($res, JSON_UNESCAPED_UNICODE) . "\n");
    }

    public function encode()
    {
        $frames = glob($this->workDir . DIRECTORY_SEPARATOR . "*");
        $totalFrames = count($frames);
        $i = 0;

        $this->videoOutputHandler = fopen($this->videoOutput, "w+");

        if ($totalFrames < 1) {
            echo("Nothing to do\n");
        }

        $size = getimagesize($frames[0]);

        if (empty($size)) {
            echo("Error! The work dir contains non image files\n");
        }

        $this->videoW = ceil($size[0] / (2 + $this->columnSpacing));
        $this->videoH = ceil($size[1] / (4 + $this->lineSpacing));

        $this->writeFileHeader();

        for ($i = 0; ($i < $totalFrames); $i++) {
            echo("Encoding frame $i\n");
            $this->extract($frames[$i]);
            $this->createDeltaFrame();
            $this->writeFrame();
        }

        $this->writeFileFooter();

        fclose($this->videoOutputHandler);
    }

    public function __construct($params)
    {
        if (array_key_exists("workDir", $params)) {
            $this->workDir = $params["workDir"];
        } else {
            $this->workDir = ".";
        }

        if (array_key_exists("videoFile", $params)) {
            $this->videoFile = $params["videoFile"];
        } else {
            $this->videoFile = "";
        }

        if (array_key_exists("videoOutput", $params)) {
            $this->videoOutput = $params["videoOutput"];
        } else {
            $this->videoOutput = "out.jvf";
        }

        $this->lastFrame = "";
        $this->lineSpacing = 2;
        $this->columnSpacing = 1;
        $this->preview = true;
    }
}
