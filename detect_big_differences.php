<?php

/**
 * Script to detect big changes between runs.
 *
 * More useful when running it through CLI as
 * it can be easily used from CI servers to check
 * the exit code.
 *
 * It takes timestamps (in miliseconds, like the runs files names) in ascending ordered,
 * so the first one will be considered the before branch and the next one/s the after branches.
 *
 * Example:
 *  php detect_big_differences.php 1231231231231 1231231231232 1231231231233
 */

include(__DIR__ . '/webapp/inc.php');
include(__DIR__ . '/webapp/lib.php');

$normalize = false;

// Removing the script name.
array_shift($argv);

// Look for the --outliers flag in order to normalize.
foreach ($argv as $key => $arg) {
    if ($arg == '--outliers' || substr($arg, 0, 9) == '--normali') {
        $normalize = true;
        unset($argv[$key]);
    }
}

if (empty($argv)) {
    echo 'Error: You need to specify the runs filenames without their .php sufix.' . PHP_EOL;
    exit(1);
}

if (count($argv) == 1) {
    echo 'Error: You should specify, at least, two runs to compare.' . PHP_EOL;
    exit(1);
}

// The filename without .php.
$timestamps = $argv;

$report = new report();
if (!$report->parse_runs($timestamps, $normalize)) {
    echo 'Error: The selected runs are not comparable.' . PHP_EOL;
    foreach ($report->get_errors() as $var => $error) {
        echo $var . ': ' . $error . PHP_EOL;
    }
    exit(1);
}

// Uses the thresholds specified in the .properties files.
if (!$report->calculate_big_differences()) {
    echo 'Error: No way to get the default thresholds...' . PHP_EOL;
    exit(1);
}
$branches = $report->get_big_differences();

// Report changes.
$exitcode = 0;
if ($branches) {
    foreach ($branches as $branchnames => $changes) {
        if (!empty($changes)) {
            echo "$branchnames" . PHP_EOL;
        }
        foreach ($changes as $state => $data) {
            foreach ($data as $var => $steps) {
                foreach ($steps as $stepname => $info) {
                    $normalizestr = $normalize ? '(Normalized) ' : '';
                    echo $normalizestr;
                    echo "- $state: $var - $stepname -> $info" . PHP_EOL;
                }
            }
        }

        if (!empty($changes['increment'])) {
            $exitcode = 1;
        }
    }
}

exit($exitcode);
