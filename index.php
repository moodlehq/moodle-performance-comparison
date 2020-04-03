<?php

include(__DIR__ . '/webapp/inc.php');
require_once(__DIR__ . '/webapp/lib.php');

$report = new report();

if (!empty($_GET['timestamps'])) {

    $normalize = false;
    if (!empty($_GET['n']) && preg_match('/^(0|1|true|false)$/', $_GET['n'])) {
        $normalize = (bool)$_GET['n'];
    }

    // Create the report.
    $report->make($_GET['timestamps'], $normalize);

}

// Render it.
$renderer = new report_renderer($report);
$renderer->render($normalize);
