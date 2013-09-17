#!/bin/bash

##############################################
# Sets up a moodle site with courses and users and generates a JMeter test plan.
#
# Auto-backup feature only available for postgres and mysql, only available as interactive
# script when running other drivers.
#
# Usage: cd /path/to/moodle-performance-comparison && ./before_run_setup.sh {size}
#
# Arguments:
# * $1 => Size, one of the following options: XS, S, M, L, XL, XXL. More than 'M' is not recommended for development computers.
#
##############################################

#set -e
debug=" > /dev/null 2>&1"

# Dependencies.
. ./lib/lib.sh

# Hardcoded values.
sitefullname="Full site name"
siteshortname="Short site name"
siteadminusername="admin"
siteadminpassword="admin"
currentwd=`pwd`
filenameusers="$currentwd/moodle/testusers.csv"
filenametestplan="$currentwd/moodle/testplan.jmx"
permissions=777

# The target course according to the selected size.
declare -A sizecourse
sizecourse['XS']='testcourse_3'
sizecourse['S']='testcourse_12'
sizecourse['M']='testcourse_73'
sizecourse['L']='testcourse_277'
sizecourse['XL']='testcourse_1065'
sizecourse['XXL']='testcourse_4177'

# We need the size.
if [ -z "$1" ]; then
    output="Usage: `basename $0` {size}

Sets up a moodle site with courses and users and generates a JMeter test plan.

Arguments:
* $1 => Size, one of the following options: XS, S, M, L, XL, XXL. More than 'M' is not recommended in development computers.
"
    echo "$output"
    exit 1
fi

# Get user info.
load_properties "webserver_config.properties"

# Creating & cleaning dirroot & dataroot (keeping .git)
if [ ! -e "$dataroot" ]; then
    mkdir -m $permissions $dataroot
