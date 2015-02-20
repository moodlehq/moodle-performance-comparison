#!/bin/bash
#
# Cleans the web application cache.
#
# This script needs to be run by the user
# running the web server.
#
# e.g. \$sudo -u www-data ./clean_webapp_data.sh
#
##############################################

# Exit on errors.
set -e

# Dependencies.
. ./lib/lib.sh

# Also images cache.
delete_files "cache/*"

outputinfo="
#######################################################################
Web application cached results deleted successfully.
"
echo "$outputinfo"
exit 0
