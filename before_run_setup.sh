#!/bin/bash
#
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
set -e

# Dependencies.
. ./lib/lib.sh

# Hardcoded values.
readonly SITE_FULL_NAME="Full site name"
readonly SITE_SHORT_NAME="Short site name"
readonly SITE_ADMIN_USERNAME="admin"
readonly SITE_ADMIN_PASSWORD="admin"
readonly CURRENT_WORKING_DIRECTORY=`pwd`
readonly FILE_NAME_USERS="$CURRENT_WORKING_DIRECTORY/moodle/testusers.csv"
readonly FILE_NAME_TEST_PLAN="$CURRENT_WORKING_DIRECTORY/moodle/testplan.jmx"
readonly PERMISSIONS=775

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
* $1 => Size, one of the following options: XS, S, M, L, XL, XXL. More than
'M' is not recommended in development computers.
" >&2
        exit 1
esac

# Get user info.
load_properties "defaults.properties"
load_properties "webserver_config.properties"

# Checks the $cmds.
check_cmds

# Creating & cleaning dirroot & dataroot (keeping .git)
if [ ! -e "$dataroot" ]; then
    mkdir -m $PERMISSIONS $dataroot || \
        throw_error "There was a problem creating $dataroot directory"
else
    # If it already existed we clean it
    delete_files "$dataroot/*"
fi

if [ ! -e "moodle" ]; then
    mkdir -m $PERMISSIONS "moodle" || \
        throw_error "There was a problem creating moodle/ directory"
fi

# Cleaning previous test plan files.
if [ -e "$FILE_NAME_USERS" ]; then
    delete_files "$FILE_NAME_USERS" 1
fi
if [ -e "$FILE_NAME_TEST_PLAN" ]; then
    delete_files "$FILE_NAME_TEST_PLAN" 1
fi


# Creating new database and delete it if it already exists.
if [ "$dbtype" == "pgsql" ]; then
    export PGPASSWORD=${dbpass}

    # Checking that the table exists.
    databaseexists="$( ${pgsqlcmd} -h "$dbhost" -U "$dbuser" -l | \
        grep "$dbname" | \
        wc -l )"
    if [ "$databaseexists" != "0" ]; then
        ${pgsqlcmd} \
            -h "$dbhost" \
            -U "$dbuser" \
            -d template1 \
            -c "DROP DATABASE $dbname" \
            --quiet
    fi

    ${pgsqlcmd} \
        -h "$dbhost" \
        -U "$dbuser" \
        -d template1 \
        -c "CREATE DATABASE $dbname WITH OWNER $dbuser ENCODING 'UTF8'" \
        --quiet

elif [ "$dbtype" == "mysqli" ]; then

    # Checking that the table exists.
    databaseexists="$( ${mysqlcmd} \
        --host=${dbhost} \
        --user=${dbuser} \
        --password=${dbpass} \
        -e "SHOW DATABASES LIKE '$dbname'" )"
    if [ ! -z "$databaseexists" ];then
        ${mysqlcmd} \
            --host=${dbhost} \
            --user=${dbuser} \
            --password=${dbpass} \
            -e "DROP DATABASE $dbname" \
            --silent
    fi

    ${mysqlcmd} \
        --host=${dbhost} \
        --user=${dbuser} \
        --password=${dbpass} \
        -e "CREATE DATABASE $dbname DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_unicode_ci" \
        --silent

else
    confirmoutput="Only postgres (pgsql) and mysql (mysqli) support: You \
need to manually create your database.
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
errorstr="Moodle's config.php can not be written, \
check $CURRENT_WORKING_DIRECTORY/moodle directory \
(and $CURRENT_WORKING_DIRECTORY/moodle/config.php if it exists) permissions."

echo "${configfilecontents}" > config.php || \
    throw_error "$errorstr"
chmod $PERMISSIONS config.php

# Install the site with user specified params.
echo "#######################################################################"
echo "Installing Moodle ($basecommit)"
${phpcmd} admin/cli/install_database.php \
    --agree-license \
    --fullname="$SITE_FULL_NAME" \
    --shortname="$SITE_SHORT_NAME" \
    --adminuser="$SITE_ADMIN_USERNAME" \
    --adminpass="$SITE_ADMIN_PASSWORD" \
    > /dev/null || \
    throw_error "Moodle can not be installed, check that the database data is correctly set"

# Check that the installed site is properly installed and can be accessed
# using the provided wwwroot.
siteindex="${wwwroot%/}/index.php"
${curlcmd} --silent "$siteindex" | \
    grep --quiet "$SITE_FULL_NAME" || \
    throw_error "There is a problem with your wwwroot config var or with \
the test site. Browse to $wwwroot and ensure you see a moodle site."


