<?php

/* Class to read l2j geodata files
 *
 * Version: 1.1
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
    private $blockI = 0;
    public $blockX = 0;
    public $blockY = 0;

    public function __construct($path)
    {
        $this->open($path);
    }

    public function readBlock()
    {
        if ($this->blockI >= 65536)
        {
            $this->close();
        }
        if ($this->f === null)
        {
            return false;
        }

        $this->blockX = (int)($this->blockI / 256);
        $this->blockY = $this->blockI % 256;
        $this->blockI += 1;

        $blockType = $this->readU8();
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
        $int = $this->readU16();
        return [self::int2z($int), 0xF];
    }

    private function readComplexBlock()
    {
        $blockData = [];
        for ($cellI = 0; $cellI < 64; $cellI += 1)
        {
            $int = $this->readU16();
            $blockData[] = [self::int2z($int)/2, self::int2nswe($int)];
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
                $levels = $this->readU8();
                $levelData = [];
                for ($l = 0; $l < $levels; $l += 1)
                {
                    $int = $this->readU16();
                    $levelData[] = [self::int2z($int)/2, self::int2nswe($int)];
                }
                $blockData[] = $levelData;
            }
        }
        return $blockData;
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

    //private function readSignedInt16()
    //{
    //    $int = ord(fgetc($this->f)) + (ord(fgetc($this->f)) << 8);
    //    //return $int - 32768;
    //    return ($int >= 32768) ? $int - 65536 : $int;
    //}

    private function readU16()
    {
        return ord(fgetc($this->f)) + (ord(fgetc($this->f)) << 8);
    }

    private function readU8()
    {
        return ord(fgetc($this->f));
    }







    private static function int2z($int)
    {
        $z = $int & 0xFFF0;
        if ($z >= 32768) $z -= 65536;
        return $z;
    }

    private static function int2nswe($int)
    {
        return $int & 0x000F;
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
