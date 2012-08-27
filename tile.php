<?php
/*
    tile.php
    ===========================================================================
    A quick hack to overlay bing tiles with their capture date as provided in the HTTP metadata.

    Sample request:
    tile.php?t=12020033230

    Call in Bing Ajax SDK like this:
        var tileSource = new Microsoft.Maps.TileSource({uriConstructor: 'http://server/tile.php?t={quadkey}'});
        var tilelayer= new Microsoft.Maps.TileLayer({ mercator: tileSource, opacity: 1 });
        map.entities.push(tilelayer);

    ===========================================================================
    Copyright (c) 2010 Very Furry / Martijn van Exel / ant

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
    */

// Your Tilecache base directory. You will need to create a "hires" directory here.
// This directory needs to be writable by the web server.
$TC_BASE = '/home/ant/public_html/bingimageanalyzer-cache/';

// Optionally, define a path to a local PHP error log file here if for some reason you don't want to use PHP's main error log file. If empty, errors will be logged using the global PHP configuration.
// You will need to create this file and make it writable for the web server.
$LOG_LOCAL = 'php_errors.log';

// From here on, no need for user configuration
$DEBUGGING = false;

error_reporting(E_ALL ^ E_NOTICE);
if(strlen($LOG_LOCAL) > 0) ini_set("error_log", "php_errors.log");

// This checks for valid TMS type request URIs, like http://domain/1.0.0/basic/17/67321/43067.png
$t = parse_query();

$s = rand(0, 7);
$url_base = 'http://ecn.t'.$s.'.tiles.virtualearth.net/tiles/a';
$url_end = '.jpeg?g=1026&n=z';
$force = $_GET['force'] == '1';
$cur_zoom = strlen($t);
$nodepth = strlen($_GET['nodepth']) > 0;

// VE CONSTANTS
$EarthRadius = 6378137;
$MinLatitude = -85.05112878;
$MaxLatitude = 85.05112878;
$MinLongitude = -180;
$MaxLongitude = 180;

$tilecache_basedir_hires = $TC_BASE . 'hires';

if (isset($_GET['debug'])) {
    $DEBUGGING = true;
    debug();
}

$hires_fn = preg_replace('/(\d)/', '/\1', $t);
$hires_fn = $tilecache_basedir_hires . $hires_fn . '.png';

if(!($d)) header("Content-type: image/png");
else print($url);

//get hires tiles
$im = get_hires($t);

if($im == false) {
    if(file_exists($hires_fn)) {
        $im = imagecreatefrompng($hires_fn);
        imagealphablending($im, true);
    }
    else {
        $im = imagecreatetruecolor(256, 256);
        imagealphablending($im, false);
        $black = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $black);
    }
    imagesavealpha($im, true);
}
imagepng($im);
imagedestroy($im);

/*
 * get_hires()
 * recursively creates hires analysis tiles
 *
 * params:  $t      quadkey of the tile to be drawn
 * returns: image resource on success, false if file doesn't need updating
 */
