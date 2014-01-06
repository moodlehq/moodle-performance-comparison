<?php

include(__DIR__ . '/webapp/inc.php');

$report = new report();

if (!empty($_GET['timestamps'])) {

    // Create the report.
    $report->make($_GET['timestamps']);

}

// Render it.
$renderer = new report_renderer($report);
$renderer->render();
