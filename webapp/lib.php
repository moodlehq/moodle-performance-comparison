<?php
/**
 * Library file for the performance comparison tool.
 *
 * This is a development tool, created for the sole purpose of helping me investigate performance issues
 * and prove the performance impact of significant changes in code.
 * It is provided in the hope that it will be useful to others but is provided without any warranty,
 * without even the implied warranty of merchantability or fitness for a particular purpose.
 * This code is provided under GPLv3 or at your discretion any later version.
 *
 * @package moodle-jmeter-perfcomp
 * @copyright 2012 Sam Hemelryk (blackbirdcreative.co.nz)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$PREFIX = array(
    'dbreads' => '',
    'dbwrites' => '',
    'dbquerytime' => '',
    'memoryused' => ' MB',
    'filesincluded' => '',
    'serverload' => '',
    'sessionsize' => ' KB',
    'timeused' => '',
    'bytes' => '',
    'latency' => '',
);
$PROPERTIES = array_keys($PREFIX);

$BASEDIR = __DIR__ . '/../'; //'/var/www/localhost/jmeter';

class page {

    public $url;
    public $name;
    public $gitbranch;
    public $desc;
    public $loopcount;
    public $users;

    public $thread = array();
    public $starttime = array();
    public $dbreads = array();
    public $dbwrites = array();
    public $dbquerytime = array();
    public $memoryused = array();
    public $filesincluded = array();
    public $serverload = array();
    public $sessionsize = array();
    public $timeused = array();
    public $bytes = array();
    public $time = array();
    public $latency = array();

    public $count = 0;

    public function __construct($url, $name, $gitbranch, $desc = 'Uknown run', $loopcount = 30, $users = 10) {
        $this->url = $url;
        $this->name = $name;
        $this->gitbranch = $gitbranch;
        $this->desc = $desc;
        $this->loopcount = $loopcount;
        $this->users = $users;
    }

    public function from_result(array $page) {
        global $PROPERTIES;

        if (array_key_exists('thread', $page)) {
            $this->thread[$this->count] = $page['thread'];
        }
        if (array_key_exists('starttime', $page)) {
            $this->starttime[$this->count] = $page['starttime'];
        }
        $this->sessionsize[$this->count] = (float)(str_replace('KB', '', $page['sessionsize']));

        foreach ($PROPERTIES as $property) {
            if ($property == 'sessionsize') {
                continue;
            }
            $this->{$property}[$this->count] = (float)$page[$property];
        }

        $this->count++;
    }

    public function average() {
        global $PROPERTIES;

        $return = array();
        foreach ($PROPERTIES as $property) {
            if (property_exists($this, $property)) {
                $return[$property] = round(array_sum($this->$property)/$this->count, 2);
            }
        }
        return $return;
    }

    public function get_info() {
        global $PROPERTIES;

        $results = array();
        foreach ($PROPERTIES as $key) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $results[$key] = array('average' => 0, 'min' => null, 'max' => null);
            $average = array();
            foreach ($this->$key as $value) {
                $average[] = $value;
                if (is_null($results[$key]['max']) || $value > $results[$key]['max']) {
                    $results[$key]['max'] = $value;
                }
                if (is_null($results[$key]['min']) || $value < $results[$key]['min']) {
                    $results[$key]['min'] = $value;
                }
            }
            $results[$key]['average'] = array_sum($average) / count($average);
            $results[$key]['display'] = round($results[$key]['average'], 2);
        }

        return $results;
    }

    public function average_by_property($property) {
        global $PROPERTIES;

        $result = array();
        foreach ($PROPERTIES as $PROPERTY) {
            $result[$PROPERTY] = array();
        }

        foreach ($this->{$property} as $key => $value) {
            foreach ($PROPERTIES as $PROPERTY) {
                $result[$PROPERTY][$value][] = $this->{$PROPERTY}[$key];
            }
        }

        foreach ($PROPERTIES as $PROPERTY) {
            krsort($result[$PROPERTY]);
        }

        $valueresult = array();
        foreach ($result[$property] as $value => $values) {
            $count = count($values);
            $valueresult[$value] = array('count' => $count);
            foreach ($PROPERTIES as $PROPERTY) {
                $valueresult[$value][$PROPERTY] = round(array_sum($result[$PROPERTY][$value])/$count, 2);
            }
        }

        return $valueresult;
    }

    public function strip_to_most_common_only($organiseby = 'filesincluced') {
        global $PROPERTIES;

        $result = array();
        foreach ($this->$organiseby as $key => $value) {
            $value = (string)$value;
            if (!isset($result[$value])) {
                $result[$value] = 0;
            }
            $result[$value]++;
        }
        arsort($result);
        reset($result);

        $mostcommon = key($result);
        foreach ($this->$organiseby as $key => $value) {
            if ($value != $mostcommon) {
                foreach ($PROPERTIES as $PROPERTY) {
                    unset($this->{$PROPERTY}[$key]);
                }
            }
        }
    }
}

function debug($stuff, $title = 'DEBUG') {
    echo "<pre style='background-color:#FFF;font-size:8pt;max-height:300px;overflow:auto;'>$title: ";
    ob_start();
    print_r($stuff);
    $html = ob_get_contents();
    ob_end_clean();
    echo htmlspecialchars($html);
    echo "</pre>";
}

function display_organised_results($property, page $before, page $after) {
    global $PROPERTIES, $PREFIX;

    $propertyaveragesbefore = $before->average_by_property($property);
    $propertyaveragesafter = $after->average_by_property($property);
    echo "<table cellspacing='0' cellpadding='3px'>";
    echo "<tr style='background-color:#DDD;'>";
    echo "<th colspan='".(count($PROPERTIES)+1)."'><b>Organised by $property</b></th>";
    echo "</tr>";
    echo "<tr style='background-color:#DDD;'>";
    echo "<td></td>";
    $width = round(80/count($PROPERTIES), 1);
    foreach ($PROPERTIES as $p) {
        if ($p == $property) {
            echo "<th style='width:$width%'>$property</th>";
        } else {
            echo "<th style='width:$width%'>$p</th>";
        }
    }
    echo "</tr>";

    $keydisplayed = false;
    foreach ($propertyaveragesbefore as $key => $values) {
        echo "<tr>";
        if (!$keydisplayed) {
            echo "<td rowspan='".count($propertyaveragesbefore)."'><b>Before</b></td>";
            $keydisplayed = true;
        }
        foreach ($PROPERTIES as $p) {
            if ($p == $property) {
                echo "<td style='background-color:#EEE;'>$key ($values[count] hits)</td>";
            } else {
                echo "<td>".$values[$p].$PREFIX[$p]."</td>";
            }
        }
        echo "</tr>";
    }

    $keydisplayed = false;
    foreach ($propertyaveragesafter as $key => $values) {
        echo "<tr>";
        if (!$keydisplayed) {
            echo "<td rowspan='".count($propertyaveragesafter)."'><b>After</b></td>";
            $keydisplayed = true;
        }
        foreach ($PROPERTIES as $p) {
            if ($p == $property) {
                echo "<td style='background-color:#EEE;'>$key ($values[count] hits)</td>";
            } else {
                if (!empty($propertyaveragesbefore[$key][$p])) {
                    // Default output values.
                    $color = '#666';
                    $dif = '';
                    // Customize output.
                    $before = $propertyaveragesbefore[$key][$p];
                    $after = $values[$p];
                    $diff = $after - $before;
                    $roundeddiff = abs(round($diff, 2));
                    if ($diff > 0) {
                        $color = '#83181F';
                        $dif = "(+$roundeddiff)";
                    } else if ($diff < 0) {
                        $color = '#188327';
                        $dif = "(-$roundeddiff)";
                    }
                    echo "<td style='color:$color'>".$after.$PREFIX[$p]." $dif</td>";
                } else {
                    echo "<td>".$values[$p].$PREFIX[$p]."</td>";
                }
            }
        }
        echo "</tr>";
    }
    echo "</table>";
}

function display_results(page $beforepage, page $afterpage) {
    global $PROPERTIES;

    $before = $beforepage->get_info();
    $after = $afterpage->get_info();

    $output = '';
    $stats = '';

    $output .= "<table cellspacing='0' cellpadding='3px'>";
    $output .= "<tr style='background-color:#eee;'>";
    $output .= "<th>Run</th>";
    $width = round(80/count($PROPERTIES),1);
    foreach ($PROPERTIES as $PROPERTY) {
        $output .= "<th style='width:{$width}%'>$PROPERTY</th>";
    }
    $output .= "</tr>";

    $output .= "<tr>";
    $output .= "<th style='text-align:right;background-color:#eee;'>$beforepage->gitbranch branch (Before) $beforepage->count hits</th>";
    foreach ($PROPERTIES as $PROPERTY) {
        $value = $before[$PROPERTY];
        $output .= "<td title='Average: $value[average]\nMin: $value[min]\nMax: $value[max]'>$value[display]</td>";
    }
    $output .= "</tr>";

    $output .= "<tr>";
    $output .= "<th style='text-align:right;background-color:#eee;'>$afterpage->gitbranch branch (After)  $afterpage->count hits</th>";
    foreach ($PROPERTIES as $PROPERTY) {
        // Default output values.
        $color = '#333';
        $dif = '';
        // Customize output.
        $dis = $after[$PROPERTY]['display'];
        $ave = $after[$PROPERTY]['average'];
        $min = $after[$PROPERTY]['min'];
        $max = $after[$PROPERTY]['max'];
        $diff = round($ave - $before[$PROPERTY]['average'],2);
        if ($diff > 0) {
            $color = '#83181F';
            $dif = " (+$diff)";
        } else if ($diff < 0) {
            $color = '#188327';
            $dif = " (-$diff)";
        }
        $output .= "<td style='color:$color;' title='Average: $ave\nMin: $min\nMax: $max'>$dis$dif</td>";
    }
    $output .= "</tr>";

    $output .= "<tr>";
    $output .= "<th style='text-align:right;background-color:#eee;'>% Change</th>";
    foreach ($PROPERTIES as $PROPERTY) {
        // Default output values.
        $p = "-";
        $sp = '-';
        $perc = '&nbsp;';
        $sign = ' ';
        $color = '#666';
        // Customize output.
        $b = $before[$PROPERTY]['average'];
        $a = $after[$PROPERTY]['average'];
        if ($b > 0 && $a > 0) {
            $p = round((($a / $b) * 100) - 100, 2);
            if ($p > 0) {
                $sign = '+';
                if ($p > 1) {
                    $color = '#83181F;';
                }
            } else if ($p < 0) {
                $sign = '-';
                if ($p < -1) {
                    $color = '#188327;';
                }
            } else {
                $color = '#666;';
                $sign = '';
            }
            $sp = abs($p);
            $p = abs($p)."%";
            $perc = '%';
        }

        $stats .= "<div class='statsbox $PROPERTY'>";
        $stats .= "<h3>$PROPERTY</h3>";
        $stats .= "<p style='color:$color'>$sign$sp<span class='perc'>$perc</span></p>";
        $stats .= "</div>";

        $output .= "<td style='color:$color;font-weight:bold;'>$sign$p</td>";
    }
    $output .= "</tr>";

    $output .= "</table>";

    $stats = "<div class='collectedstats' rel='$beforepage->name'><div class='pagename'>$beforepage->name<br /><span style='font-size:50%;font-weight:normal;font-style:italic;'>$beforepage->url</span></div><div class='wrapper'>$stats</div></div>";
    return array($output, $stats);
}

function get_runs($dir = null) {
    global $BASEDIR;
    if ($dir == null) {
        $dir = $BASEDIR.'/runs/';
    }
    $files = scandir($dir);
    $runs = array();
    foreach ($files as $file) {
        if (preg_match('/^(.*?).php$/', $file, $matches)) {
            $key = $matches[1];
            $timestamp = time();
            $branch = 'Unknown';
            if (preg_match('/^([a-zA-Z0-9\-_]+)\.(\d{10})\d*$/', $key, $matches)) {
                $branch = $matches[1];
                $timestamp = $matches[2];
            }
            $start = file_get_contents($dir.$file, null, null, 3, 512);
            $desc = 'Unknown run';
            if (preg_match("/rundesc = '([^']+)'/", $start, $matches)) {
                $desc = $matches[1];
            }
            $group = 'Unknown group';
            if (preg_match("/group = '([^']+)'/", $start, $matches)) {
                $group = $matches[1];
            }
            $loopcount = "Unknown";
            if (preg_match("/loopcount = '(\d+)'/", $start, $matches)) {
                $loopcount = $matches[1];
            }
            $users = "Unknown";
            if (preg_match("/users = '(\d+)'/", $start, $matches)) {
                $users = $matches[1];
            }
            $rampup = "Unknown";
            if (preg_match("/rampup = '(\d+)'/", $start, $matches)) {
                $rampup = $matches[1];
            }
            $throughput = "Unknown";
            if (preg_match("/throughput = '(\d+.\d+|\d+)'/", $start, $matches)) {
                $throughput = $matches[1];
            }
            $siteversion = "Unknown";
            if (preg_match("/siteversion = '(\d+.\d+)'/", $start, $matches)) {
                $siteversion = $matches[1];
            }
            $sitebranch = "Unknown";
            if (preg_match("/sitebranch = '(\d+)'/", $start, $matches)) {
                $sitebranch = $matches[1];
            }
            $sitecommit = "Unknown";
            if (preg_match("/sitecommit = '([^']+)'/", $start, $matches)) {
                $sitecommit = $matches[1];
            }
            $size = "Unknown size";
            if (preg_match("/size = '([^']+)'/", $start, $matches)) {
                $size = $matches[1] . ' size';
            }

            $runs[$key] = array(
                'key' => $key,
                'time' => date('G:i D dS M Y', $timestamp),
                'branch' => $branch,
                'file' => $dir.$file,
                'desc' => $desc,
                'group' => $group,
                'users' => $users,
                'loopcount' => $loopcount,
                'rampup' => $rampup,
                'throughput' => $throughput,
                'siteversion' => $siteversion,
                'sitebranch' => $sitebranch,
                'sitecommit' => $sitecommit,
                'size' => $size
            );
        }
    }
    return $runs;
}

function display_run_selector(array $runs, $before=null, $after=null, array $params = array(), $organiseby = 'filesincluded', $mostcommononly = false, $normalize = false) {
    echo "<div class='runselector'>";
    echo "<form method='get' action=''>";
    foreach ($params as $key => $value) {
        echo "<input type='hidden' name='$key' value='$value' />";
    }
    echo "<label for='before'>Before:&nbsp;</label>";
    echo "<select name='before' id='before'>";
    foreach ($runs as $date => $run) {
        $selected = '';
        if ($before == $date) {
            $selected = ' selected="selected"';
        }
        echo "<option$selected value='$date'>$run[desc] - $run[group], $run[size], Moodle $run[sitebranch] ($run[siteversion], $run[sitecommit]) ($run[users] users * $run[loopcount] loop, rampup=$run[rampup] throughput=$run[throughput]) $run[time]</option>";
    }
    echo "</select>";
    echo "<br/><br/>";
    echo "<label for='after'>After:&nbsp;</label>";
    echo "<select name='after' id='after'>";
    foreach ($runs as $date => $run) {
        $selected = '';
        if ($after == $date) {
            $selected = ' selected="selected"';
        }
        echo "<option$selected value='$date'>$run[desc] - $run[group], $run[size], Moodle $run[sitebranch] ($run[siteversion], $run[sitecommit]) ($run[users] users * $run[loopcount] loop, rampup=$run[rampup] throughput=$run[throughput]) $run[time]</option>";
    }
    echo "</select>";
    echo "<hr />";
    if ($mostcommononly) {
        echo "<input type='checkbox' name='x' value='1' checked='checked' /> Group by and display the most common result set. This will be organised as selected.";
    } else {
        echo "<input type='checkbox' name='x' value='1' /> Group by and display the most common result set only. This will be organised as selected.";
    }
    echo "<br />";
    echo "<label for='o'>Organise by:&nbsp;</label>";
    echo "<select name='o' id='o'>";
    $options = array(
        'dbreads' => 'DB reads',
        'dbwrites' => 'DB writes',
        'dbquerytime' => 'DB query time',
        'filesincluded' => 'Files included',
        'bytes' => 'Bytes',
    );

    foreach ($options as $value => $string) {
        $selected = '';
        if ($value == $organiseby) {
            $selected = ' selected="selected"';
        }
        echo "<option$selected value='$value'>$string</option>";
    }
    echo "</select>";
    echo "<br />";
    echo "<input type='submit' value='Load' />";
    echo "</form>";
    echo "<p>You can change the width and height of the graphs by adding w=newwidth and h=newheight to the url.</p>";
    echo "<p>Also, adding 'n=1|true' to the url, you will get normalized results of outliers.<br/>";
    echo "(Normalization is " . ($normalize ? "ENABLED" : "DISABLED") . " now)</p>";
    echo "</div>";
}

function produce_page_graph($field, $beforekey, page $before, $afterkey, page $after, $width = 800, $height = 600, array $options = array()) {
    global $BASEDIR;

    $subdir = md5($beforekey.$afterkey.$before->name.$width.$height.serialize($options));
    $name = $subdir.'/'.$field.'.png';
    $path = $BASEDIR.'/cache/';

    if (file_exists($path.$name) && empty($_GET['force'])) {
        return $name;
    }

    if (!is_dir($path.$subdir)) {
        mkdir($path.$subdir);
        chmod($path.$subdir, 0775);
    }

    $image = imagecreatetruecolor($width, $height);
    if (function_exists('imageantialias')) {
        imageantialias($image, true);
    }

    $colours = new stdClass;
    $colours->black = imagecolorallocate($image, 0, 0, 0);
    $colours->white = imagecolorallocate($image, 255, 255, 255);
    $colours->shadow = imagecolorallocate($image, 200, 200, 200);
    $colours->beforepoint = imagecolorallocate($image, 165, 165, 255);
    $colours->afterpoint = imagecolorallocate($image, 255, 165, 165);
    $colours->beforeline = imagecolorallocate($image, 110, 110, 255);
    $colours->afterline = imagecolorallocate($image, 255, 110, 110);

    $colours->afterlineflat = imagecolorallocate($image, 255, 0, 0);
    $colours->afterlineave = imagecolorallocate($image, 255, 32, 32);

    $colours->beforelineflat = imagecolorallocate($image, 0, 0, 255);
    $colours->beforelineave = imagecolorallocate($image, 32, 32, 255);

    $x1 = 10;
    $x2 = $width-10;
    $y1 = 30;
    $y2 = $height-10;

    imagefill($image, 0, 0, $colours->white);
    imagefilledrectangle($image, $x1-2, $y1+2, $x2+2, $y2+2, $colours->shadow);
    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $colours->white);
    imagerectangle($image, $x1, $y1, $x2, $y2, $colours->black);

    $graphheight = $y2 - $y1 -2;
    $graphwidth = $x2 - $x1 - 2;

    $total = count($before->$field);
    $min = min(min($before->$field), min($after->$field));
    $max = max(max($before->$field), max($after->$field));

    $dmin = $min*0.1;
    if ($min >= 10 && $min < 100) {
        $dmin = $min*0.2;
    } else if ($min >= 100 && $min < 1000) {
        $dmin = $min*0.2;
    } else if ($min >= 1000) {
        $dmin = $min*0.01;
    }
    $min -= $dmin;
    $max += $dmin;


    if ($min > 0 && $min < 10) {
        $min = 0;
    } else if ($min >= 10 && $min < 100) {
        $min = floor($min);
    } else if ($min >= 100 && $min < 1000) {
        $min = round($min, -1); // Down
    } else if ($min >= 1000) {
        $min = round($min, -3); // Down
    }
    if ($max >= 10 && $max < 100) {
        $max = ceil($max);
    } else if ($max >= 100 && $max < 1000) {
        $max = round($max, -1); // Up
    } else if ($max >= 1000) {
        $max = round($max, -2); // Up
    }

    $lines = min($graphwidth, $total);

    $gap = (($graphwidth-$lines) / $lines);
    if ($gap < 0) {
        $gap = 0;
    }

    $range = $max - $min;
    if ($range > 0) {
        $ratio = $graphheight / $range;
    } else {
        $ratio = 1;
    }

    $b = reset($before->$field);
    $a = reset($after->$field);
    $pb = null;
    $pa = null;
    $px = null;

    $bc = null;
    $ac = null;

    $beforeaverages = array();
    $afteraverages = array();

    $averagesamplespace = max(20, $total/20);

    for ($i=1;$i<$lines;$i++) {
        $xpoint = $i+$x1+(($i-1)*$gap);

        $ybefore = ($height - 10) - floor(($b-$min) * $ratio);
        $yafter = ($height - 10) - floor(($a-$min) * $ratio);

        if ($i > $averagesamplespace/2 && $i < $lines - $averagesamplespace/2) {
            $beforechunk = array_slice($before->$field, $i-$averagesamplespace/2, $averagesamplespace);
            $beforechunk = array_sum($beforechunk) / count($beforechunk);
            $beforechunk = ($height - 10) - floor(($beforechunk-$min) * $ratio);
            $beforeaverages[$xpoint] = $beforechunk;

            $afterchunk = array_slice($after->$field, $i-$averagesamplespace/2, $averagesamplespace);
            $afterchunk = array_sum($afterchunk) / count($afterchunk);
            $afterchunk = ($height - 10) - floor(($afterchunk-$min) * $ratio);
            $afteraverages[$xpoint] = $afterchunk;
        }

        if ($gap > 0 && $px != null) {
            if ($gap > 3) {
                imagefilledellipse($image, $xpoint, $ybefore, 3, 3, $colours->beforepoint);
                imagefilledellipse($image, $xpoint, $yafter, 3, 3, $colours->afterpoint);
            } else {
                imagesetpixel($image, $xpoint, $ybefore, $colours->beforepoint);
                imagesetpixel($image, $xpoint, $yafter, $colours->afterpoint);
            }
            imageline($image, $px, $pb, $xpoint, $ybefore, $colours->beforeline);
            imageline($image, $px, $pa, $xpoint, $yafter, $colours->afterline);
        }
        $px = $xpoint;
        $pb = $ybefore;
        $pa = $yafter;

        $b = next($before->$field);
        $a = next($after->$field);
    }

    $ybefore = array_sum($before->$field)/$total-$min;
    $ybefore = ($height - 10) - round($ybefore * $ratio, 1);

    $yafter = array_sum($after->$field)/$total-$min;
    $yafter = ($height - 10) - round($yafter * $ratio, 1);

    imagedashedline($image, 11, $ybefore, $width-12, $ybefore, $colours->beforelineflat);
    imagedashedline($image, 11, $yafter, $width-12, $yafter, $colours->afterlineflat);

    $lastx = null;
    $lasty = null;
    foreach ($beforeaverages as $x => $y) {
        if ($lastx != null) {
            imageline($image, $lastx, $lasty, $x, $y, $colours->beforelineave);
        }
        $lastx = $x;
        $lasty = $y;
    }
    $lastx = null;
    $lasty = null;
    foreach ($afteraverages as $x => $y) {
        if ($lastx != null) {
            imageline($image, $lastx, $lasty, $x, $y, $colours->afterlineave);
        }
        $lastx = $x;
        $lasty = $y;
    }

    write_graph_y_labels($image, $min, $max, $width, $height, $colours->black);
    write_graph_title($image, $field, $width, $height, $colours->black);
    write_graph_legend($image, $colours, $width, $height);

    imagepng($image, $path.$name, 9);
    chmod($path.$name, 0775);
    return $name;
}

function write_graph_y_labels(&$image, $min, $max, $width, $height, $colour) {
    $font = get_font();
    imagettftext($image, 6, 0, 12, 40, $colour, $font, round($max,2));
    imagettftext($image, 6, 0, 12, $height-12, $colour, $font, round($min,2));
}

function write_graph_title(&$image, $title, $width, $height, $colour) {
    $font = get_font();
    $bb = imagettfbbox(10, 0, $font, $title);
    imagettftext($image, 10, 0, ($width/2)-($bb[2]-$bb[0])/2, 20, $colour, $font, $title);
}

function write_graph_legend(&$image, $colours, $width, $height) {
    $font = get_font();

    $size = 7;
    $angle = 0;

    $title = 'Before';
    $bb = imagettfbbox($size, $angle, $font, $title);
    imagettftext($image, $size, $angle, ($width/2)-($bb[2]-$bb[0]), $height-12, $colours->beforepoint, $font, $title);

    $title = 'After';
    $bb = imagettfbbox($size, $angle, $font, $title);
    imagettftext($image, $size, $angle, ($width/2)+($bb[2]-$bb[0]), $height-12, $colours->afterpoint, $font, $title);
}

function get_font() {
    global $BASEDIR;
    return $BASEDIR.'/webapp/DejaVuSans.ttf';
}

function build_pages_array(array $runs, $before, $after, $normalize = false) {
    global $PROPERTIES;

    $pages = array();
    $results = array();
    $combined = array('before' => array(), 'after' => array());
    foreach ($PROPERTIES as $PROPERTY) {
        $combined['before'][$PROPERTY] = array();
        $combined['after'][$PROPERTY] = array();
    }

    include($runs[$before]['file']);
    foreach ($results as $thread) {
        foreach ($thread as $page) {
            $key = md5($page['name']);
            if (!array_key_exists($key, $pages)) {
                $pages[$key] = array('before' => null, 'after' => null);
                $pages[$key]['before'] = new page($page['url'], $page['name'], 'unknown');
            }
            $pages[$key]['before']->from_result($page);
        }
    }

    // We may see normalized information, let's do it.
    if ($normalize) {
        foreach ($pages as $key => $page) {
            foreach ($PROPERTIES as $property) {
                normalize_outliners_in_array($pages[$key]['before']->$property);
            }
        }
    }

    $results = array();
    include($runs[$after]['file']);
    foreach ($results as $thread) {
        foreach ($thread as $page) {
            $key = md5($page['name']);
            if (!array_key_exists($key, $pages)) {
                $pages[$key] = array('before' => null, 'after' => null);
            }
            if (empty($pages[$key]['after'])) {
                $pages[$key]['after'] = new page($page['url'], $page['name'], 'unknown');
            }
            $pages[$key]['after']->from_result($page);
        }
    }

    // We may see normalized information, let's do it.
    if ($normalize) {
        foreach ($pages as $key => $page) {
            foreach ($PROPERTIES as $property) {
                normalize_outliners_in_array($pages[$key]['after']->$property);
            }
        }
    }

    $combined['before'] = new page('Before and after', 'Combined total properties', 'Combined');
    $combined['after'] = new page('Combined after', 'Combined total properties', 'Combined');
    foreach ($pages as $pagearray) {
        foreach ($PROPERTIES as $PROPERTY) {
            $count = 0;
            if (!isset($pagearray['before']->$PROPERTY)) {
                continue;
            }
            if (!isset($pagearray['after']->$PROPERTY)) {
                continue;
            }
            foreach ($pagearray['before']->$PROPERTY as $key => $value) {
                if (!isset($combined['before']->{$PROPERTY}[$key])) {
                    $combined['before']->{$PROPERTY}[$key] = 0;
                }
                if (!isset($combined['after']->{$PROPERTY}[$key])) {
                    $combined['after']->{$PROPERTY}[$key] = 0;
                }
                $combined['before']->{$PROPERTY}[$key] += $value;
                $combined['after']->{$PROPERTY}[$key] += $pagearray['after']->{$PROPERTY}[$key];
                $count++;
            }
        }

    }

    $pages['combined'] = $combined;
    return $pages;
}

/**
 * Given an array of values, modify it returning all outliers normalized
 *
 * @param array values which outliers we are going to normalize
 */