function get_hires($t) {
    global $DEBUGGING, $cur_zoom, $tilecache_basedir_hires, $force;
    $hires_fn = preg_replace('/(\d)/', '/\1', $t);
    $hires_dir = substr(($tilecache_basedir_hires . $hires_fn), 0, -2);
    $hires_fn = $tilecache_basedir_hires . $hires_fn . '.png';
    $hires_exists = file_exists($hires_fn);

    $log = array("");
    $log_fn = $hires_fn . ".log";
    $log_exists = file_exists($log_fn);

    if ($log_exists) {
        $log = explode(",", file_get_contents($log_fn));
        //old log files had one mtime only (one number). newer ones have one for each subtile ($j).
        $oldlog = count($log) == 1;
    }

    $z = strlen($t);
    $mtime = 0;
    $uptodate = false;

    if($DEBUGGING) {
        $indent = "";
        for($i = $cur_zoom; $i < $z; $i++) $indent .= "  ";
        print($indent."processing tile ".$t." in zoom ".$z."\n");
    }

    //check if file is up to date
    if($hires_exists && !$force) {
        $mtime = filemtime($hires_fn);
        $uptodate = $log_exists ? $mtime > max($log) : $z < 14;
    }

    if($DEBUGGING) {
        print($indent.($hires_exists ? "file exists, mtime ".$mtime."\n" : "file does not exist\n"));
        print($indent.($log_exists ? "log file: ".file_get_contents($log_fn)."\n" : "log file does not exist\n"));
        print($indent."mtime ".($mtime > max($log) ? "greater" : "less"). " than max. logged time; ".($uptodate ? "aborting\n" : "\n"));
    }

    //return false if the tile is up to date (only in first iteration)
    if ($z == $cur_zoom && $uptodate) {
        //error_log("not updating ".$t);
        return false;
    }

    //error_log("updating ".$t);

    //prepare image file
    $im = imagecreatetruecolor(256, 256);
    imagealphablending($im, false);
    $black = imagecolorallocatealpha($im, 0, 0, 0, 127);
    $red = imagecolorallocatealpha($im, 255, 0, 0, 0);              //f00
    $green = imagecolorallocatealpha($im, 0, 255, 0, 0);            //0f0
    $cyan = imagecolorallocatealpha($im, 0, 204, 153, 0);           //0c9
    $blueishcyan = imagecolorallocatealpha($im, 0, 153, 204, 0);    //09c
    $blue = imagecolorallocatealpha($im, 0, 102, 255, 0);           //06f
    imagefill($im, 0, 0, $black);
    imagesavealpha($im, true);

    //if the file exists, prepare for updating it
    if($hires_exists) {
        $src = imagecreatefrompng($hires_fn);
        imagecopyresampled($im, $src, 0, 0, 0, 0, 256, 256, 256, 256);
    }
    //mkdir if directory doesn't exist
    elseif(!file_exists($hires_dir)) {
        mkdir($hires_dir, 0777, true);
    }

    //check availability of hires tiles
    if($z == $cur_zoom) {
        if($z >= 20 && check_tile_exists($t)) {
            imagefill($im, 0, 0, $blue);
        }
        elseif($z >= 19 && check_tile_exists($t)) {
            imagefill($im, 0, 0, $blueishcyan);
        }
        elseif($z >= 18 && check_tile_exists($t)) {
            imagefill($im, 0, 0, $cyan);
        }
        elseif($z >= 14 && $z < 18) {
            if(check_tile_exists($t)) {
                imagefill($im, 0, 0, $green);
            }
            else {
                imagefill($im, 0, 0, $red);
            }
        }
        if($z >= 14 && !$log_exists) {
            //error_log("writing ".$t);
            if($DEBUGGING) print($indent."saving (hires)\n");
            imagepng($im, $hires_fn);
            mark_as_visited($t);
        }
    }

    //if there is a log file, process tiles in higher zoom levels... but only four levels deeper
    if($log_exists && $z - $cur_zoom < 4) {
        //for each subtile...
        for($j = 0; $j < 4; $j++) {
            //...check if it has updates
            if($oldlog ? $mtime < (int)$log[0] : $mtime < (int)$log[$j]) {
                $imsrc = get_hires($t.$j);

                //paint the tile
                $dst_x = $j==0 || $j==2 ? 0 : 128;
                $dst_y = $j==0 || $j==1 ? 0 : 128;

                for($x = 0; $x <= 255; $x = $x + 2) {
                    for($y = 0; $y <= 255; $y = $y + 2) {
                        $new_x = $dst_x + $x/2;
                        $new_y = $dst_y + $y/2;
                        $color = imagecolorat($im, $new_x, $new_y);
                        $colorsrc = imagecolorat($imsrc, $x, $y);

                        if($colorsrc != $color &&
                            $colorsrc != $black) {
                            imagesetpixel($im, $new_x, $new_y, $colorsrc);
                        }
                    }
                }
            }
        }
        //write contents to file if back in first iteration
        if($z == $cur_zoom) {
            //error_log("writing ".$t);
            if($DEBUGGING) print($indent."saving\n");
            imagepng($im, $hires_fn);
            mark_as_visited($t);
        }
    }
    if($DEBUGGING) print($indent."----\n");
    return $im;
}

/*
 * mark_as_visited()
 * Write log files for parent tiles. Every log file contains a comma-separated list
 * of four numeric values that represent the modification times for each subtile.
 *
 * params:  $t      quadkey of the modified tile
 */
