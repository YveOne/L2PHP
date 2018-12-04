<?php

/* Class to view l2j geodata files
 *
 * Version: 1.1
 * Author: Yvonne P. (contact[at]yveone[dot]de)
 *
**/

namespace L2PHP;

define('L2GDI_IMG_SIZE', 2048);

if (!defined('L2GDI_COLOR_MIN'))    define('L2GDI_COLOR_MIN', '000000');
if (!defined('L2GDI_COLOR_MAX'))    define('L2GDI_COLOR_MAX', 'FFFFFF');
if (!defined('L2GDI_BLOCK_MIN'))    define('L2GDI_BLOCK_MIN', '100000');
if (!defined('L2GDI_BLOCK_MAX'))    define('L2GDI_BLOCK_MAX', 'FF0000');

class L2GeoDataImage
{

    private $raw;
    private $out;

    private $cellOffsetX;
    private $cellOffsetY;

    private $zMin = 65536;
    private $zMax = -0;

    public function __construct(&$gdr, $level=0)
    {
        $this->raw = imagecreatetruecolor(L2GDI_IMG_SIZE, L2GDI_IMG_SIZE);
        $this->out = imagecreatetruecolor(L2GDI_IMG_SIZE, L2GDI_IMG_SIZE);
        imagefill($this->raw, 0, 0, 15);
        imagealphablending($this->out, false);
        imagesavealpha($this->out, true);

        while (($block = $gdr->readBlock()) !== false)
        {

            $this->cellOffsetX = $gdr->blockX * 8;
            $this->cellOffsetY = $gdr->blockY * 8;

            $blockType = $block[0];
            $blockData = $block[1];

            switch ($blockType)
            {

                case L2GDR_FLAT:
                    if (!$level)
                    {
                        for ($cellI = 0; $cellI < 64; $cellI += 1)
                        {
                            $this->drawRawCell($cellI, $blockData);
                        }
                    }
                    break;

                case L2GDR_COMPLEX:
                    if (!$level)
                    {
                        foreach ($blockData as $cellI => $cellData)
                        {
                            $this->drawRawCell($cellI, $cellData);
                        }
                    }
                    break;

                case L2GDR_MULTI:
                    foreach ($blockData as $cellI => $cellData)
                    {
                        if (isset($cellData[$level]))
                        {
                            $this->drawRawCell($cellI, $cellData[$level]);
                        }
                    }
                    break;
            }
        }
        $gdr->close();
    }

    private function drawRawCell($cellI, $cellData)
    {
        $px = $this->cellOffsetX + (int)($cellI/8);
        $py = $this->cellOffsetY +       $cellI%8;
        $z = $cellData[0]+32768;
        if ($z < $this->zMin) $this->zMin = $z;
        if ($z > $this->zMax) $this->zMax = $z;
        imagesetpixel($this->raw, $px, $py, ($z&0xFFF0)+$cellData[1]);
    }
/*
    private function drawRawCell($cellI, $cellData)
    {
        $px = $this->cellOffsetX + (int)($cellI/8);
        $py = $this->cellOffsetY +       $cellI%8;
        //$z = $cellData[0];
        //if ($z < $this->zMin) $this->zMin = $z;
        //if ($z > $this->zMax) $this->zMax = $z;
        //imagesetpixel($this->raw, $px, $py, (($z+32768)<<8)+$cellData[1]);
        $z = $cellData[0];
        if ($z < $this->zMin) $this->zMin = $z;
        if ($z > $this->zMax) $this->zMax = $z;
        $int = (($cellData[0]+32768)&0xFFF0)+$cellData[1];
        imagesetpixel($this->raw, $px, $py, $int);
    }
*/
    private static function parseColorInt($int)
    {
        return [
            ($int >> 24) & 0xFF,
            ($int >> 16) & 0xFF,
            ($int >>  8) & 0xFF,
            ($int      ) & 0xFF
        ];
    }






