Tools to compare Moodle test plan runs using different hardwares and configurations and compare results.

Most of the code comes from Sam Hemelryk's tool (https://github.com/samhemelryk/moodle-jmeter-perfcomp) this is an
adaptation to run Moodle's *tool_generator* test plans.

## Requirements
* JMeter - http://jmeter.apache.org/

## Installation
* Get the code
    + *cd /var/www* (or any other place, but accessible through a web server)
    + *git clone git://github.com/dmonllao/moodle-performance-comparison.git moodle-performance-comparison*
    + *cd moodle-performance-comparison*
* Configure the tool
    + *cp config.properties.dist config.properties*
    + Edit config.properties with your own values
* Run the tests
    + Specify the paths to the files provided by Moodle's *tool_generator*
    + The group name should be specified to show runs results in the same graph to compare them (TODO)
    + The run description is useful to identify which settings or cache stores have been used in that run
    + *./testrunner.sh {/path/to/testplan_XXXX.jmx} {/path/to/users_XXXX.csv} {run_group_name} {run_description}*

## Usage
* Check the results
    + http://localhost/moodle-performance-comparison/index.php (change to your URL according to your checkout directory)
