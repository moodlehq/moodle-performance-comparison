#!/bin/bash

##############################################
# Runs the whole scripts chain and opens the browser once finished to compare results
#
# Note that this is only useful when running jmeter in the moodle site server
# and you can only rely on results like db queries, memory or files included,
# as this is no controlling the server load at all.
#
# Usage: cd /path/to/moodle-performance-comparison && ./compare.sh
#
##############################################

# Exit on errors.
set -e

# Dependencies.
. ./lib/lib.sh

# Get user info.
load_properties "defaults.properties"
load_properties "webserver_config.properties"

# Checks the $cmds.
check_cmds

timestart=`date +%s`

# Runs descriptions according to branches.
# Group name according to date.
groupname="compare_"`date '+%Y%m%d%H%M'`

# Hardcoding S as the size, with 5 loops is enough to have consistent results.
./before_run_setup.sh S || throw_error "Before run setup didn't finish as expected"

./test_runner.sh "$groupname" "before" || throw_error "The before test run didn't finish as expected"

# We don't restart the browser here, this is a development machine
# and probably you are not staring at the CLI waiting for it to
# finish.

./after_run_setup.sh || throw_error "After run setup didn't finish as expected"

./test_runner.sh "$groupname" "after" || throw_error "The after test run didn't finish as expected"

timeend=`date +%s`

# Output time elapsed.
elapsedtime=$[$timeend - $timestart]
show_elapsed_time $elapsedtime
output="
#######################################################################
Comparison test finished successfully.
"
echo "$outputinfo"

# Opens the comparison web interface in a browser.
if [[ "$OSTYPE" == "darwin"* ]];then
    open -a $browser "$wwwroot/../"
else
    $browser "$wwwroot/../"
fi
