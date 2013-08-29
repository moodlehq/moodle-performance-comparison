#!/bin/bash

##############################################
# Script to run the test plan using jmeter
#
# Runs will be grouped according to $1 so they
# can be compared easily. The run description
# will be useful to identify them.
#
# Usage: cd /path/to/moodle-performance-comparison && ./testrunner.sh {test_plan_file_path} {users_file_path} {run_group_name} {run_description}
#
# Arguments:
# * $1 => The test plan file path
# * $2 => The path to the file with user's login data
# * $3 => The run group name, there will be comparision graphs by this group name
# * $4 => The run description, useful to identify the changes between runs.
#
##############################################

set -e

# Dependencies.
. ./lib/lib.sh

if [ -z "$4" ]; then
    echo "Usage: `basename $0` {test_plan_file_path} {users_file_path} {run_group} {run_description}"
    exit 1
fi

# Load properties.
load_properties

# Uses the test plan specified in the CLI call.
logfile=logs/jmeter.`date '+%Y%m%d_%H%M'`.log

# Run it baby! (without GUI).
jmeterbin=$jmeter_path/bin/jmeter
$jmeterbin -n -j "$logfile" -t "$1" -Jusersfile="$2" -Jgroup="$3" -Jdesc="$4"
jmeterexitcode=$?
if [ "$jmeterexitcode" -ne "0" ] ; then
    echo "Error: Jmeter can not run, ensure that:"
    echo "* The test plan and the users files are ok"
    echo "* You provide correct arguments to the script"
    exit $jmeterexitcode
fi

echo ""
echo "Test plan completed successfully"
