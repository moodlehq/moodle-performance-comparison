Set of tools to compare Moodle test plan runs using different hardwares and configurations and compare results.

WARNING: Work in progress!! I only pushed it to upstream to prevent losing code and test the Moodle test plan generation side.

## Requirements
* JMeter - http://jmeter.apache.org/

## Installation
* Get the code
    + *cd /var/www* (or any other place, but accessible through a web server)
    + *git clone git://github.com/dmonllao/moodle-performance-comparison.git moodle-performance-comparison*
    + *cd moodle-performance-comparison*
* Configure the tool
    + *cp config.properties.dist config.properties*
    + Edit config.properties.dist with your own values
* Run the tests
    + Specify the paths to the files provided by Moodle's *tool_generator*
    + The group name should be specified to show runs results in the same graph to compare them (TODO)
    + The run description is useful to identify which settings or cache stores have been used in that run
    + *./testrunner.sh {/path/to/testplan_XXXX.jmx} {/path/to/users_XXXX.csv} {run_group_name} {run_description}*

## Usage
* Check the results
    + http://localhost/moodle-performance-comparison/index.php (change to your URL according to your checkout directory)
