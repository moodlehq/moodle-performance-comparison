#!/bin/bash

# Loads configuration and static vars. Should be a first include before moving to other directories.
load_properties()
{
    # User configured properties.
    configfile="./config.properties"
    if [ ! -r $configfile ]; then
        echo "Error: Config file does not exist, copy config.properties.dist and edit the values according to your system"
        exit 1
    fi
    . $configfile
}
