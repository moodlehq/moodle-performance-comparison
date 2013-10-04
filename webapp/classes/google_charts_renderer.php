<?php


/**
 * Charts renderer.
 *
 * @package moodle-performance-comparison
 * @copyright 2013 David Monllaó
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class google_charts_renderer {

    const GOOGLE_APIS_URL = 'https://www.google.com/jsapi';

    /**
     * @var array List of charts to render.
     */
    protected static $charts;

    /**
     * Adds a chart.
     *
     * @param google_chart $chart
     * @return void
     */
    public static function add(google_chart $chart) {
        self::$charts[] = $chart;
    }

    /**
     * Renders all the charts once the JS libraries are loaded.
     *
     * @return void
     */
    public static function render() {

        echo '<head>';

        if (self::$charts) {
            echo self::load_google_chart_api();
            echo self::create_onload_callback();
        }

        echo '</head>';
    }

    /**
     * Returns the JS Google libs.
     *
     * @return string The JS includes
     */
    protected static function load_google_chart_api() {

        return '<script type="text/javascript" src="' . self::GOOGLE_APIS_URL . '"></script>' . PHP_EOL .
            '<script type="text/javascript">' . PHP_EOL .
            '  google.load("visualization", "1.0", {"packages":["corechart"]});' . PHP_EOL .
            '</script>' . PHP_EOL;
    }

    /**
     * Returns the callback function code.
     *
     * @return string Callback function contents including all charts.
     */
    protected static function create_onload_callback() {

        $output = '<script type="text/javascript">' . PHP_EOL .
            'function drawCharts() {' . PHP_EOL . PHP_EOL;

        foreach (self::$charts as $chart) {
            $output .= $chart->output_js();
        }

        $output .= '}' . PHP_EOL .
            'google.setOnLoadCallback(drawCharts);' . PHP_EOL .
            '</script>' . PHP_EOL;

        return $output;
    }

}
