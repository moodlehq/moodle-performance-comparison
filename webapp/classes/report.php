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
     * Generates the report
     *
     * @param array $timestamps We will get the runs files from their timestamp (is part of the name).
     * @return void
     */
    public function make(array $timestamps) {

        krsort($timestamps);

        foreach ($timestamps as $timestamp) {

            if (!is_numeric($timestamp)) {
                die('Timestamps are supposed to be [0-9], cheater!');
            }

            // Creating the run object and parsing it.
            $run = new test_plan_run($timestamp);
            $run->parse_results();
            $this->runs[] = $run;
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

        // TODO: Merge with the declared ones.
        $options = array('title' => $var);

        $chart = new google_chart($chartid, $dataset, $chartdeclaration['class'], $options);
        google_charts_renderer::add($chart);

        // The DOM container.
        $width = $chartdeclaration['width'];
        $height = $chartdeclaration['height'];
        $this->containers[$chartdeclaration['id']][] = '<div id="' . $chartid . '" style="width: ' . $width . 'px; height: ' . $height . 'px;"></div>' . PHP_EOL;
    }

}
