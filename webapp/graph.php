<?php
/**
 * Displays a large graph.
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

require_once(__DIR__ . '/lib.php');

$runs = get_runs();
$before = null;
$after = null;
$width = '800';
$height = '600';
$normalize = false;
if (!empty($_GET['before']) && array_key_exists($_GET['before'], $runs)) {
    $before = $_GET['before'];
    $beforekey = array_search($before, $runs);
}
if (!empty($_GET['after']) && array_key_exists($_GET['after'], $runs)) {
    $after = $_GET['after'];
    $afterkey = array_search($after, $runs);
}
if (!empty($_GET['property']) && in_array($_GET['property'], $PROPERTIES)) {
    $property = $_GET['property'];
}
if (!empty($_GET['w']) && preg_match('/^\d+$/', $_GET['w'])) {
    $width = (int)$_GET['w'];
}
if (!empty($_GET['h']) && preg_match('/^\d+$/', $_GET['h'])) {
    $height = (int)$_GET['h'];
}
if (!empty($_GET['n']) && preg_match('/^(0|1|true|false)$/', $_GET['n'])) {
    $normalize = (bool)$_GET['n'];
}

$pages = array();
if ($before && $after) {
    $pages = build_pages_array($runs, $before, $after, $normalize);
}

if (isset($_GET['page']) && array_key_exists($_GET['page'], $pages)) {
    $page = $pages[$_GET['page']];
}

echo "<html><head></head><body style='margin:0;padding:0;text-align:center;'>";
echo "<img src='../cache/" .
    produce_page_graph($property, $beforekey, $page['before'], $afterkey, $page['after'],
        $width, $height, array('n' => $normalize)) .
    "' alt='$property' style='width:{$width}px;height:{$height}px;' />";
echo "</body></html>";
