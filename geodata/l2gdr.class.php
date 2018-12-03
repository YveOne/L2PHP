<?php

/* Class to read l2j geodata files
 *
 * Version: 1.0
 * Author: Yvonne P. (contact[at]yveone[dot]de)
 *
**/

namespace L2PHP;

define('L2GDR_FLAT',    0);
define('L2GDR_COMPLEX', 1);
define('L2GDR_MULTI',   2);

class L2GeoDataReader
{
    private $f = null;
    private $blockI = -1;
    public $blockX = -1;
    public $blockY = -1;

    public function __construct($path)
    {
        $this->open($path);
    }

    public function readBlock()
    {
        $this->blockI += 1;
        if ($this->blockI === 65536)
        {
            $this->close();
        }
        if ($this->f === null)
        {
            return false;
        }

        $this->blockX = (int)($this->blockI / 256);
        $this->blockY = $this->blockI % 256;

        $blockType = $this->readUnsignedInt8();
        switch ($blockType)
        {
            case L2GDR_FLAT:
                return [$blockType, $this->readFlatBlock()];
                break;
            case L2GDR_COMPLEX:
                return [$blockType, $this->readComplexBlock()];
                break;
            case L2GDR_MULTI:
                return [$blockType, $this->readMultiBlock()];
                break;
        }
        die('unknown block type at '.$this->blockI);
    }

    private function readFlatBlock()
    {
        return $this->readCell();
    }

    private function readComplexBlock()
    {
        $blockData = [];
        for ($cellI = 0; $cellI < 64; $cellI += 1)
        {
            $blockData[] = $this->readCell(2);
        }
        return $blockData;
    }

    private function readMultiBlock()
    {
        $blockData = [];
        for ($cellX = 0; $cellX < 8; $cellX += 1)
        {
            for ($cellY = 0; $cellY < 8; $cellY += 1)
            {
                $levels = $this->readUnsignedInt8();
                $levelData = [];
                for ($l = 0; $l < $levels; $l += 1)
                {
                    $levelData[] = $this->readCell(2);
                }
                $blockData[] = $levelData;
            }
        }
        return $blockData;
    }

    private function readCell($div=1)
    {
        $int = $this->readSignedInt16();
        $nswe = $int & 0xF;
        $coordZ = $int >> 4 << 4;
        return [$coordZ/$div, $nswe];
    }

    public function open($path)
    {
        if ($this->f === null)
        {
            $this->f = @fopen($path, 'r');
            if (!$this->f)
            {
                die("<b>Error:</b> L2GeoDataReader - File '<b>{$path}</b>' not found.");
            }
        }
    }

    public function close()
    {
        if ($this->f !== null)
        {
            fclose($this->f);
            $this->f = null;
        }
    }

    private function readSignedInt16()
    {
        $int = ord(fgetc($this->f)) + (ord(fgetc($this->f)) << 8);
        return ($int >= 32768) ? $int - 65536 : $int;
    }

    private function readUnsignedInt8()
    {
        return ord(fgetc($this->f));
    }

    public static function nswe($nswe)
    {
        $bits = str_pad(decbin($nswe), 4, '0', STR_PAD_LEFT);
        return [
            'n' => $bits{0} === '1',
            's' => $bits{1} === '1',
            'w' => $bits{2} === '1',
            'e' => $bits{3} === '1',
        ];
    }

}
