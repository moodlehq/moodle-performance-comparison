#!/bin/bash

##############################################
# Runs the whole scripts chain and opens the browser once finished to compare results
#
# Note that this is only useful when running jmeter in the moodle site server
# and you can only rely on results like db queries, memory or files included,
# as this is no controlling the server load at all.
#
# Usage: cd /path/to/moodle-performance-comparison && ./compare.sh {size}
#
# Arguments:
# * $1 => Size, one of the following options: XS, S, M, L, XL, XXL. More than 'M' is not recommended for development computers.
#
##############################################

#set -e

# Dependencies.
. ./lib/lib.sh

# Get user info.
load_properties "webserver_config.properties"

timestart=`date +%s`

# We need the size.
if [ -z "$1" ]; then
    output="Usage: `basename $0` {size}

Sets up the sites and performs both test runs.

Arguments:
* $1 => Size, one of the following options: XS, S, M, L, XL, XXL. More than 'M' is not recommended in development computers.
"
    echo "$output"
    exit 1
fi

# Runs descriptions according to branches.
# Group name according to date.
groupname="compare_"`date '+%Y%m%d%H%M'`

./before_run_setup.sh $1
./test_runner.sh "$groupname" "before" -l 5
./after_run_setup.sh
./test_runner.sh "$groupname" "after" -l 5

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
$browser "$wwwroot/../"
