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

# Exit on errors.
#set -e

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

# Validate the passed size ($1)
case "$1" in
    'XS')
        targetcourse='testcourse_3'
        ;;
     'S')
        targetcourse='testcourse_12'
        ;;
     'M')
        targetcourse='testcourse_73'
        ;;
     'L')
        targetcourse='testcourse_277'
        ;;
    'XL')
        targetcourse='testcourse_1065'
        ;;
   'XXL')
        targetcourse='testcourse_4177'
        ;;
       *)
        echo "Usage: `basename $0` {size}

Sets up a moodle site with courses and users and generates a JMeter test plan.

Arguments:
* $1 => Size, one of the following options: XS, S, M, L, XL, XXL. More than 'M' is not recommended in development computers.
"
        exit 1
esac

# Get user info.
load_properties "defaults.properties"
load_properties "webserver_config.properties"

# Checks the $cmds.
check_cmds

# Creating & cleaning dirroot & dataroot (keeping .git)
if [ ! -e "$dataroot" ]; then
    mkdir -m $permissions $dataroot
    mkdirexitcode=$?
    if [ "$mkdirexitcode" -ne "0" ]; then
        echo "Error: There was a problem creating $dataroot directory"
        exit $mkdirexitcode
    fi
fi
rm $dataroot/* -rf
if [ ! -e "moodle" ]; then
    mkdir -m $permissions "moodle"
    mkdirexitcode=$?
    if [ "$mkdirexitcode" -ne "0" ]; then
        echo "Error: There was a problem creating moodle/ directory"
        exit $mkdirexitcode
    fi
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
    export PGPASSWORD=${dbpass}

    # Checking that the table exists.
    databaseexists="$( ${pgsqlcmd} -h "$dbhost" -U "$dbuser" -l | grep "$dbname" | wc -l )"
    if [ "$databaseexists" != "0" ]; then
        ${pgsqlcmd} -h "$dbhost" -U "$dbuser" -d template1 -c "DROP DATABASE $dbname" --quiet
    fi

    ${pgsqlcmd} -h "$dbhost" -U "$dbuser" -d template1 -c "CREATE DATABASE $dbname WITH OWNER $dbuser ENCODING 'UTF8'" --quiet

elif [ "$dbtype" == "mysqli" ]; then

    # Checking that the table exists.
    databaseexists="$( ${mysqlcmd} --host=${dbhost} --user=${dbuser} --password=${dbpass} -e "SHOW DATABASES LIKE '$dbname'" )"
    if [ ! -z "$databaseexists" ];then
        ${mysqlcmd} --host=${dbhost} --user=${dbuser} --password=${dbpass} -e "DROP DATABASE $dbname" --silent
    fi

    ${mysqlcmd} --host=${dbhost} --user=${dbuser} --password=${dbpass} -e "CREATE DATABASE $dbname DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_unicode_ci" --silent

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

checkout_branch $repository 'origin' $basecommit

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
echo "#######################################################################"
echo "Installing Moodle ($basecommit)"
${phpcmd} admin/cli/install_database.php --agree-license --fullname="$sitefullname" --shortname="$siteshortname" --adminuser="$siteadminusername" --adminpass="$siteadminpassword" > /dev/null
installexitcode=$?
if [ "$installexitcode" -ne "0" ]; then
    echo "Error: Moodle can not be installed, check that the database data is correctly set"
    exit $installexitcode
fi

# Generate courses.
${phpcmd} admin/tool/generator/cli/maketestsite.php --size=$1 --fixeddataset --bypasscheck --filesizelimit="1000" --quiet > /dev/null
testsiteexitcode=$?
if [ "$testsiteexitcode" -ne "0" ]; then
    echo "Error: The test site can not be generated, check that the site is correctly installed"
    exit $testsiteexitcode
fi

# Enable advanced settings and list courses in the frontpage.
${phpcmd} ../set_moodle_site.php
setsiteexitcode=$?
if [ "$setsiteexitcode" -ne "0" ]; then
    echo "Error: The test site can not be configured, check that the site is correctly installed"
    exit $setsiteexitcode
fi

# We capture the output to get the files we will need.
testplancommand=${phpcmd}' admin/tool/generator/cli/maketestplan.php --size='$1' --shortname='${targetcourse}' --bypasscheck' > /dev/null
testplanfiles="$(${testplancommand})"
testplanfileexitcode=$?
if [ "$testplanfileexitcode" -ne "0" ]; then
    echo "Error: code $testplanfileexitcode. Moodle's test plan generator could not finish as expected"
    exit $testplanfileexitcode
fi

# We only get the first two items as there is more performance info.
if [[ "$testplanfiles" == *"testplan"* ]]; then
    # Prepare curl arguments.
    files=( $testplanfiles )
    if [ "${#files[*]}" -ne 2 ]; then
        echo "Error: There was a problem generating the test plan."
        exit 1
    fi
    ${curlcmd} -o $filenametestplan ${files[0]} -o $filenameusers ${files[1]} --silent
else
    echo "Error: There was a problem generating the test plan."
    exit 1
fi

# Backups.
if [ ! -e "$backupsdir" ]; then
    mkdir -m $permissions $backupsdir
    mkdirexitcode=$?
    if [ "$mkdirexitcode" -ne "0" ]; then
        echo "Error: There was a problem creating $backupsdir directory"
        exit $mkdirexitcode
    fi

fi
datesufix=`date '+%Y%m%d%H%M'`
filenamedataroot="$backupsdir/dataroot_backup_$datesufix"
filenamedatabase="$backupsdir/database_backup_$datesufix.sql"

# Dataroot backup.
echo "Creating Moodle ($basecommit) database and dataroot backups"
rm -rf $dataroot/sessions
cp -r $dataroot $filenamedataroot
cpexitcode=$?
if [ "$cpexitcode" -ne "0" ]; then
    echo "Error: code $cpexitcode. $dataroot can not be copied to $filenamedataroot"
    exit $cpexitcode
fi


# Database backup.
if [ "$dbtype" == "pgsql" ]; then
    export PGPASSWORD=${dbpass}
    ${pgsqldumpcmd} -h "$dbhost" -U "$dbuser" $dbname > $filenamedatabase
elif [ "$dbtype" == "mysqli" ]; then
    ${mysqldumpcmd} --host=${dbhost} --user=${dbuser} --password=${dbpass} ${dbname} > $filenamedatabase
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
fileexitcode=$?
if [ "$fileexitcode" -ne "0" ]; then
    echo "Error: code $fileexitcode. Moodle can not add the info about the generated files to $currentwd/test_files.properties check the permissions"
    exit $fileexitcode
fi


# Upgrading moodle, although we are not sure that base and before branch are different.
echo "Upgrading Moodle ($basecommit) to $beforebranch"
checkout_branch $beforebranchrepository 'before' $beforebranch
${phpcmd} admin/cli/upgrade.php --non-interactive --allow-unstable > /dev/null
upgradeexitcode=$?
if [ "$upgradeexitcode" -ne "0" ]; then
    echo "Error: Moodle can not be upgraded to $beforebranch"
    exit $upgradeexitcode
fi

# Stores the site data in an jmeter-accessible file.
save_moodle_site_data

# Returning to the root.
cd ..

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
