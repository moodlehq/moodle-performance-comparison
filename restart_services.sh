#!/bin/bash

##############################################
# Restarts the services
#
# Not specially compatible with a wide range of server
# settings, at least it works in linux-ubuntu.
#
# It restarts the services as listed in $servicesarray
# which are defined in webserver_config.properties.
#
##############################################

set -e

# Dependencies.
. ./lib/lib.sh

# Load webserver-side config.
load_properties "webserver_config.properties"

# Only root access; prevents ugly service command error messages.
if [ "$(id -u)" != "0" ]; then
    echo "Error: You can only run this script as root"
    exit 1
fi

# Stop all the services
for service in "${servicesarray[@]}"
do
    service $service stop
done

# Start them again.
for service in "${servicesarray[@]}"
do
    service $service start
done

outputinfo="
#######################################################################
Services restarted successfully
"
echo "$outputinfo"
exit 0
