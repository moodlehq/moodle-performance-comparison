Tools to compare Moodle sites performance.


## Purpose

They can be used to compare:

* Performance before/after applying a patch
* Two different branches performance
* Different configurations and cache stores configurations
* Different hardware
* Web, database and other services tunning
* Also works restoring your site sql dumps rather than using the fixed generated dataset


## Features

* Clean site installation
* Fixed data set generation with courses, users, enrolments, module instances...
* Web and database warm-up processes included in the test plan (results not collected)
* JMeter test plan generation from course contents
* JMeter runs gathering results about moodle performance data (database reads/writes, memory usage...)
* Runs results comparison

There are scripts for both the web server and the JMeter server sides.

* In case they are both in the same server you just need to clone the project once.
* In case they are in different servers you need to clone the project in both servers
    + test_runner.sh along with jmeter_config.properties will be used in the server hosting JMeter
    + before_run_setup.sh, after_run_setup.sh and restart_services.sh will be used in the web server

Most of the code to display the tests results comes from Sam Hemelryk's tool (https://github.com/samhemelryk/moodle-jmeter-perfcomp) this is an
adaptation to run Moodle's *tool_generator* test plans.


## Requirements
* JMeter - http://jmeter.apache.org/


## Installation

The installation process differs depending whether you have both the web server and JMeter in the same computer or not.

### Web and JMeter servers in the same computer (usually a development computer)
* Get the code
    + *cd /var/www* (or any other place, but accessible through a web server, not a public one please, read [security](#security) below)
    + *git clone git://github.com/dmonllao/moodle-performance-comparison.git moodle-performance-comparison*
    + *cd moodle-performance-comparison*
* Configure the tool
    + *cp webserver_config.properties.dist webserver_config.properties*
    + Edit webserver_config.properties with your own values
    + *cp jmeter_config.properties.dist jmeter_config.properties*
    + Edit jmeter_config.properties with your own values

### Web server and JMeter running from a different server
* Get the code in the web server
    + *cd /var/www* (or any other place, but accessible through a web server, not a public one please, read [security](#Security) below)
    + *git clone git://github.com/dmonllao/moodle-performance-comparison.git moodle-performance-comparison*
    + *cd moodle-performance-comparison*
* Get the code in the JMeter server
    + *cd /wherever/you/want*
    + *git clone git://github.com/dmonllao/moodle-performance-comparison.git moodle-performance-comparison*
    + *cd moodle-performance-comparison*
* Configure the tool in the web server
    + *cp webserver_config.properties.dist webserver_config.properties*
    + Edit webserver_config.properties with your own values
* Configure the tool in the JMeter server
    + *cp jmeter_config.properties.dist jmeter_config.properties*
    + Edit jmeter_config.properties with your own values


## Usage

It also differs depending whether you have both web server and JMeter in the same computer or not. Here there is another alternative, you can load your sql dump instead of having a clean brand new site with a fixed dataset, so you can run the generated test plan using real site generated data.

The groupname and description arguments of test_runner.sh are useful to identify the run when comparing results, you can use it to set the branch name, the settings you used or whatever will help you identify which run is it.

Note that you can run the tests as many times as you want, you just need to run after_run_setup.sh and restart_services.sh before running test_runner.sh every time to clean up the site.

### Web and JMeter servers in the same computer (usually a development computer)
* Generate the data and run the tests
    + cd /path/to/moodle-performance-comparison
    + *./before_run_setup.sh {XS|S|M|L|XL|XXL}*
    + Change site settings if necessary according to what you are comparing (you can overwrite the database dump if you don't want to set it again)
    + *./restart_services.sh*
    + *./test_runner.sh* {groupname} {descriptioname}
    + *./after_run_setup.sh*
    + Change site settings if necessary according to what you are comparing
    + *./restart_services.sh*
    + *./test_runner.sh* {groupname} {descriptioname}
* Check the results
    + http://localhost/moodle-performance-comparison/index.php (change to your URL according to your configuration)

### Web server and JMeter running from a different server
 Same process than when using a single computer but:

* Configuring jmeter_config.properties in the server hosting JMeter and webserver_config.properties in the web server
*  Running test_runner.sh in the server hosting JMeter and all other commands in the web server
* JMeter needs to download the test plan and the test users from the web server, so rather than:
    + *./test_runner.sh* {groupname} {descriptioname}
* Do
    + *wget http://webserver/moodle/site/path/testusers.csv http://webserver/moodle/site/path/testplan.jmx*
    + *./test_runner.sh* {groupname} {descriptioname} testusers.csv testplan.jmx

### Using your own sql dump
The installation is the same, it also depends on if you use the same computer for both web server and JMeter or not, but the usage changes when you want to use your own sql dump and is not as easy to automate as you need to specify which course do you want to use as target course and you can not use before_run_setup.sh to generate the test plan and test_files.properties.
* *cd /webserver/path/to/moodle-performance-comparison*
* Restore your dataroot
* Restore your database
* Generate the test plan. You need to provide the shortname of the course that will be tested
    + *cd moodle/*
    + *php admin/tool/generator/cli/maketestsite.php --size="THESIZEYOUWANT" --shortname="TARGETCOURSESHORTNAME" fixeddataset --bypasscheck --updateuserspassword*
* Download the test plan and the test users. The URLs are provided by maketestsite.php in the previous step, before the performance info output begins.
    + *wget http://webserver/url/provided/by/maketestsite.php/in/the/previous/step/testplan_NNNNNNNNNNNN_NNNN.jmx -O testplan.jmx*
    + *wget http://webserver/url/provided/by/maketestsite.php/in/the/previous/step/users_NNNNNNNNNNNN_NNNN.jmx -O testusers.csv*
* Create moodle-performance-comparison/test_files.properties
    + *cd ../*
    + Create a new /path/to/moodle-performance-comparison/test_files.properties file with the following content:

>    testplanfile="/absolute/path/to/testplan.jmx"
>    datarootbackup="/absolute/path/to/the/dataroot/backup/directory"
>    testusersfile="/absolute/path/to/testusers.csv"
>    databasebackup="/absolute/path/to/the/database/backup.sql"

* Continue the normal process from restart_services.sh -> test_runner.sh -> after_run_setup.sh -> ....

## Security

This tool in only intended to be used in development/testing environments inside the local network, it would be insecure to expose the project root in a public accessible web server, the same only exposing moodle/ directory:

* Database connection data and other sensitive data is stored in properties files (you can change permissions)
* It uses default sugar passwords (you can change the defaults in webserver_config.properties)
* Stores test users credentials in Moodle's wwwroot (you can change permissions)
* In general all files permissions are non secure at all (you can change permissions)
* Other things I probably forgot, to resume, don't do it unless you are sure what you are doing


## Troubleshooting
* You can find JMeter logs in logs/
* You can find runs results in runs_samples/ and the php arrays generated from them in runs/
* The generated .jtl files can be big. Don't hesitate to get rid of them if you don't need them for extra analytic purposes.
* Same with $backupsdir/ contents, if you run before_run_setup.sh many time you will have a looot of hd space wasted
