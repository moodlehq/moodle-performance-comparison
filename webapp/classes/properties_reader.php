<?php

/**
 * Properties files reader.
 *
 * @package moodle-performance-comparison
 * @copyright 2013 David Monllaó
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class properties_reader {


    /**
     * Gets the vars values from the bash properties files.
     *
     * Allows passing a string in case you are only interested
     * in one property.
     *
     * @param array $vars Array of vars you are interested on.
     * @return array The var that are not found or it's value is '' will not be returned.
     */
    public static function get($vars) {

        // Single property.
        if (is_scalar($vars)) {
            $vars = array($vars);
        }

        $propertiesvalues = array();

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
                        $return = self::extract_properties_file_value($var, $line);
                        if ($return && empty($propertiesvalues[$var])) {
                            $propertiesvalues[$var] = preg_replace('/[^0-9\.]/', '', $return);
                        }
                    }
                }
            }
        }

        return $propertiesvalues;
    }

    /**
     * Extracts the property value from $line.
     *
     * @param string $var
     * @param string $line
     * @return string The var value
     */
    protected static function extract_properties_file_value($var, $line) {

        // It can be commented.
        if (strpos($line, $var . '=') !== 0) {
            return false;
        }

        // Just in case an extra conditional as is the user the one that enters the value.
        if (preg_match("/$var='?([^']*)'?/", $line, $matches)) {
            return $matches[1];
        }

        return false;
    }

}
