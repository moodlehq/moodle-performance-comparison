Tools to compare Moodle sites and/or branches performance.


## Purpose

They can be used to compare:

* Performance before/after applying a patch
* Two different branches performance
* Different configurations and cache stores configurations
* Different hardware
* Web, database and other services tunning
* Also works restoring your site sql dumps rather than using the fixed generated dataset, more info in [Using your own sql dump Moodle 2.5 onwards](#using-your-own-sql-dump-moodle-25-onwards) or  [Using your own sql dump (before Moodle 2.5)](#using-your-own-sql-dump-before-moodle-25)


## Features

* Clean site installation (from Moodle 2.5 onwards)
* Fixed data set generation with courses, users, enrolments, module instances... (from Moodle 2.5 onwards)
* JMeter test plan generation from course contents (from Moodle 2.5 onwards)
* Web and database warm-up processes included in the test plan (results not collected)
* JMeter runs gathering results about moodle performance data (database reads/writes, memory usage...)
* Runs results comparison

There are scripts for both the web server and the JMeter server sides.

* In case they are both in the same server you just need to clone the project once.
* In case they are in different servers you need to clone the project in both servers
    + test_runner.sh along with jmeter_config.properties will be used in the server hosting JMeter
    + before_run_setup.sh, after_run_setup.sh and restart_services.sh will be used in the web server


## Requirements
* MySQL or PostgreSQL
* Git
* cURL
* PHP 5.3
* Java 6 or later
* JMeter - https://jmeter.apache.org/download_jmeter.cgi binaries (probably you will face problems using apt-get or other package management systems to download it)

## Installation

The installation process differs depending whether you have both the web server and JMeter in the same computer or not.

### Web and JMeter servers in the same computer (usually a development computer)
* Get the code
    + *cd /var/www* (or any other place, but accessible through a web server, not a public one please, read [security](#security) below)
    + *git clone git://github.com/moodlehq/moodle-performance-comparison.git moodle-performance-comparison*
    + *cd moodle-performance-comparison*
* Configure the tool
    + *cp webserver_config.properties.dist webserver_config.properties*
    + Edit webserver_config.properties with your own values
    + *cp jmeter_config.properties.dist jmeter_config.properties*
    + Edit jmeter_config.properties with your own values

### Web server and JMeter running from a different server
* Get the code in the web server
    + *cd /var/www* (or any other place, but accessible through a web server, not a public one please, read [security](#Security) below)
    + *git clone git://github.com/moodlehq/moodle-performance-comparison.git moodle-performance-comparison*
    + *cd moodle-performance-comparison*
* Get the code in the JMeter server
    + *cd /wherever/you/want*
    + *git clone git://github.com/moodlehq/moodle-performance-comparison.git moodle-performance-comparison*
    + *cd moodle-performance-comparison*
* Configure the tool in the web server
    + *cp webserver_config.properties.dist webserver_config.properties*
    + Edit webserver_config.properties with your own values
* Configure the tool in the JMeter server
    + *cp jmeter_config.properties.dist jmeter_config.properties*
    + Edit jmeter_config.properties with your own values


## Usage

The simplest is to just execute *compare.sh*, but it will only work in development computers where jmeter is installed in the web server and when you are testing differences between different branches. For other cases the process also differs depending whether you have both web server and JMeter in the same computer or not. Here there is another alternative, you can load your sql dump instead of having a clean brand new site with a fixed dataset, so you can run the generated test plan using real site generated data.

The groupname and description arguments of test_runner.sh are useful to identify the run when comparing results, you can use it to set the branch name, the settings you used or whatever will help you identify which run is it.

Note that you can run the tests as many times as you want, you just need to run after_run_setup.sh and restart_services.sh before running test_runner.sh every time to clean up the site.

It is recommendable that you run all the scripts using the same user (there is no need to use a root user at all) you can use different users to run them (there are no restrictions about it) but be sure that the permissions are correct, it seems to be one of the more common issues when running this tool.

### Web and JMeter servers in the same computer, to find performance differences between different branches (usually a development computer)
* Run compare.sh, the browser will be automatically opened after both runs are finished
    + ./compare.sh
* In case the browser doesn't open properly the comparison page, browse to
    + http://localhost/moodle-performance-comparison/index.php (change to your URL according to your configuration)

### Web and JMeter servers in the same computer, to find performance differences changing site settings / cache stores
* Generate the data and run the tests
    + cd /path/to/moodle-performance-comparison
    + *./before_run_setup.sh {XS|S|M|L|XL|XXL}*
    + Change site settings if necessary according to what you are comparing
    + *./restart_services.sh*
    + *./test_runner.sh* {groupname} {descriptioname}
    + *./after_run_setup.sh*
    + Change site settings if necessary according to what you are comparing
    + *./restart_services.sh*
    + *./test_runner.sh* {groupname} {descriptioname}
* Check the results
    + http://localhost/moodle-performance-comparison/index.php (change to your URL according to your configuration)

### Web server and JMeter running from a different server
* Generate the data and the test plan (web server)
    + *cd /path/to/moodle-performance-comparison*
    + *./before_run_setup.sh {XS|S|M|L|XL|XXL}*
    + Change site settings if necessary according to what you are comparing
    + *./restart_services.sh*
* Get the test plan files (jmeter server)
    + *cd /path/to/moodle-performance-comparison*
    + *curl -O http://webserver/moodle/site/path/testusers.csv -O http://webserver/moodle/site/path/testplan.jmx*
* Get the $beforebranch moodle version data (jmeter server)
    + *cd /path/to/moodle-performance-comparison*
    + *curl -O http://webserver/moodle/site/path/site_data.properties*
* Run the before test (jmeter server)
    + *cd /path/to/moodle-performance-comparison*
    + *./test_runner.sh {groupname} {descriptioname} testplan.jmx testusers.csv site_data.properties*
* Restore the base state to run the after branch (web server)
    + *cd /path/to/moodle-performance-comparison*
    + *./after_run_setup.sh*
    + Change site settings if necessary according to what you are comparing
    + *./restart_services.sh*
* Get the $afterbranch moodle version data (jmeter server)
    + *cd /path/to/moodle-performance-comparison*
    + *curl -O http://webserver/moodle/site/path/site_data.properties*
* Run the after test (jmeter server)
    + *cd /path/to/moodle-performance-comparison*
    + *./test_runner.sh {groupname} {descriptioname} testplan.jmx testusers.csv site_data.properties*
* Check the results (web server)
    + http://localhost/moodle-performance-comparison/index.php (change to your URL according to your configuration)

### Using your own sql dump (Moodle 2.5 onwards)
The installation and configuration is the same, it also depends on if you use the same computer for both web server and JMeter or not, but the usage changes when you want to use your own sql dump, it is not that easy to automate, as you need to specify which course do you want to use as target course and you can not use before_run_setup.sh to generate the test plan and test_files.properties.

* *cd /webserver/path/to/moodle-performance-comparison*
* Restore your dataroot to $dataroot
* Restore your database to $dbname in $dbhost
* Get the moodle code
* Upgrade the site to $beforebranch
    + *cd moodle/*
    + *git checkout $beforebranch*
    + *php admin/cli/upgrade.php --allow-unstable --non-interactive*
* Generate the test plan updating users passwords. You need to provide the shortname of the course that will be tested
    + *php admin/tool/generator/cli/maketestplan.php --size="THESIZEYOUWANT" --shortname="TARGETCOURSESHORTNAME" --bypasscheck --updateuserspassword*
* Generate the site_data.properties file, with the current moodle version data, in the root directory of moodle-performance-comparison
    + *cd ..*
    + *./create_site_data_file.sh*
* Download the test plan and the test users. The URLs are provided by maketestsite.php in the previous step, before the performance info output begins.
    + *cd moodle/*
    + *curl -o testplan.jmx http://webserver/url/provided/by/maketestsite.php/in/the/previous/step/testplan_NNNNNNNNNNNN_NNNN.jmx*
    + *curl -o testusers.csv http://webserver/url/provided/by/maketestsite.php/in/the/previous/step/users_NNNNNNNNNNNN_NNNN.jmx*
* Backup dataroot and database (pg_dump or mysqldump), this backup will contain the updated passwords
* Create moodle-performance-comparison/test_files.properties with the backups you just generated and the test plan data
    + *cd ../*
    + Create a new /path/to/moodle-performance-comparison/test_files.properties file with the following content:

>    testplanfile="/absolute/path/to/testplan.jmx"
>
>    datarootbackup="/absolute/path/to/the/dataroot/backup/directory"
>
>    testusersfile="/absolute/path/to/testusers.csv"
>
>    databasebackup="/absolute/path/to/the/database/backup.sql"

* cd */path/to/moodle-performance-comparison* and continue the normal process from restart_services.sh -> test_runner.sh -> after_run_setup.sh -> restart_services.sh -> test_runner.sh

### Using your own sql dump (before Moodle 2.5)
Moodle 2.5 introduces the site and the test plan generators, so you can not use them if you are comparing previous branches. But you can:
* Use the template included in Moodle 2.5 codebase and fill the placeholders with one of your site courses info and the test plan users, loops and ramp up period
    + The test plan template is located in *admin/tool/generator/testplan.template.jmx*
* Fill a testusers.php with the target course data
    + You will need to check that the test data has enough users according to the data you provided in the test plan
* Generate the site_data.properties file, with the current moodle version data, in the root directory of moodle-performance-comparison
    + *cd ..*
    + *./create_site_data_file.sh*
* Follow [Using your own sql dump (Moodle 2.5 onwards)](#using-your-own-sql-dump-moodle-25-onwards) instructions 


## Advanced usage
* You can overwrite the values provided by the test plan using test_runner.sh options:
    + -u=[users_number]
    + -l=[loops_number]
    + -r=[rampup_period]
    + -t=[throughput]


## Security

This tool in only intended to be used in development/testing environments inside the local network, it would be insecure to expose the project root in a public accessible web server, the same only exposing moodle/ directory:

* Database connection data and other sensitive data is stored in properties files (you can change permissions)
* It uses default sugar passwords (you can change the defaults in webserver_config.properties)
* Stores test users credentials in Moodle's wwwroot (you can change permissions)
* In general all files permissions are non secure at all (you can change permissions)
* Other things I probably forgot, to resume, don't do it unless you are sure what you are doing


## Troubleshooting
* You can find an extensive troubleshooting guide [here](https://github.com/moodlehq/moodle-performance-comparison/blob/master/TROUBLESHOOTING.md)
* You might be interested in raising the PHP memory_limit to 512MB (apache) or something like that to 'M' or bigger when comparing results.
* You can find JMeter logs in logs/
* You can find runs outputs in runs_outputs/ the results in runs_samples/ and the php arrays generated from them in runs/
* The generated .jtl files can be big. Don't hesitate to get rid of them if you don't need them for extra analytic purposes.
* Same with $backupsdir/ contents, if you run before_run_setup.sh many time you will have a looot of hd space wasted.
* If files with _java_pid[\d]+.hprof_ format are generated in your project root means that jmeter is running out of resource. http://wiki.apache.org/jmeter/JMeterFAQ#JMeter_keeps_getting_.22Out_of_Memory.22_errors.__What_can_I_do.3F for more info.
