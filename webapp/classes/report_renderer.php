<?php


/**
 * Reports renderer.
 *
 * @package moodle-performance-comparison
 * @copyright 2013 David Monllaó
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_renderer {

    protected $report;

    /**
     * Adds the report data.
     *
     * @param report $report
     * @return void
     */
    public function __construct(report $report) {
        $this->report = $report;
    }

    /**
     * Outputs wrapper.
     *
     * @return void
     */
    public function render() {
        echo $this->output_head();

        echo $this->output_form();
        echo $this->output_runs_info();
        echo $this->output_charts_containers();
    }

    /**
     * All JS & CSS.
     *
     * @return string HTML
     */
    protected function output_head() {

        google_charts_renderer::render();
        return '<link rel="stylesheet" type="text/css" href="webapp/styles.css" />';
    }

    /**
     * Outputs the form.
     *
     * @return string Form HTML
     */
    protected function output_form() {

        $runs = $this->report->get_run_files_info();

        // Select runs form.
        $output = '<form method="get">';

        // Restrict the select size.
        $sizestr = '';
        $sizelimit = 20;
        if (count($runs) > $sizelimit) {
            $sizestr = ' size="' . $sizelimit . '" ';
        } else {
            $sizestr = ' size="' . count($runs) . '" ';
        }

        $output .= '<select name="timestamps[]" ' . $sizestr . ' multiple="multiple">' . PHP_EOL;
        foreach ($runs as $run) {
            $output .= '<option value="' . $run->get_run_info()->timestamp . '">' . $run->get_run_info_extended_string() . '</option>';
        }
        $output .= '</select>';

        $output .= '<br/><br/><input type="submit" value="View comparison"/>';
        $output .= '</form>';
        return $output;
    }

    /**
     * Outputs all the runs info in a one row table.
     *
     * @return string HTML
     */
    protected function output_runs_info() {

        if (!$this->report->get_runs()) {
            return false;
        }

        $runinfo = array();
        foreach ($this->report->get_runs() as $run) {
            $runinfo[] = $this->get_info_container($run->get_run_info());
        }

        return $this->create_table('Runs information', $runinfo, count($runinfo));
    }

    /**
     * Outputs the charts containers.
     *
     * @return string HTML
     */
    protected function output_charts_containers() {

        if (!$this->report->get_runs()) {
            return false;
        }

        $output = '';

        // Number of columns per row.
        $containers = $this->report->get_containers();
        foreach ($this->report->get_charts_declaration() as $chartsdeclaration) {
            $output .= $this->create_table($chartsdeclaration['name'], $containers[$chartsdeclaration['id']], $chartsdeclaration['perrow']);
        }

        return $output;
    }

    /**
     * Returns a run's info.
     *
     * @param stdClass $runinfo
     * @return string HTML
     */
    protected function get_info_container(stdClass $runinfo) {

        $container = '<h2>' . $runinfo->rundesc . '</h2><ul>' . PHP_EOL;
        foreach ($runinfo as $varname => $value) {
            if (is_scalar($value)) {
                $container .= '<li><b>' . $varname . ': </b>' . $value . '</li>';
            }
        }

        return $container;
    }

    /**
     * Generates the HTML of a table based on the provided cells.
     *
     * @param string A title to show as the table heading
     * @param array $cells The cells data
     * @param int $nrows The number of cols per row
     * @param string $class The CSS class
     * @return string HTML
     */
    protected function create_table($title, $cells, $ncols, $class = 'container') {

        $output = '<div class="section">' . PHP_EOL;
        $output .= '<h2>' . $title . '</h2>' . PHP_EOL;
        $output .= '<table class="' . $class . '"><tr>' . PHP_EOL;
        for ($i = 0; $i < count($cells); $i++) {
            $output .= '<td>' . $cells[$i] . '</td>';
            if (($i + 1) % $ncols == 0) {
                $output .= '</tr><tr>';
            }
        }
        $output .= '</tr></table>' . PHP_EOL;
        $output .= '</div>';

        return $output;
    }

}