# Generate courses.
${phpcmd} admin/tool/generator/cli/maketestsite.php \
    --size=$1 \
    --fixeddataset \
    --bypasscheck \
    --filesizelimit="1000" \
    --quiet \
    > /dev/null || \
    throw_error "The test site can not be generated, check that the site is correctly installed"

# Enable advanced settings and list courses in the frontpage.
${phpcmd} ../set_moodle_site.php || \
    throw_error "The test site can not be configured, check that the site is correctly installed"

# We capture the output to get the files we will need.
testplancommand=${phpcmd}' admin/tool/generator/cli/maketestplan.php \
    --size='$1' \
    --shortname='${targetcourse}' \
    --bypasscheck' \
    > /dev/null || \
    throw_error "Moodle's test plan generator could not finish as expected"
testplanfiles="$(${testplancommand})"

# We only get the first two items as there is more performance info.
if [[ "$testplanfiles" == *"testplan"* ]]; then
    # Prepare curl arguments.
    files=( $testplanfiles )
    if [ "${#files[*]}" -ne 2 ]; then
        echo "Error: There was a problem generating the test plan." >&2
        exit 1
    fi
    ${curlcmd} \
        -o $FILE_NAME_TEST_PLAN ${files[0]} \
        -o $FILE_NAME_USERS ${files[1]} \
        --silent || \
        throw_error "There was a problem getting the test plan files. Check your wwwroot setting."
else
    echo "Error: There was a problem generating the test plan." >&2
    exit 1
fi

# Backups.
if [ ! -e "$backupsdir" ]; then
    mkdir -m $PERMISSIONS $backupsdir || \
        throw_error "There was a problem creating $backupsdir directory"

fi
datesufix=`date '+%Y%m%d%H%M'`
filenamedataroot="$backupsdir/dataroot_backup_$datesufix"
filenamedatabase="$backupsdir/database_backup_$datesufix.sql"

# Dataroot backup.
echo "Creating Moodle ($basecommit) database and dataroot backups"
delete_files "$dataroot/sessions"
cp -r $dataroot $filenamedataroot || \
    throw_error "$dataroot can not be copied to $filenamedataroot"

# Database backup.
if [ "$dbtype" == "pgsql" ]; then
    export PGPASSWORD=${dbpass}
    ${pgsqldumpcmd} \
        -h "$dbhost" \
        -U "$dbuser" \
        $dbname > $filenamedatabase
elif [ "$dbtype" == "mysqli" ]; then
    ${mysqldumpcmd} \
        --host=${dbhost} \
        --user=${dbuser} \
        --password=${dbpass} \
        ${dbname} > $filenamedatabase
else
    echo "Only postgres and mysql backup/restore support, you will have to backup it manually."
    $filenamedatabase='NOT AVAILABLE'
fi

# Info about what have we done, stored inside moodle's dirroot to be visible.
# Overwrites the old file if it exists.
errorstr="Moodle can not add the info about the generated files to \
$CURRENT_WORKING_DIRECTORY/test_files.properties, check the permissions"

generatedfiles="testplanfile=$FILE_NAME_TEST_PLAN
testusersfile=$FILE_NAME_USERS
datarootbackup=$filenamedataroot
databasebackup=$filenamedatabase"
echo "$generatedfiles" > "$CURRENT_WORKING_DIRECTORY/test_files.properties" || \
    throw_error "$errorstr"

# Upgrading moodle, although we are not sure that base and before branch are different.
echo "Checking out Moodle from repo: $beforebranchrepository, ref: $beforebranch"
checkout_branch $beforebranchrepository 'before' $beforebranch
echo "Upgrading Moodle ($basecommit) to $(git rev-parse before/$beforebranch)"
${phpcmd} admin/cli/upgrade.php \
    --non-interactive \
    --allow-unstable \
    > /dev/null || \
    throw_error "Moodle can not be upgraded to $beforebranch"

# Stores the site data in an jmeter-accessible file.
save_moodle_site_data

# Returning to the root.
cd ..

# Also output the info.
outputinfo="
#######################################################################
'Before' run setup finished successfully.

Note the following files were generated, you will need this info when running
testrunner.sh in a different server, they are also saved in test_files.properties.
- Test plan: $FILE_NAME_TEST_PLAN
- Test users: $FILE_NAME_USERS
- Dataroot backup: $filenamedataroot
- Database backup: $filenamedatabase

Now you can:
- Change the site configuration
- Change the cache stores
And to continue with the test you should:
- Run restart_services.sh (or manually restart web and database servers if
  this script doesn\'t suit your system)
- Run test_runner.sh
"
echo "$outputinfo"
exit 0
