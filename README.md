Set of shell scripts to run Moodle performance tests using different hardwares and configurations and compare results.

WARNING: Work in progress!! I only pushed it to upstream to prevent losing code.

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
* Install the site
    + *./installsite.sh*
* Set the site configuration (advanced settings, cache stores...) that you want to run the test plan with
    + Log in with admin/admin
    + Restrict your actions to site setup to avoid generating data that can interfere with the test plan results
    + Leave debug & debugdisplay values as specified in config.php, when enabled there are more db queries
* Generate data to populate the database
    + *./generatedata.sh {small|medium|big}*
* Run the tests
    + The group name should be specified to show runs results in the same graph to compare them
    + The run description is useful to identify which settings or cache stores have been used in that run
    + *./runtestplan.sh {run_group_name} {run_description}*

## Usage (TODO)
* Check the results
    + http://localhost/moodle-performance-comparison/results.php (change to your URL according to config.properties)

## TODO
* Remove WARNING
* Add info in the commands --help.
* Move dmonllao/moosh.git to moodlehq/moosh.git if we add/modify commands.
* Add a reset site (or drop & install) script.
