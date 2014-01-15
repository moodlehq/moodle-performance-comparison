# Troubleshooting guide

This guide purpose, as you can guess, is to help you make this tool run, provinding info about what is happening behind the scripts and how you can backtrace aunexpected error until you find the cause and a solution.

moodle-performance-comparison tool uses both bash scripts and PHP scripts, needs a web server and a database engine, it will probably run in multiple and different infrastructures, OS versions, PHP and bash interpreters, web servers, jmeter versions... So, depending on your configuration and your environment you may find issues when configuring it, downloading it's dependencies or running it that we have not detected yet; the tool provides error messages for most of the common issues you can fall into and we will be adding more of those error messages as long as you let us know the problems you are finding while trying to make it run, so don't hesitate to open an issue or comment about them and other people will not spend the time you did trying to find the solution for a problem.


## Requirements

### Java
  * Java 6 or later is required

#### JMeter dependencies
  * The tool has been tested with JMeter 2.9 and 2.11, but will probably work from JMeter 2.7 onwards
  * You should download the binaries
  * You may have problems with the JMeter dependencies, ensure you downloaded the binaries from *http://jmeter.apache.org/download_jmeter.cgi* rather than using a package management system. You may find errors like the one below, stating that there are undefined classes:

> 2014/01/13 00:54:50 ERROR - jmeter.util.BeanShellInterpreter: Error invoking bsh method: source Sourced file: recorder.bsf : Typed variable declaration : Typed variable declaration : Attempt to resolve method: rightPad() on undefined variable or class name: StringUtils


## Installing the tool

### Source code
  * Not much to say here, we recommend *git clone git://github.com/moodlehq/moodle-performance-comparison.git* otherwise you can just download the ZIP for the branch you want to use
  * The only changes between the project branches are the default hashes proposed in *webserver_config.properties.dist*
  * Remember that this tool is not intended to be used in public servers and there are serious security risks doing it


## Configuring

In general, the best tip is to follow the *webserver_config.properties.dist* and *jmeter_config.properties.dist* provided values, changing them according to your system, probably you will have to change the database connection data, *$wwwroot*, *$dataroot* and *$backupsdir*. Depending on the use of the tool you will need to change *$afterbranch* and _$afterbranchrepository_ or both after and before. Would be better to avoid using values with tricky characters like quotes, double quotes, accents...

### File permissions
  * Ensure the user you are using has write permissions over the parent directories of *$dataroot* and *$backupsdir*
 
### Database connection
  * Only mysql and postgres are fully supported. Read README information about how to use the tool using other DB engines
  * Ensure you can access your database using the credentials you set in *webserver_config.properties*
  * If your database server is in a different server than the web server ensure they can access each other, the CLI commands used to create the databases are psql and mysql, you can also overwrite the path to the commands using *webserver_config.properties*
  * *$dbuser* should have permissions to create a database in *$dbhost*

### JMeter
  * You should provide the path to the directory where you extracted the JMeter files not the one with the jmeter sh script; the one containing bin/, lib/, README, LICENSE...

### Moodle site
  * Ensure your *$wwwroot* value includes *http://*, your locahost/the-host-name/ip and the path to the moodle-performance-comparison project + */moodle* as the moodle site is installed in moodle-performance-comparison/moodle


## Running the tool

Here we will explain what the scripts are doing. Basically this is a resume of what are they doing when running all together (*compare.sh* follows all this process):

1. Checkout a base moodle codebase for both before and after branches
2. Installs a moodle site
3. Generates courses, users, enrolments and activities
4. Generates a JMeter test plan based on the site data
5. Backs up the database and the dataroot
6. Upgrades moodle to *$beforebranch*
7. Runs the tests
8. Restores the database and the dataroot to #5
9. Upgrades moodle to *$afterbranch*
10. Runs the tests
11. Opens a browser window to display the differences between runs

The error messages the tool provides informs you about what went wrong and they are following the STDERR messages provided by the command that failed, so in most of the cases you will know what is going wrong. If you end up with one of those errors and you can not solve it you can always find the error message in the scripts and run manually the command that is failing to see the whole output. 

Following the scripts in the order they should be executed and the points where you can have problems:

*before_run_setup.sh*

1. Creates directories to store moodle's dataroot and dirroot using provided *$dataroot* var. You might have problems if you provided a wrong *$dataroot* value.
2. Cleans previous existing JMeter test plan files
3. A clean database owned by *$dbuser* is created, droping any previous one if it existed. Here you can experience database permissions problems or you can find firewall restrictions to establish a connection between the web server and the database server
4. Checks out the base commit. Probably you have not changed that value, so it should be ok
5. Creates a moodle config file in *moodle/config.php* based on the template contents
6. Installs moodle using the default site and admin data
7. Checks that what you set as *$wwwroot* is the test site that has just been installed. Here it can fail if you provided a wrong *$wwwroot* value or the site can not be accessed by curl. You can also check that you can access *$wwwroot* manually using a browser, and logging in with admin/admin
8. Generates data to populate the site with users, courses, enrolments... All of moodle's php scripts returns != 0 exit codes so you will see an error message if something goes wrong. Same as before, you can log in to see if the courses, users, enrolments and activities are generated according to the test size you specified.
9. Generates the JMeter test plan; it creates two files the .jmx test plan file and the users file with the login details. They are stored in *moodle-performance-comparison/moodle* under the names *testplan.jmx* and *testusers.csv*, you can open them and see if you find any issue with their contents.
10. Database and dataroot are backed up. You can check *$backups* dir contents and confirm they are there, otherwise the base populated site would not be restored
11. Creates a file containing the all generated files. You can open *moodle-performance-comparison/test_files.properties* and ensure the mentioned files exists
12. Checks out *$beforebranch* and upgrades moodle to it.
13. Stores information about *$beforebranch*; the moodle version and the git commit info. You can open *moodle-performance-comparison/moodle/site_data.properties* and check if it's contents makes sense

*test_runner.sh*

1. Runs JMeter using the info contained in the test plan files and the site data info (*testplan.jmx*, *testusers.csv* and *site_data.properties*) It takes a while depending on the size you specified, so if it finishes too fast, you can suspect that something went wrong. It generates a few files that you can open and check: *logs/jmeter.DATE.log* (the JMeter logs), *runs_outputs/DATE.output* (list of threads that JMeter run and it's HTTP status code), *runs_samples/data.DATE.jtl* (HTTP samples XML) and runs/DATE.php (PHP file with all the run data and results)
2. Checks *logs/jmeter.DATE.log* looking for warnings or errors to let the user know about unexpected errors.

*after_run_setup.sh*

1. Restores database and dataroot removing the previous ones. Here you can face permissions problems.
2. Upgrades moodle to $afterbranch like *before_run_setup.sh* does in #12
3. Saves the site data like *before_run_setup.sh* does in #13

*test_runner.sh*

1. Same as before
2. Same as before


## Viewing the results
  * Not much to comment about here, you can find issues when using the detailed view as it was inherited from a previous tool, but it works quite well.