function normalize_outliners_in_array(array &$values): void {
    // Calculate the estadistics we need.
    list($lower, $upper) = calculate_outlier_limits($values);
    $avg = array_sum($values) / count($values);
    $pseudoavg = ($lower === $upper) ? $lower : $avg;
    // Iterate over the array, updating outliers by pseudo average.
    foreach($values as $key => $value) {
        if ($value < $lower || $value > $upper) {
            $values[$key] = $pseudoavg;
        }
    }
}

/**
 * Return the lower and upper values beyong which values will be considered outliers.
 *
 * Going to use the 1.5xIQR rule here that is good enough
 * and not so avg-dependent like the 3*SD one. It doen't
 * matter much, really. I'm not inventing anything.
 * Ref: https://bit.ly/2UCSlvi
 *
 * @param array $values array of values to find outlier limits.
 * @return array lower and upper outlier limits.
 */
function calculate_outlier_limits(array $values): array {
    // Calculate all quartiles.
    $quartiles = quartiles($values);
    // Calculate IQR.
    $iqr = $quartiles[3] - $quartiles[1];
    // Lower and upper.
    $lower = $quartiles[1] - ($iqr * 1.5);
    $upper = $quartiles[3] + ($iqr * 1.5);

    return [$lower, $upper];
}

/**
 * Calculate all quartiles
 *
 * @param array $values array of values to calculate the quartiles
 * @return array containing quartiles 0-4
 */
function quartiles(array $values): array {
    // We need the values to be sorted and counted.
    sort($values);
    $count = count($values);

    $q = [];

    $q[0] = min($values);
    $q[2] = median($values);
    $q[4] = max($values);

    if ($count % 2 !== 0) {
        unset($values[round($count / 2)]);
        $count--;
    }

    $split = array_chunk($values, $count / 2);

    $q[1] = median($split[0]);
    $q[3] = median($split[1]);

    return $q;
}

/**
 * Calculate the median of an array of numeric values
 *
 * @param array $values array of values to calculate the median
 * @return float the median
 */
function median(array $values): float {
    // We need the values to be sorted and counted.
    sort($values);
    $count = count($values);

    $i = round($count / 2) - 1;
    if ($count % 2 !== 0) {
        $m = $values[$i];
    } else {
        $m = ($values[$i] + $values[$i + 1]) / 2;
    }

    return $m;
}