function mark_as_visited($t) {
    global $DEBUGGING, $cur_zoom, $tilecache_basedir_hires;

    if($DEBUGGING) {
        $z = strlen($t);
        $indent = "";
    }

    $mtime = time();

    //step up to parent folder to write log files four times
    for($i = 0; $i < 4; $i++) {
        $q = substr($t, -1);
        $t = substr($t, 0, -1);
        $z = strlen($t);

        if($DEBUGGING) $indent .= "> ";

        $parent_hires_fn = preg_replace('/(\d)/', '/\1', $t);
        $parent_hires_fn = $tilecache_basedir_hires . $parent_hires_fn . '.png';
        $log_fn = $parent_hires_fn . ".log";
        $log = array("", "", "", "");

        if($DEBUGGING) print($indent."marking ".$t."\n");

        if(file_exists($log_fn)) {
            $log = explode(",", file_get_contents($log_fn));
            //old log files had one mtime only (one number). newer ones have one for each subtile ($j).
            if(count($log) == 1) {
                $log[1] = $log[2] = $log[3] = $log[0];
            }

            if($DEBUGGING) print($indent."found log: ".file_get_contents($log_fn)."\n");
        }

        //old style logging in first two steps
        if($z >= $cur_zoom - 2) {
            $fh = fopen($log_fn, 'w') or die("can't open file for writing");
            fwrite($fh, $mtime);
            if($DEBUGGING) print($indent."writing log: ".$mtime."\n");
            fclose($fh);
        }
        elseif($z > 1) {
            $fh = fopen($log_fn, 'w') or die("can't open file for writing");
            for($j = 0; $j < 4; $j++) {
                if($j == $q) {
                    fwrite($fh, $mtime . ",");
                    if($DEBUGGING) print($indent."writing log: ".$mtime." at ".$q."\n");
                }
                else {
                    fwrite($fh, $log[$j] . ",");
                }
            }
            fclose($fh);
        }
        else break;
    }
}

