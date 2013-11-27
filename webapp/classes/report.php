<?php


/**
 * A report representation
 *
 * @package moodle-performance-comparison
 * @copyright 2013 David Monllaó
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report {

    /**
     * @var Until where we can skip a false positive if it goes from or to 0.
     */
    const FALSE_POSITIVE_SCALAR_THRESHOLD = 2;

    /**
     * @var The path relative to the project root.
     */
    const RUNS_RELATIVE_PATH = 'runs/';

    /**
     * @var array The test_plan_run elements
     */
    protected $runs = array();

    /**
     * @var array Multidimensional array to store the charts containers.
     */
    protected $containers = array();

    /**
     * @var array How should look the charts look like.
     */
    protected $chartsdeclaration = array();

    /**
     * @var array Errors found when comparing runs data.
     */
    protected $errors = array();

    /**
     * @var array Big differences between the first run and the other ones.
     */
    protected $bigdifferences = array();

    /**
     * Loads the test plans.
     *
     * @return void
     */
    public function __construct() {

        $this->chartsdeclaration = array(
            'vars_bar' => array(
                'id' => 'vars_bar',
                'name' => 'Comparing variables step by step',
                'class' => 'BarChart',
                'orientation' => 'steporienteddataset',
                'perrow' => 3,
                'height' => 400,
                'width' => 500,
            ),
            'vars_area' => array(
                'id' => 'vars_area',
                'name' => 'Comparing variables step by step',
                'class' => 'AreaChart',
                'orientation' => 'steporienteddataset',
                'perrow' => 3,
                'height' => 400,
                'width' => 500,
            ),
            'grouped_steppedarea' => array(
                'id' => 'grouped_steppedarea',
                'name' => 'Grouped steps',
                'class' => 'SteppedAreaChart',
                'orientation' => 'runorienteddataset',
                'perrow' => 2,
                'height' => 500,
                'width' => 600,
            )
        );

        // Init the containers array.
        foreach ($this->chartsdeclaration as $chartid => $chartdata) {
            $this->containers[$chartid] = array();
        }

    }

    /**
     * Gets the runs data
     *
     * @param array $timestamps We will get the runs files from their timestamp (is part of the name).
     * @return bool Whether runs are comparable or not.
     */
    public function parse_runs(array $timestamps) {

        foreach ($timestamps as $timestamp) {

            if (!is_numeric($timestamp)) {
                die('Error: Timestamps are supposed to be [0-9]' . PHP_EOL);
            }

            // Creating the run object and parsing it.
            $run = new test_plan_run($timestamp);
            $run->parse_results();
            $this->runs[] = $run;
        }

        // Stop when runs are not comparables between them.
        if (!$this->check_runs_are_comparable()) {
            return false;
        }

        return true;
    }

    /**
     * Generates the report
     *
     * @param array $timestamps We will get the runs files from their timestamp (is part of the name).
     * @return bool False if problems were found.
     */
    public function make(array $timestamps) {

        // They come from the form in the opposite order.
        krsort($timestamps);

        // Gets the runs data and checks that it is comparable.
        if (!$this->parse_runs($timestamps)) {
            // No need to parse anything if it is not comparable.
            return false;
        }

        // Will be used to get runs generic data like the steps names, they are supposed to be
        // the same in all the runs, the UI should restrict the comparisons to comparable runs.
        $genericrun = & $this->runs[0];

        // Generating the data arrays.
        $vars = test_plan_run::$runvars;
        foreach ($vars as $var) {

            // TODO: Do something with the raw data.
            $sums = array();
            $raws = array();
            foreach ($this->runs as $runkey => $run) {
                list($sum, $raw) = $run->get_run_dataset($var);
                $sums[$runkey] = $sum;
                $raws[$runkey] = $raw;
            }

            // Getting all the data, ready for step-oriented charts and branch (run) oriented.
            $steporienteddataset = array();
            $runorienteddataset = array();

            $steporienteddataset[0] = array('Step');
            $runorienteddataset[0] = array('Run');
            foreach ($this->runs as $key => $run) {

                // Step-oriented dataset includes headers with runs info.
                // We init the headers here.
                $steporienteddataset[0][] = $run->get_run_info_string();

                // The header is also there.
                $datasetkey = $key + 1;

                $runorienteddataset[$datasetkey] = array($run->get_run_info_string());
                // And now we add the multiple runs data.
                foreach ($genericrun->get_run_steps() as $stepkey => $step) {
                    $runorienteddataset[$datasetkey][] = $sums[$key][$step];
                }

            }

            // Real runs data.
            foreach ($genericrun->get_run_steps() as $key => $step) {

                // Runs-oriented dataset includes headers with steps info
                // We init the headers here.
                $runorienteddataset[0][] = $step;

                // The header is also there.
                $datasetkey = $key + 1;

                $steporienteddataset[$datasetkey] = array($step);
                // And now we add the multiple runs data.
                foreach ($this->runs as $runkey => $run) {
                    $steporienteddataset[$datasetkey][] = $sums[$runkey][$step];
                }
            }

            $this->create_charts($var, $steporienteddataset, $runorienteddataset);
        }

        // We calculate differences between runs to list them.
        $this->calculate_big_differences();

        return true;
    }

    /**
     * Returns the runs info.
     *
     * @return array
     */
    public function get_run_files_info() {

        $runfiles = array();
        $runsvalues = array();

        $dir = __DIR__ . '/../../' . self::RUNS_RELATIVE_PATH;
        if ($dh = opendir($dir)) {
            while (($filename = readdir($dh)) !== false) {
                if ($filename != '.' && $filename != '..' && $filename != 'empty') {
                    $timestamp = preg_replace("/[^0-9]/","", $filename);
                    $runfiles[$timestamp] = new test_plan_run($timestamp);

                    // Get the params for filtering.
                    foreach (test_plan_run::$runparams as $param => $name) {

                        // In case some runs misses vars.
                        if (!empty($runfiles[$timestamp]->get_run_info()->{$param})) {
                            $value = $runfiles[$timestamp]->get_run_info()->{$param};
                        } else {
                            $value = 'Unknown';
                        }

                        if (empty($runsvalues[$param])) {
                            $runsvalues[$param] = array();
                        }
                        $runsvalues[$param][$value] = $value;
                    }

                    // Discard it if filters are set (once we got it's params).
                    if (!empty($_GET['filters'])) {
                        foreach ($_GET['filters'] as $param => $filteredvalue) {
                            // In case some runs misses vars.
                            if (!empty($runfiles[$timestamp]->get_run_info()->{$param})) {
                                $runvar = $runfiles[$timestamp]->get_run_info()->{$param};
                            } else {
                                $runvar = 'Unknown';
                            }
                            // Ensure it still exists.
                            if (!empty($filteredvalue) && !empty($runfiles[$timestamp]) &&
                                    $filteredvalue != $runvar) {
                                unset($runfiles[$timestamp]);
                                break;
                            }
                        }
                    }
                }
            }
            closedir($dh);
        }

        // Ordering them by timestamp DESC.
        krsort($runfiles);

        return array($runfiles, $runsvalues);
    }

    /**
     * Gets the big changes comparing the first run against the other runs results
     *
     * @param array $thresholds Format: array('bystep' => array('dbreads' => 1, 'dbwrites' => ...), 'total' => array('dbreads' => 2, 'dbwrites'...))
     * @return bool Whether it finished ok.
     */
    public function calculate_big_differences(array $thresholds = array()) {

        // Default values if nothing was provided.
        if (empty($thresholds)) {
            if (!$thresholds = $this->get_default_thresholds()) {
                return false;
            }
        }

        // We get the first run as a base to compare with the other runs
        $baserun = & $this->runs[0];
        $basetocompare = $baserun->get_run_dataset(false, 'totalsums');

        // Comparing each other run against the base one.
        $nruns = count($this->runs);
        // We skip the first one.
        for ($i = 1; $i < $nruns; $i++) {

            $run = & $this->runs[$i];

            $varaggregates = array_fill_keys(test_plan_run::$runvars, 0);

            $runtotals = $run->get_run_dataset(false, 'totalsums');
            foreach ($runtotals as $var => $steps) {

                $branchnames = 'between ' . $baserun->get_run_info_string() . ' and ' . $run->get_run_info_string();

                // Check differences between specific steps.
                foreach ($steps as $stepname => $value) {

                    if ($changed = $this->get_value_changes($basetocompare[$var][$stepname], $value, $thresholds['bystep'][$var])) {
                        list($state, $msg) = $changed;
                        $this->bigdifferences[$branchnames][$state][$var][$stepname] = $msg;
                    }

                    // Add it to the global $var sum
                    $varaggregates[$var] = $varaggregates[$var] + $value;
                }

                // Has the performance changed in general.
                if ($changed = $this->get_value_changes(array_sum($basetocompare[$var]), $varaggregates[$var], $thresholds['total'][$var])) {

                    list($state, $msg) = $changed;

                    // We unset all the steps to avoid showing too much info with a general increase / decrease is ok.
                    $this->bigdifferences[$branchnames][$state][$var] = array();
                    $this->bigdifferences[$branchnames][$state][$var]['All steps data combined'] = $msg;
                }
            }
        }

        return true;
    }

    /**
     * Gets the big differences between runs.
     *
     * @return array|bool List of big differences between runs. False if there are no runs.
     */
    public function get_big_differences() {
        return $this->bigdifferences;
    }

    /**
     * Hopefully your eyes will not burn after reading this function's code.
     *
     * Uses the .properties files looking for the threshold values. Gives preference to
     * $thresholds array over $groupedthreshold and $singlestepthreshold.
     *
     * @return array Format: array('bystep' => array('dbreads' => 1, 'dbwrites' => ...), 'total' => array('dbreads' => 2, 'dbwrites'...))
     */
    protected function get_default_thresholds() {

        $vars = array('groupedthreshold', 'singlestepthreshold', 'thresholds');

        // Ordered by preference.
        $files = array(
            __DIR__ . '/../../jmeter_config.properties',
            __DIR__ . '/../../defaults.properties'
        );

        foreach ($files as $file) {

            // Open the file and read each line.
            if ($fh = fopen($file, 'r')) {
                while (($line = fgets($fh)) !== false) {

                    foreach ($vars as $var) {
                        $return = $this->extract_threshold_value($var, $line);
                        if ($return && empty($$var)) {
                            $$var = $return;
                        }
                    }
                }
            }
        }

        // There will always be a value in defaults.properties.
        if (empty($groupedthreshold) || empty($singlestepthreshold)) {
            die('Error: defaults.properties thresholds values can not be found' . PHP_EOL);
        }

        // Preference to $thresholds.
        if (!empty($thresholds)) {
            return json_decode($thresholds, true);
        }

        // Generate the default thresholds array.
        $thresholds = array('bystep' => array(), 'total' => array());
        foreach (test_plan_run::$runvars as $var) {
            $thresholds['bystep'][$var] = $singlestepthreshold;
            $thresholds['total'][$var] = $groupedthreshold;

        }

        return $thresholds;
    }

    /**
     * Extracts the property value from $line
     *
     * @param string $var
     * @param string $line
     * @return string The var value
     */
    protected function extract_threshold_value($var, $line) {

        // It can be commented.
        if (strpos($line, $var . '=') !== 0) {
            return false;
        }

        // Just in case an extra conditional as is the user the one that enters the value
        if (preg_match("/$var='?([^']*)'?/", $line, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     * Describes the changes between two values using the provided threshold
     *
     * @param float $from
     * @param float $to
     * @param float $threshold
     * @return bool|string The string describing the changes or false if there are no changes
     */
    protected function get_value_changes($from, $to, $threshold) {

        // Different treatment for near-zero values.
        // If there are real problems the sum of all the steps will spot them.
        if ($to == 0 && $from == 0) {
            // Skip it.
            return false;
        } else if ($to == 0) {
            if ($from > self::FALSE_POSITIVE_SCALAR_THRESHOLD) {
                // It is a real decrease if goes to 0.
                return array('decrease', 'from ' . $from . ' to 0');
            } else {
                // Ignore the change.
                return false;
            }
        } else if ($from == 0) {
            if ($to > self::FALSE_POSITIVE_SCALAR_THRESHOLD) {
                // It is a increment if it was 0 and now is too much.
                return array('increment', 'from 0 to ' . $to);
            } else {
                // Ignore the change.
                return false;
            }
        }

        $difference = ($to * 100) / $from;

        if ($difference > 100) {
            $change = round($difference - 100, 2);
        } else {
            $change = round(100 - $difference, 2);
        }

        if (($difference - $threshold) > 100) {
            return array('increment', $change . '% worst');
        } else if (($difference + $threshold) < 100) {
            return array('decrease', $change . '% better');
        }

        return false;
    }

    /**
     * Getter for the charts declaration
     *
     * @return array
     */
    public function get_charts_declaration() {
        return $this->chartsdeclaration;
    }

    /**
     * Returns the test_plan_run objects
     *
     * @return array
     */
    public function get_runs() {
        return $this->runs;
    }

    /**
     * Returns the charts containers.
     *
     * @return array
     */
    public function get_containers() {
        return $this->containers;
    }

    /**
     * Returns wheter if errors have been found.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Returns true if the runs are comparable between them.
     *
     * @return bool True if they are comparable
     */
    protected function check_runs_are_comparable() {

        $values = array();
        foreach (test_plan_run::$runcomparablevars as $var) {
            foreach ($this->runs as $run) {

                $runvalue = $run->get_run_info()->$var;

                // All should match the fist one.
                if (empty($values[$var])) {
                    $values[$var] = $runvalue;
                }

                if ($values[$var] != $runvalue) {
                    $this->errors[$var] = "You can not compare runs with a different $var value";
                }
            }
        }

        if (!empty($this->errors)) {
            return false;
        }

        return true;
    }

    /**
     * Creates the charts as defined in the constructor.
     *
     * @param string $var
     * @param array $steporienteddataset
     * @param array $runorienteddataset
     * @return void
     */
    protected function create_charts($var, $steporienteddataset, $runorienteddataset) {
        foreach ($this->chartsdeclaration as $chartid => $chartdeclaration) {
            $this->create_chart($var, ${$chartdeclaration['orientation']}, $chartdeclaration);
        }
    }
    /**
     * Creates a chart and adds it to the lists.
     * @param string $var
     * @param array $dataset
     * @param array $charttypeid
     * @return void
     */
    protected function create_chart($var, $dataset, $chartdeclaration) {

        $chartid = $var . '_' . $chartdeclaration['id'];

        // TODO: Merge with the declared ones to allow specific behaviours.
        $options = array('title' => $var);

        $chart = new google_chart($chartid, $dataset, $chartdeclaration['class'], $options);
        google_charts_renderer::add($chart);

        // The DOM container.
        $width = $chartdeclaration['width'];
        $height = $chartdeclaration['height'];
        $this->containers[$chartdeclaration['id']][] = '<div id="' . $chartid . '" style="width: ' . $width . 'px; height: ' . $height . 'px;"></div>' . PHP_EOL;
    }

}
