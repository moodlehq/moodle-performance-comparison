#!/bin/bash

##############################################
# Script to run the test plan using jmeter
#
# Runs will be grouped according to $1 so they
# can be compared easily. The run description
# will be useful to identify them.
#
# Usage: cd /path/to/moodle-performance-comparison && ./test_runner.sh {run_group_name} {run_description} {test_plan_file_path} {users_file_path}
#
# Arguments:
# * $1 => The run group name, there will be comparision graphs by this group name
# * $2 => The run description, useful to identify the changes between runs.
# * $3 => The test plan file path
# * $4 => The path to the file with user's login data
#
##############################################

set -e

# Dependencies.
. ./lib/lib.sh

# Load the generated files locations (when jmeter is running in the same server than the web server).
if [ -e "test_files.properties" ]; then
    load_properties "test_files.properties"
fi

# We give priority to the ones that comes as arguments.
if [ ! -z "$3" ]; then
    $testplanfile = $3
fi
if [ ! -z "$4" ]; then
    $testusersfile = $4
fi

# If there is no test_files.properties and no files were provided we throw an error.
if [ -z "$testplanfile" ] || [ -z "$testusersfile" ]; then
    echo "Usage: `basename $0` {run_group} {run_description} {test_plan_file_path} {users_file_path}"
    exit 1
fi

# Load properties.
load_properties "jmeter_config.properties"

# Uses the test plan specified in the CLI call.
logfile=logs/jmeter.`date '+%Y%m%d%H%M'`.log

# Run it baby! (without GUI).
jmeterbin=$jmeter_path/bin/jmeter
$jmeterbin -n -j "$logfile" -t "$testplanfile" -Jusersfile="$testusersfile" -Jgroup="$1" -Jdesc="$2"
jmeterexitcode=$?
if [ "$jmeterexitcode" -ne "0" ] ; then
    echo "Error: Jmeter can not run, ensure that:"
    echo "* The test plan and the users files are ok"
    echo "* You provide correct arguments to the script"
    exit $jmeterexitcode
fi

outputinfo="
#######################################################################
Test plan completed successfully.

To compare this run with others remember to execute after_run_setup.sh before it to clean the site restoring the database and the dataroot.
"
echo "$outputinfo"
exit 0