fi
rm $dataroot/* -rf
if [ ! -e "moodle" ]; then
    mkdir -m $permissions "moodle"
fi

# Cleaning previous test plan files.
if [ -e "$filenameusers" ]; then
    rm "$filenameusers"
fi
if [ -e "$filenametestplan" ]; then
    rm "$filenametestplan"
fi


# Creating new database and delete it if it already exists.
if [ "$dbtype" == "pgsql" ]; then
    psql -h "$dbhost" -U "$dbuser" -c "DROP DATABASE $dbname"
    psql -h "$dbhost" -U "$dbuser" -c "CREATE DATABASE $dbname WITH OWNER $dbuser ENCODING 'UTF8'"
elif [ "$dbtype" == "mysqli" ]; then
    mysql -h "$dbhost" -u "$dbuser" -p -e "DROP DATABASE $dbname"
    mysql -h "$dbhost" -u "$dbuser" -p -e "CREATE DATABASE $dbname DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_unicode_ci"
else
    confirmoutput="Only postgres and mysql support: You need to manually create your database. 
Press [q] to stop the script or, if you have already done it, any other key to continue.
"
    echo "$confirmoutput"
    read confirmation
    if [ "$confirmation" == "q" ]; then
        exit 1
    fi
fi

# Move to moodle dirroot and begin setting up everything.
cd moodle

checkout_branch $baserepository 'base' $basecommit

# Copy config.php template and set user properties.
replacements="%%dbtype%%#$dbtype
%%dbhost%%#$dbhost
%%dbname%%#$dbname
%%dbuser%%#$dbuser
%%dbpass%%#$dbpass
%%dbprefix%%#$dbprefix
%%wwwroot%%#$wwwroot
%%dataroot%%#$dataroot
%%toolgeneratorpassword%%#$toolgeneratorpassword"

configfilecontents="$( cat ../config.php.template )"
for i in ${replacements}; do
    configfilecontents=$( echo "${configfilecontents}" | sed "s#${i}#g" )
done
# Overwrites the previous config.php file.
echo "${configfilecontents}" > config.php
permissionsexitcode=$?
if [ "$permissionsexitcode" -ne "0" ]; then
    echo "Error: Moodle's config.php can not be written, check $currentwd/moodle directory (and $currentwd/moodle/config.php if it exists) permissions."
    exit $permissionsexitcode
fi
chmod $permissions config.php

# Install the site with user specified params.
php admin/cli/install_database.php --agree-license --fullname="$sitefullname" --shortname="$siteshortname" --adminuser="$siteadminusername" --adminpass="$siteadminpassword" $debug
installexitcode=$?
if [ "$installexitcode" -ne "0" ]; then
    echo "Error: Moodle can not be installed"
    exit $installexitcode
fi

# Generate courses.
php admin/tool/generator/cli/maketestsite.php --size=$1 --fixeddataset --bypasscheck $debug
testsiteexitcode=$?
if [ "$testsiteexitcode" -ne "0" ]; then
    echo "Error: The test site can not be generated"
    exit $testsiteexitcode
fi

# We capture the output to get the files we will need.
testplancommand='php admin/tool/generator/cli/maketestplan.php --size='$1' --shortname='${sizecourse[$1]}' --bypasscheck'$debug
testplanfiles="$(${testplancommand})"
# We only get the first two items as there is more performance info.
if [[ "$testplanfiles" == *"testplan"* ]]; then
    if [ -z "$debug" ]; then
        wgetoutput=""
    else
        wgetoutput=" -o /dev/null"
    fi
    wget $testplanfiles $wgetoutput
else
    echo "Error: There was a problem generating the test plan."
    exit 1
fi

# Yeah baby, we are bad ass; if we run it every time there will always be just one file changed.
# We need hardcoded filenames to access them easily from other scripts or CI servers jobs.
mv testplan* $filenametestplan
mv users* $filenameusers

# Backups
if [ ! -e "$backupsdir" ]; then
    mkdir -m $permissions $backupsdir
fi
datesufix=`date '+%Y%m%d%H%M'`
filenamedataroot="$backupsdir/dataroot_backup_$datesufix"
filenamedatabase="$backupsdir/database_backup_$datesufix.sql"

# Dataroot backup
cp -r $dataroot $filenamedataroot

# Database backup.
if [ "$dbtype" == "pgsql" ]; then
    pg_dump -h "$dbhost" -U "$dbuser" $dbname > $filenamedatabase
elif [ "$dbtype" == "mysqli" ]; then
    mysqldump -h "$dbhost" -u "$dbuser" -p $dbname > $filenamedatabase
else
    echo "Only postgres and mysql backup/restore support, you will have to backup it manually."
    $filenamedatabase='NOT AVAILABLE'
fi

# Info about what have we done, stored inside moodle's dirroot to be visible.
# Overwrites the old file if it exists.
generatedfiles="testplanfile=$filenametestplan
testusersfile=$filenameusers
datarootbackup=$filenamedataroot
databasebackup=$filenamedatabase"
echo "$generatedfiles" > "$currentwd/test_files.properties"

# Upgrading moodle, although we are not sure that base and before branch are different.
checkout_branch $beforerepository 'before' $beforebranch
php admin/cli/upgrade.php --non-interactive --allow-unstable
upgradeexitcode=$?
if [ "$upgradeexitcode" -ne "0" ]; then
    echo "Error: Moodle can not be upgraded to $beforebranch"
    exit $upgradeexitcode
fi

# Also output the info.
outputinfo="
#######################################################################
'Before' run setup finished successfully.

Note the following files were generated, you will need this info when running testrunner.sh in a different server, they are also saved in test_files.properties.
- Test plan: $filenametestplan
- Test users: $filenameusers
- Dataroot backup: $filenamedataroot
- Database backup: $filenamedatabase

Now you can:
- Change the site configuration
- Change the cache stores
And to continue with the test you should:
- Run restart_services.sh (or manually restart web and database servers if this script doesn\'t suit your system)
- Run test_runner.sh
"
echo "$outputinfo"
exit 0
