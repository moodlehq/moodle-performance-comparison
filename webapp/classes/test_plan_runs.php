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
    public static $runvars = array('dbreads', 'dbwrites', 'dbquerytime', 'memoryused', 'filesincluded', 'serverload',
            'sessionsize', 'timeused');

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
     * @var array Vars that needs to be equals across runs to be comparable.
     */
    public static $runcomparablevars = array('users', 'rampup', 'size', 'loopcount', 'throughput', 'baseversion');

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
     * @var array Average of sums per (users * loopcount).
     */
    protected $averagesums = array();

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
    public function __construct($timestamp, $normalize = false) {
        $this->rundata = $this->include_run($timestamp);

        // If we want to normalize data, go for it. Basically it
        // will get clear - really extreme - outliers and change
        // and replace them by the avg value, so they don't
        // cause any distorsion when comparing totals and/or averages.
        //
        // Note that other outliner detection may have been used,
        // directly removing them... before analysis, but given
        // how this class calculates totals and averages...
        // see parse_results(), we must keep them within the rundata,
        if ($normalize) {
            $this->rundata = $this->normalize();
        }
    }

    /**
     * Outliner detection and replacement function.
     *
     * Use this to find all the outliners (by step and variable)
     * present in rundata. And normalize them to be the average
     * value, so they don't introduce distorsions when looking
     * for changes anymore.
     */
    private function normalize() {
        // Let's get all the values.
        $runvalues = [];
        foreach ($this->rundata->results as $thread) {
            foreach ($thread as $threadstep) {
                $stepname = trim($threadstep['name']);
                if (!isset($runvalues[$stepname])) {
                    $runvalues[$stepname] = [];
                }
                foreach (self::$runvars as $var) {
                    if (!isset($runvalues[$stepname][$var])) {
                        $runvalues[$stepname][$var] = [];
                    }
                    $runvalues[$stepname][$var][] = $threadstep[$var];
                }
            }
        }
        // Now, for each step and variable, calculate their outlier limits.
        $statvalues = [];
        foreach ($runvalues as $stepname => $runvalues) {
            $statvalues[$stepname] = [];
            foreach ($runvalues as $runvar => $values) {
                $statvalues[$stepname][$runvar] = new stdClass();
                list($lower, $upper) = calculate_outlier_limits($values);
                $avg = array_sum($values) / count($values);
                $statvalues[$stepname][$runvar]->lower = $lower;
                $statvalues[$stepname][$runvar]->upper = $upper;
                $statvalues[$stepname][$runvar]->pseudoavg = ($lower === $upper) ? $lower : $avg;
            }
        }
        // Let's apply the outlier limits to all the rundata, normalizing them to pseudoavg
        // to avoid them to keep disturbing.
        foreach ($this->rundata->results as $threadkey => $thread) {
            foreach ($thread as $threadstepkey => $threadstep) {
                $stepname = trim($threadstep['name']);
                foreach (self::$runvars as $runvar) {
                    $value = $this->rundata->results[$threadkey][$threadstepkey][$runvar];
                    // If the value is beyond limits, apply pseudoavg to the original rundata.
                    if ($value < $statvalues[$stepname][$runvar]->lower || $value > $statvalues[$stepname][$runvar]->upper) {
                        $this->rundata->results[$threadkey][$threadstepkey][$runvar] = $statvalues[$stepname][$runvar]->pseudoavg;
                    }
                }
            }
        }

        return $this->rundata;
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
            $this->averagesums[$var] = array();
        }

        // Adding all threads info.
        foreach ($this->rundata->results as $thread) {
            foreach ($thread as $threadstep) {

                $stepname = trim($threadstep['name']);
                if (!in_array($stepname, $this->steps)) {
                    $this->steps[] = $stepname;
                }

                // Add the thread data to the totals.
                foreach (self::$runvars as $var) {

                    if (!isset($threadstep[$var])) {
                        unset($this->totalsums[$var]);
                        unset($this->rawtotals[$var]);
                        unset($this->averagesums[$var]);
                        continue;
                    }
                    // Init if is empty.
                    if (empty($this->totalsums[$var][$stepname])) {
                        $this->totalsums[$var][$stepname] = 0;
                    }

                    // Init if is empty (yes, it could be together with the one above).
                    if (empty($this->rawtotals[$var][$stepname])) {
                        $this->rawtotals[$var][$stepname] = array();
                    }

                    if ($var === 'sessionsize') {
                        // Strip out the KB, MB... part (it is language dependant, so better to strip out
                        // everything that is not a number of \.).
                        $threadstep[$var] = preg_replace('/[^0-9\.]/', '', $threadstep[$var]);
                    }

                    $this->totalsums[$var][$stepname] = $this->totalsums[$var][$stepname] + $threadstep[$var];
                    $this->rawtotals[$var][$stepname][] = $threadstep[$var];
                }
            }
        }
        // Average the sum per (loopcount * users).
        $averagefactor = $this->rundata->loopcount * $this->rundata->users;
        foreach (self::$runvars as $var) {
            if (!empty($this->totalsums[$var])) {
                foreach ($this->totalsums[$var] as $key => $stepsum) {
                    $this->averagesums[$var][$key] = $stepsum / $averagefactor;
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
     * Return run variables list.
     *
     * @return array
     */
    public function get_run_var_names() {
        return array_keys($this->totalsums);
    }

    /**
     * Returns an array with the run data grouped by var and step and the raw results.
     *
     * @param string $var The var to return or false to get all vars.
     * @param string $dataset The required dataset, false to get the both of them. 'totalsums' or 'rawtotals'
     * @return array Depending on the provided params
     */
    public function get_run_dataset($var = false, $dataset = false) {

        // Return all.
        if ($var === false) {
            $totalsums = & $this->totalsums;
            $rawtotals = & $this->rawtotals;
            $averagesums = & $this->averagesums;
        } else {
            $totalsums = & $this->totalsums[$var];
            $rawtotals = & $this->rawtotals[$var];
            $averagesums = & $this->averagesums[$var];
        }

        // Returning just one dataset if it was specified.
        if ($dataset != false) {
            if (!isset($$dataset)) {
                die('Error: The "' . $dataset . '" provided dataset does not exist' . PHP_EOL);
            }

            return $$dataset;
        }

        return array($totalsums, $rawtotals, $averagesums);
    }

    /**
     * Deletes the current run.
     *
     * It has been confirmed in client side.
     * @return bool
     */
    public function delete() {

        $filepath = __DIR__ . '/../../runs/' . $this->get_filename();

        if (!is_writable($filepath)) {
            echo "No permissions to delete $filepath file.<br/><br/>Probably the file was created " .
                "before https://github.com/moodlehq/moodle-performance-comparison/issues/10 " .
                "was integrated; delete it manually or change the file permissions.<br/>";
            return false;
        }

        return unlink($filepath);
    }

    /**
     * Returns the specified file
     *
     * PHP files are interpreted, so we return a file from tmp.
     *
     * @return void
     */
    public function download() {

        $filepath = __DIR__ . '/../../runs/' . $this->get_filename();

        if (!file_exists($filepath)) {
            die("The specified $filepath file does not exist");
        }

        $downloadedfilename = basename($filepath);
        header('Content-Description: File Transfer');
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename=' . $downloadedfilename);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        ob_clean();
        flush();
        readfile($filepath);
        exit;
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
            die('Error: The selected file "' . $this->filename . '" does not exists' . PHP_EOL);
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
