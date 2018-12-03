<?php

set_time_limit(60);
ini_set("memory_limit","256M");
ini_set('display_errors', 1);
error_reporting(E_ALL);




include('l2gdr.class.php');
define('L2GDI_COLOR_MIN', '000000');
define('L2GDI_COLOR_MAX', 'FFFFFF');
define('L2GDI_BLOCK_MIN', '100000');
define('L2GDI_BLOCK_MAX', 'FF0000');
include('l2gdi.class.php');

use L2PHP\L2GeoDataReader as L2GeoDataReader;
use L2PHP\L2GeoDataImage as L2GeoDataImage;




// 22_22 (giran) = 0-4 levels
// 23_18 (toi) = 0-17 levels
$mapId = '23_18';
$mapFile = "{$mapId}.l2j";
$mapLevel = 0;

$L2GDR = new L2GeoDataReader($mapFile);
$L2GDI = new L2GeoDataImage($L2GDR, $mapLevel);
$L2GDI->heightmap(true);
$L2GDI->output();




