function get_tile_headers($quadkey){
    global $url_base,$url_end,$DEBUGGING;
    $url = $url_base.$quadkey.$url_end;
    //if($DEBUGGING) print("\tchecking tile url: " . $url . "\n");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_HEADER,         true);
    curl_setopt($ch, CURLOPT_NOBODY,         true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    $headers = curl_exec($ch);
    //if($DEBUGGING) print("\theaders: " . $headers . "\n");
    return $headers;
}

function check_tile_exists($quadkey) {
    return preg_match("/X\-VE\-Tile\-Info\:\ no\-tile/m", get_tile_headers($quadkey)) > 0 ? false : true;
}

function charAt($str,$pos) {
    return (substr($str,$pos,1) !== false) ? substr($str,$pos,1) : -1;
}

// VE tile calculation functions adapted from C# code at http://msdn.microsoft.com/en-us/library/bb259689.aspx
function Clip($n, $minValue, $maxValue) {
    return min(max($n, $minValue), $maxValue);
}

function MapSize($zoomLevel) {
    return (int) 256 << $zoomLevel;
}

function GroundResolution($latitude, $zoomLevel) {
    $MinLatitude = -85.05112878;
    $MaxLatitude = 85.05112878;
    $EarthRadius = 6378137;
    $latitude = Clip($latitude, $MinLatitude, $MaxLatitude);
    return cos($latitude * pi() / 180) * 2 * pi() * $EarthRadius / MapSize($zoomLevel);
}

function MapScale($latitude, $zoomLevel, $screenDpi) {
    return GroundResolution($latitude, $zoomLevel) * $screenDpi / 0.0254;
}

function LatLongToPixelXY($latitude, $longitude, $zoomLevel) {
    $EarthRadius = 6378137;
    $MinLatitude = -85.05112878;
    $MaxLatitude = 85.05112878;
    $MinLongitude = -180;
    $MaxLongitude = 180;
    print("lat/lon:" . $latitude . "/" . $longitude);
    $latitude = Clip($latitude, $MinLatitude, $MaxLatitude);
    $longitude = Clip($longitude, $MinLongitude, $MaxLongitude);
    print("lat/lon:" . $latitude . "/" . $longitude);
    $x = ($longitude + 180) / 360;
    $sinLatitude = sin($latitude * pi() / 180);
    $y = 0.5 - log((1 + $sinLatitude) / (1 - $sinLatitude)) / (4 * pi());

    $mapSize = MapSize($zoomLevel);
    print("mapsize:" . $mapSize);
    $pixelX = Clip($x * $mapSize + 0.5, 0, $mapSize - 1);
    $pixelY = Clip($y * $mapSize + 0.5, 0, $mapSize - 1);

    return array('pixelX' => (int) $pixelX, 'pixelY' => (int) $pixelY);
}

function PixelXYToLatLong($pixelX, $pixelY, $zoomLevel) {
    $mapSize = MapSize($zoomLevel);
    $x = (Clip($pixelX, 0, $mapSize - 1) / $mapSize) - 0.5;
    $y = 0.5 - (Clip($pixelY, 0, $mapSize - 1) / $mapSize);

    $latitude = 90 - 360 * atan(exp(-$y * 2 * pi())) / pi();
    $longitude = 360 * $x;

    return array('latitude' => $latitude, 'longitude' => $longitude);
}

function PixelXYToTileXY($pixelX, $pixelY) {
    $tileX = $pixelX / 256;
    $tileY = $pixelY / 256;
    return array('tileX' => (int) $tileX, 'tileY' => (int) $tileY);
}

function TileXYToPixelXY($tileX, $tileY) {
    $pixelX = $tileX * 256;
    $pixelY = $tileY * 256;
    return array('pixelX' => $pixelX, 'pixelY' => $pixelY);
}

function TileXYToQuadKey($tileX, $tileY, $zoomLevel) {
    $quadKey = "";
    for ($i = $zoomLevel; $i > 0; $i--) {
        $digit = '0';
        $mask = 1 << ($i - 1);
        if (($tileX & $mask) != 0) {
            $digit++;
        }
        if (($tileY & $mask) != 0) {
            $digit++;
            $digit++;
        }
        $quadKey .= $digit;
    }
    return $quadKey;
}

function QuadKeyToTileXY($quadKey) {
    $tileX = $tileY = 0;
    $zoomLevel = strlen(quadKey);
    for ($i = $zoomLevel; $i > 0; $i--) {
        $mask = 1 << ($i - 1);
        switch (substr($quadKey,$levelOfDetail - i,1)) {
            case '0':
                break;

            case '1':
                $tileX |= mask;
                break;

            case '2':
                $tileY |= mask;
                break;

            case '3':
                $tileX |= mask;
                $tileY |= mask;
                break;

            default:
                return false;
        }
    }
    return array('tileX' => $tileX, 'tileY' => $tileY, 'zoomLevel' => $zoomLevel);
}

    // adapted from http://social.msdn.microsoft.com/Forums/en-US/vemapcontroldev/thread/49d2e73a-b826-493b-84fd-34b0cb4d4fc3/
function QuadKeyToLatLong($quadkey) {
    $x=0;
    $y=0;
    $zoomlevel = strlen($quadkey);

    //convert quadkey to tile xy coords
    for ($i = 0; $i < $zoomlevel; $i++) {
        $factor = pow(2,$zoomlevel-$i-1);
        switch (charAt($quadkey,$i)) {
            case '0':
                break;
            case '1':
                $x += $factor;
                break;
            case '2':
                $y += $factor;
                break;
            case '3':
                $x += $factor;
                $y += $factor;
                break;
        }
    }

    //convert tileXY into pixel coordinates for top left corners
    $pixelX = $x*256;
    $pixelY = $y*256;

    //convert to latitude and longitude coordinates
    $longitude = $pixelX*360/(256*pow(2,$zoomlevel)) - 180;
    $latitude = asin((exp((0.5 - $pixelY / 256 / pow(2,$zoomlevel)) * 4 * pi()) - 1) / (exp((0.5 - $pixelY / 256 / pow(2,$zoomlevel)) * 4 * pi()) + 1)) * 180 / pi();
    return array('lat' => $latitude, 'lon' => $longitude);
}

function parse_query() {
    $tms_identifier = 0;
    $req_uri = $_SERVER["REQUEST_URI"];
    $matches = preg_split("/\//",$_SERVER["REQUEST_URI"]);
    for($i = 0; $i < count($matches);  $i++) {
        if($matches[$i]=="1.0.0")
            $tms_identifier = $i;
    }
    if($tms_identifier) {
        $tms_zoom = (int)$matches[$tms_identifier+2];
        $tms_x=(int)$matches[$tms_identifier+3];
        preg_match("/\d+/",$matches[$tms_identifier+4],$tms_y_matches);
        $tms_y=(int)$tms_y_matches[0];
        if($tms_zoom && $tms_x && $tms_y) {
            $n = pow(2, $tms_zoom);
            $lon_deg = $tms_x / $n * 360.0 - 180.0;
            $lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $tms_y / $n))));
            $t = TileXYToQuadKey($tms_x,$tms_y,$tms_zoom+1);
        }
    }
    if(!isset($t))
        $t = $_GET['t'];
    return $t;
}

function debug() {
    global $t, $cur_zoom;
    print('<html><head><title>debug tile ' . $t . '</title></head><body><pre>');
    print('debugging tile ' . $t . "\n");
    print('current zoom: ' . $cur_zoom . "\n\n");

    get_hires($t);
    exit();
}
?>
