<?php

include(__DIR__ . '/webapp/inc.php');

if (!$filename = $_GET['filename']) {
    die('Error: No filename to delete');
}

if (!preg_match('/^\d*$/', $filename, $matches)) {
    die('Error: Incorrect filename');
}

$run = new test_plan_run($filename);
$run->download();
