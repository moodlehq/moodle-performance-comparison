#!/bin/bash

##############################################
# Prepares the next test run after finished running the before_run_setup.sh script.
# * Restores database
# * Restores dataroot
# * Upgrades moodle if necessary
#
# Auto-restore feature only available for postgres and mysql.
#
# Usage: cd /path/to/moodle-performance-comparison && ./after_run_setup.sh
#
# No arguments
#
##############################################

#set -e

# Dependencies.
. ./lib/lib.sh

# We need the paths.
if [ ! -z "$1" ]; then
    output="Usage: `basename $0`

Prepares the next test run after finished running the before_run_setup.sh script.
* Restores database
* Restores dataroot
* Upgrades moodle if necessary
"
    echo "$output"
    exit 1
fi

# Checking as much as we can that before_run_setup.sh was already executed and finished successfully.
errormsg="Error: Did you run before_run_test.sh before running this one? "
if [ ! -e "test_files.properties" ]; then
    echo $errormsg
    exit 1
fi
if [ ! -d "moodle" ]; then
    echo $errormsg
    exit 1
fi
if [ ! -d "moodle/.git" ]; then
    echo $errormsg
    exit 1
fi

# Get user info.
load_properties "webserver_config.properties"

# Get generated test plan values.
load_properties "test_files.properties"

# Move to the moodle directory.
cd moodle

# Remove current dataroot and restore the provided one (Better using chown...).
rm $dataroot -rf
cp -r $datarootbackup $dataroot
chmod 777 $dataroot -R

# Drop and restore the database.
if [ "$dbtype" == "pgsql" ]; then
    echo "Creating $dbname database"
    psql -h "$dbhost" -U "$dbuser" -c "DROP DATABASE $dbname"
    psql -h "$dbhost" -U "$dbuser" -c "CREATE DATABASE $dbname WITH OWNER $dbuser ENCODING 'UTF8'"
    psql -h "$dbhost" -U "$dbuser" $dbname < $databasebackup
elif [ "$dbtype" == "mysqli" ]; then
    echo "Creating $dbname database"
    mysql -h "$dbhost" -u "$dbuser" -e "DROP DATABASE $dbname"
    mysql -h "$dbhost" -u "$dbuser" -p -e "CREATE DATABASE $dbname DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_unicode_ci"
    mysql -h "$dbhost" -u "$dbuser" $dbname < $databasebackup
else
    confirmoutput="Only postgres and mysql support: You need to manually restore your database. 
Press [q] to stop the script or, if you have already done it, any other key to continue.
"
    echo "$confirmoutput"
    read confirmation
    if [ "$confirmation" == "q" ]; then
        exit 1
    fi
fi

# Upgrading moodle, although we are not sure that before and after branches are different.
checkout_branch $afterrepository 'after' $afterbranch
php admin/cli/upgrade.php --non-interactive --allow-unstable

# Info, all went as expected and we are all happy.
outputinfo="
#######################################################################
'After' run setup finished successfully.

Now you can:
- Change the site configuration
- Change the cache stores
And to continue with the test you should:
- Run restart_services.sh (or manually restart web and database servers if this script doesn\'t suit your system)
- Run test_runner.sh
"

echo "$outputinfo"
exit 0
