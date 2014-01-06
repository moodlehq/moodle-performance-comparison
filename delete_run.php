<?php

include(__DIR__ . '/webapp/inc.php');

if (!$filename = $_GET['filename']) {
    die('Error: No filename to delete');
}

if (!preg_match('/^\d*$/', $filename, $matches)) {
    die('Error: Incorrect filename');
}

$returnurl = urldecode($_GET['returnurl']);

$run = new test_plan_run($filename);
if (!$run->delete()) {
    echo '<b>Error: There was a problem deleting the file</b>';
} else {
    echo '<p>Run deleted</p>';

    // Remove the deleted one.
    $timestamps = explode('&', $returnurl);
    foreach ($timestamps as $key => $timestamp) {
        if (strstr($timestamp, $filename) != false) {
            unset($timestamps[$key]);
        }
    }
    $returnurl = implode('&', $timestamps);
}

// Link to return to the index.
echo '<br/><br/><a href="index.php?' . $returnurl . '">Return to the runs page</a>';
