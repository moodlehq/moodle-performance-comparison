<?php

/**
 * Representation of a chart.
 *
 * Generates the required JS to create a chart.
 *
 * @package moodle-performance-comparison
 * @copyright 2013 David Monllaó
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class google_chart {

    /**
     * @var string The DOM id node to add the chart in.
     */
    protected $chartid;

    /**
     * @var string The chart type.
     */
    protected $charttype;

    /**
     * @var array The chart data.
     */
    protected $dataset;

    /**
     * @var array Options passed to the chart.
     */
    protected $chartoptions;

    /**
     * Sets the chart info.
     *
     * @param string $chartid
     * @param array $data
     * @param string $charttype
     * @param array $chartoptions
     * @return void
     */
    public function __construct($chartid, array $data, $charttype, $chartoptions = false) {

        // Defaults to ColumnChart.
        if (empty($charttype)) {
            $charttype = 'ColumnChart';
        }

        $this->chartid = $chartid;
        $this->charttype = $charttype;
        $this->dataset = $data;
        $this->chartoptions = $chartoptions;
    }

    /**
     * Returns the generated Javascript to display the chart.
     *
     * @return string The generated JS.
     */
    public function output_js() {

        $output = '';

        // Chart data set.
        $output .= 'var data = google.visualization.arrayToDataTable([' . PHP_EOL;
        foreach ($this->dataset as $row) {
            $output .= '[';

            // Passing to JS strings or numbers.
            foreach ($row as $key => $value) {
                if (is_int($value) || is_float($value)) {
                    $row[$key] = $value;
                } else {
                    $row[$key] = "'" . addslashes($value) . "'";
                }
            }
            $output .= implode(", ", $row);
            $output .= '],' . PHP_EOL;
        }
        $output .= ']);' . PHP_EOL;

        // Chart options.
        if ($this->chartoptions) {
            $output .= "var options = " . json_encode($this->chartoptions) . ";";
        } else {
            $output .= "var options = null;";
        }
        $output .= PHP_EOL;

        // Draw the chart.
        $output .= "var chart = new google.visualization.{$this->charttype}(document.getElementById('{$this->chartid}'));" . PHP_EOL .
            "chart.draw(data, options);" . PHP_EOL . PHP_EOL;

        return $output;
    }

}
