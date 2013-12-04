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

# Exit on errors.
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
load_properties "defaults.properties"
load_properties "webserver_config.properties"

# Checks the $cmds.
check_cmds

# Get generated test plan values.
load_properties "test_files.properties"

# Move to the moodle directory.
cd moodle

# Remove current dataroot and restore the provided one (Better using chown...).
if [ ! -d "$dataroot" ] || [ -z "$dataroot" ]; then
    echo "Error: Armageddon prevented just 2 lines of code above a rm -rf. Please, assign a value to \$dataroot var in webserver_config.properties"
    exit 1
fi
rm $dataroot -rf
cp -r $datarootbackup $dataroot
cpexitcode=$?
if [ "$cpexitcode" -ne "0" ]; then
    echo "Error: code $cpexitcode. $datarootbackup can not be copied to $dataroot"
    exit $cpexitcode
fi

chmod -R 777 $dataroot

# Drop and restore the database.
if [ "$dbtype" == "pgsql" ]; then
    echo "#######################################################################"
    echo "Restoring database and dataroot to Moodle ($basecommit)"
    export PGPASSWORD=${dbpass}

    # Checking that the table exists.
    databaseexists="$( ${pgsqlcmd} -h "$dbhost" -U "$dbuser" -l | grep "$dbname" | wc -l )"
    if [ "$databaseexists" != "0" ]; then
        ${pgsqlcmd} -h "$dbhost" -U "$dbuser" -d template1 -c "DROP DATABASE $dbname" --quiet
    fi
    ${pgsqlcmd} -h "$dbhost" -U "$dbuser" -d template1 -c "CREATE DATABASE $dbname WITH OWNER $dbuser ENCODING 'UTF8'" --quiet
    ${pgsqlcmd} --quiet -h "$dbhost" -U "$dbuser" $dbname < $databasebackup > /dev/null

elif [ "$dbtype" == "mysqli" ]; then
    echo "#######################################################################"
    echo "Restoring database and dataroot to Moodle ($basecommit)"

    # Checking that the table exists.
    databaseexists="$( ${mysqlcmd} --host=${dbhost} --user=${dbuser} --password=${dbpass} -e "SHOW DATABASES LIKE '$dbname'" )"
    if [ ! -z "$databaseexists" ];then
        ${mysqlcmd} --host=${dbhost} --user=${dbuser} --password=${dbpass} -e "DROP DATABASE $dbname" --silent
    fi
    ${mysqlcmd} --host=${dbhost} --user=${dbuser} --password=${dbpass} -e "CREATE DATABASE $dbname DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_unicode_ci" --silent
    ${mysqlcmd} --silent --host=${dbhost} --user=${dbuser} --password=${dbpass} $dbname < $databasebackup > /dev/null
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
echo "Upgrading Moodle ($basecommit) to $afterbranch"
checkout_branch $afterbranchrepository 'after' $afterbranch
${phpcmd} admin/cli/upgrade.php --non-interactive --allow-unstable > /dev/null
upgradeexitcode=$?
if [ "$upgradeexitcode" -ne "0" ]; then
    echo "Error: Moodle can not be upgraded to $afterbranch"
    exit $upgradeexitcode
fi

# Stores the site data in an jmeter-accessible file.
save_moodle_site_data

# Returning to the root.
cd ..

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
