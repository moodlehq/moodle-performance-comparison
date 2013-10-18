<?php


/**
 * Representation of a run.
 *
 * @package  moodle-performance-comparison
 * @copyright 2013 David Monllaó
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_plan_run {

    /**
     * @var array Vars we can get from the run.
     */
    public static $runvars = array('dbreads', 'dbwrites', 'memoryused', 'filesincluded', 'serverload', 'sessionsize', 'timeused');

    /**
     * @var array Params of a run. Not including the description nor timestamp.
     */
    public static $runparams = array(
        'group' => 'Group',
        'sitebranch' => 'Major Moodle branch',
        'users' => 'Number of users',
        'siteversion' => 'Version',
        'sitecommit' => 'Commit',
        'rampup' => 'Ramp-up period',
        'size' => 'Size',
        'loopcount' => 'Number of loops',
        'throughput' => 'Throughput',
        'baseversion' => 'Base version'
    );

    /**
     * @var stdClass Run data, including the threads results.
     */
    protected $rundata;

    /**
     * @var string The run file name.
     */
    protected $filename;

    /**
     * @var array An array step-and-var-based (eg: [dbreads][Login]).
     */
    protected $totalsums = array();

    /**
     * @var array Raw data of the runs.
     */
    protected $rawtotals = array();


    /**
     * @var array The test steps names.
     */
    protected $steps = array();

    /**
     * Gets the run data from the run PHP file.
     *
     * @param int $timestamp
     * @return void
     */
    public function __construct($timestamp) {
        $this->rundata = $this->include_run($timestamp);
    }

    /**
     * Returns the run filename.
     * @param bool $includeextension
     * @return string
     */
    public function get_filename($includeextension = true) {

        if (!$includeextension) {
            return basename($this->filename, '.php');
        }

        return $this->filename;
    }

    /**
     * Returns info to identify the run.
     *
     * @return string
     */
    public function get_run_info_string() {

        $commitinfo = $this->rundata->sitecommit;
        if (strlen($commitinfo) > 20) {
            $commitinfo = substr($commitinfo, 0, 17) .'...';
        }

        // Cutting the commit as it can be very long.
        return $this->rundata->rundesc . ' - ' .
            $this->rundata->group . ' - ' .
            $this->rundata->sitebranch .
            ' (' . $commitinfo . ')';
    }

    /**
     * Returns all the info about the run.
     *
     * @return string
     */
    public function get_run_info_extended_string() {

        $time = date('H:i D dS M Y', $this->rundata->timestamp);

        return $this->rundata->rundesc . ' - ' . $this->rundata->group . ', ' .
            $this->rundata->size . ' size, ' .
            'Moodle ' . $this->rundata->sitebranch . ' ' .
            '(' . $this->rundata->siteversion . ', ' . $this->rundata->sitecommit . ') ' .
            '(' . $this->rundata->users . ' users * ' . $this->rundata->loopcount . ' loops, ' .
            'rampup=' . $this->rundata->rampup . ' throughput=' . $this->rundata->throughput . ')' .
            ' ' . $time;
    }

    /**
     * Parses the run results to obtain global numbers.
     *
     * @return void
     */
    public function parse_results() {

        // Init.
        foreach (self::$runvars as $var) {
            $this->totalsums[$var] = array();
            $this->rawtotals[$var] = array();
        }

        // Adding all threads info.
        foreach ($this->rundata->results as $thread) {
            foreach ($thread as $threadstep) {

                $stepname = trim($threadstep['name']);
                if (!in_array($stepname, $this->steps)) {
                    $this->steps[] = $stepname;
                }

                // Init if is empty.
                if (empty($this->rawtotals[$stepname])) {
                    $this->rawtotals[$stepname] = array();
                    foreach (self::$runvars as $key) {
                        $this->rawtotals[$stepname][$key] = array();
                    }
                }

                // Add the thread data to the totals.
                foreach (self::$runvars as $var) {

                    // Init if is empty.
                    if (empty($this->totalsums[$var][$stepname])) {
                        $this->totalsums[$var][$stepname] = 0;
                    }

                    // Init if is empty (yes, it could be together with the one above).
                    if (empty($this->rawtotals[$stepname])) {
                        $this->rawtotals[$var][$stepname] = array();
                    }

                    $this->totalsums[$var][$stepname] = $this->totalsums[$var][$stepname] + $threadstep[$var];
                    $this->rawtotals[$var][$stepname][] = $threadstep[$var];
                }
            }
        }
    }

    /**
     * Returns all run info.
     *
     * @return stdClass
     */
    public function get_run_info() {
        return $this->rundata;
    }

    /**
     * Returns the steps names.
     *
     * @return array
     */
    public function get_run_steps() {
        return $this->steps;
    }

    /**
     * Returns an array to pass to generate the chart from it.
     *
     * @param string $var
     * @return array
     */
    public function get_run_dataset($var) {
        return array($this->totalsums[$var], $this->rawtotals[$var]);
    }

    /**
     * Gets the run data.
     *
     * @param int $timestamp
     * @return array
     */
    protected function include_run($timestamp) {

        $this->filename = $timestamp . '.php';
        $filepath = __DIR__ . '/../../' . report::RUNS_RELATIVE_PATH . $this->filename;
        if (!file_exists($filepath)) {
            die('The selected file "' . $this->filename . '" does not exists');
        }

        include($filepath);

        $runinfovars = array(
            'host', 'sitepath', 'group', 'rundesc', 'users', 'loopcount',
            'rampup', 'throughput', 'size', 'baseversion', 'siteversion', 'sitebranch', 'sitecommit'
        );

        // Object containing everything.
        $rundata = new stdClass();
        foreach ($runinfovars as $var) {

            // In case runs don't have all vars defined.
            if (empty($$var)) {
                $$var = 'Unknown';
            }
            $rundata->{$var} = $$var;
        }
        // Removing miliseconds.
        $rundata->timestamp = substr($timestamp, 0, 10);
        $rundata->results = $results;

        return $rundata;
    }

}
