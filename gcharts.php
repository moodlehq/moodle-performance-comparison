<?php

ini_set('display_errors', true);
ini_set('error_reporting', E_ALL);

require_once(__DIR__ . '/webapp/classes/google_charts_renderer.php');
require_once(__DIR__ . '/webapp/classes/google_chart.php');
require_once(__DIR__ . '/webapp/classes/test_plan_runs.php');
require_once(__DIR__ . '/webapp/classes/report_renderer.php');
require_once(__DIR__ . '/webapp/classes/report.php');

$report = new report();

if (!empty($_GET['timestamps'])) {

    // Create the report.
    $report->make($_GET['timestamps']);

}

// Render it.
$renderer = new report_renderer($report);
$renderer->render();