    public function heightmap($showNSWE=false)
    {

        list($aMin, $rMin, $gMin, $bMin) = self::parseColorInt(hexdec(L2GDI_COLOR_MIN));
        list($aMax, $rMax, $gMax, $bMax) = self::parseColorInt(hexdec(L2GDI_COLOR_MAX));
        $aRange = $aMax - $aMin;
        $rRange = $rMax - $rMin;
        $gRange = $gMax - $gMin;
        $bRange = $bMax - $bMin;

        $zMin = $this->zMin;
        $zMax = $this->zMax;
        $zRange = $zMax - $zMin;
        if ($zRange)
        {
            $zMulti = 1 / $zRange;
            if ($showNSWE)
            {

                list($aMinBlock, $rMinBlock, $gMinBlock, $bMinBlock) = self::parseColorInt(hexdec(L2GDI_BLOCK_MIN));
                list($aMaxBlock, $rMaxBlock, $gMaxBlock, $bMaxBlock) = self::parseColorInt(hexdec(L2GDI_BLOCK_MAX));
                $aRangeBlock = $aMaxBlock - $aMinBlock;
                $rRangeBlock = $rMaxBlock - $rMinBlock;
                $gRangeBlock = $gMaxBlock - $gMinBlock;
                $bRangeBlock = $bMaxBlock - $bMinBlock;

                for ($x = 0; $x < L2GDI_IMG_SIZE; $x += 1)
                {
                    for ($y = 0; $y < L2GDI_IMG_SIZE; $y += 1)
                    {
                        $cRaw = imagecolorat($this->raw, $x, $y);
                        $zPos = $cRaw & 0xFFF0;
                        $nswe = $cRaw & 0x000F;
                        if ($zPos)
                        {
                            $p = $zMulti * ($zPos - $zMin);
                            if ($nswe < 15)
                            {
                                $a = (int)($aRangeBlock*$p) + $aMinBlock;
                                $r = (int)($rRangeBlock*$p) + $rMinBlock;
                                $g = (int)($gRangeBlock*$p) + $gMinBlock;
                                $b = (int)($bRangeBlock*$p) + $bMinBlock;
                            }
                            else
                            {
                                $a = (int)($aRange*$p) + $aMin;
                                $r = (int)($rRange*$p) + $rMin;
                                $g = (int)($gRange*$p) + $gMin;
                                $b = (int)($bRange*$p) + $bMin;
                            }
                            $c =  ($a << 24) + ($r << 16) + ($g << 8) + ($b);
                            imagesetpixel($this->out, $x, $y, $c);
                        }
                    }
                }
            }
            else
            {
                for ($x = 0; $x < L2GDI_IMG_SIZE; $x += 1)
                {
                    for ($y = 0; $y < L2GDI_IMG_SIZE; $y += 1)
                    {
                        $cRaw = imagecolorat($this->raw, $x, $y);
                        $zPos = $cRaw & 0xFFF0;
                        if ($zPos)
                        {
                            $p = $zMulti * ($zPos - $zMin);
                            $a = (int)($aRange*$p) + $aMin;
                            $r = (int)($rRange*$p) + $rMin;
                            $g = (int)($gRange*$p) + $gMin;
                            $b = (int)($bRange*$p) + $bMin;
                            $c = ($a << 24) + ($r << 16) + ($g << 8) + ($b);
                            imagesetpixel($this->out, $x, $y, $c);
                        }
                    }
                }
            }
        }
    }

    private static function z2color($z, $a=0)
    {
        $z += L2GDI_ZADD;
        $r1 = 0;
        $g1 = 0;
        $b1 = 0;
        $zLast = 0;

        foreach (L2GDI_COLORS as $i => $color)
        {
            $zNext = $color[1];
            $cInt = hexdec($color[0]);
            $r2 = ($cInt >> 16) & 0xFF;
            $g2 = ($cInt >>  8) & 0xFF;
            $b2 = ($cInt      ) & 0xFF;
            if ($z < $zNext)
            {
                if ($i) // first loop done
                {
                    $p = 1 / ($zNext-$zLast) * ($z-$zLast);
                    $r3 = (int)(($r2-$r1)*$p) + $r1;
                    $g3 = (int)(($g2-$g1)*$p) + $g1;
                    $b3 = (int)(($b2-$b1)*$p) + $b1;
                    return ($a << 24) + ($r3 << 16) + ($g3 << 8) + ($b3);
                }
                else
                {
                    return $cInt;
                }
            }
            $r1 = $r2;
            $g1 = $g2;
            $b1 = $b2;
            $zLast = $zNext;
        }
        return ($a << 24) + ($r1 << 16) + ($g1 << 8) + ($b1);
    }

    public function output()
    {
        header('Content-type: image/png');
        imagepng($this->out);
        imagedestroy($this->raw);
        imagedestroy($this->out);
    }

}
