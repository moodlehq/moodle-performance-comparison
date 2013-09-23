<?php
/**
 * The default entry point for the Performance comparison tool.
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

require_once(__DIR__ . '/webapp/lib.php');

$runs = get_runs();
$before = null;
$after = null;
$width = '300';
$height = '150';
$organiseby = 'filesincluded';
$mostcommononly = false;
if (!empty($_GET['before']) && array_key_exists($_GET['before'], $runs)) {
    $before = $_GET['before'];
}
if (!empty($_GET['after']) && array_key_exists($_GET['after'], $runs)) {
    $after = $_GET['after'];
}
if (!empty($_GET['w']) && preg_match('/^\d+$/', $_GET['w'])) {
    $width = (int)$_GET['w'];
}
if (!empty($_GET['h']) && preg_match('/^\d+$/', $_GET['h'])) {
    $height = (int)$_GET['h'];
}
if (!empty($_GET['o']) && preg_match('/^[a-z]+$/', $_GET['o'])) {
    $organiseby = $_GET['o'];
}
if (!empty($_GET['x']) && preg_match('/^(0|1|true|false)$/', $_GET['x'])) {
    $mostcommononly = (bool)$_GET['x'];
}

$pages = array();
if ($before && $after) {
    $pages = build_pages_array($runs, $before, $after);
}

echo "<html>";
echo "<head>";
echo '<script type="text/javascript" src="http://yui.yahooapis.com/combo?3.3.0/build/yui/yui-min.js&3.3.0/build/oop/oop-min.js&3.3.0/build/event-custom/event-custom-base-min.js&3.3.0/build/event/event-base-min.js&3.3.0/build/dom/dom-base-min.js&3.3.0/build/dom/selector-native-min.js&3.3.0/build/dom/selector-css2-min.js&3.3.0/build/node/node-base-min.js&3.3.0/build/event/event-base-ie-min.js&3.3.0/build/event-custom/event-custom-complex-min.js&3.3.0/build/event/event-synthetic-min.js&3.3.0/build/event/event-hover-min.js&3.3.0/build/dom/dom-style-min.js&3.3.0/build/dom/dom-style-ie-min.js&3.3.0/build/node/node-style-min.js"></script>';
echo '<link rel="stylesheet" type="text/css" href="webapp/jmeter.css" />';
echo "<script type='text/javascript' src='webapp/jmeter.js'></script>";
echo "</head>";
echo "<body>";

display_run_selector($runs, $before, $after, array('w' => $width, 'h' => $height), $organiseby, $mostcommononly);

if ($before && $after) {
    $count = 0;
    echo "<div id='pagearray'>";
    $statsarray = array();
    foreach ($pages as $key => $page) {
        if (!is_object($page['before']) || !is_object($page['after'])) {
            continue;
        }
        $count++;
        $class = ($count%2)?'odd':'even';
        $classkey = substr($key, 0, 8);
        if ($mostcommononly) {
            $page['before']->strip_to_most_common_only($organiseby);
            $page['after']->strip_to_most_common_only($organiseby);
        }
        echo "<div class='pagecontainer $class page-$classkey'>";
        echo "<h1 class='pagetitle'>".$page['before']->name."</h1>";
        echo "<h2 class='pagesubtitle'><a href='".$page['before']->url."'>".$page['before']->url."</a></h2>";
        echo "<div class='statistical'>";

        list($output, $stats) = display_results($page['before'], $page['after']);
        echo $stats;
        echo $output;
        $statsarray[] = $stats;
        display_organised_results($organiseby, $page['before'], $page['after']);
        
        echo "<div class='graphdiv'>";
        foreach ($PROPERTIES as $PROPERTY) {
            if (!property_exists($page['before'], $PROPERTY)) {
                continue;
            }
            $graphfile = produce_page_graph($PROPERTY, $before, $page['before'], $after, $page['after'], $width, $height, array('x' => $mostcommononly));
            echo "<a href='webapp/graph.php?before=$before&after=$after&property=$PROPERTY&page=$key' class='largegraph'>";
            echo "<img src='./cache/".$graphfile."' alt='$PROPERTY' style='width:{$width}px;height:{$height}px;' />";
            echo "</a>";
        }
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
    echo "<div class='pagecontainer statsarray even'>";
    echo "<h1 class='pagetitle'>Combined stats</h1>";
    $cstats = array_pop($statsarray);
    array_unshift($statsarray, $cstats);
    foreach ($statsarray as $stats) {
        echo $stats;
    }
    echo "</div>";
    echo "</div>";
}
echo "\n<script type='text/javascript'>YUI().use('node', collapse_pages);</script>\n";
echo "</body>";
echo "</html>";
