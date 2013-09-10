#!/bin/bash

# Loads configuration and static vars. Should be a first include before moving to other directories.
# For non-config files the caller script should check that the file exists to provide a more acurate error message.
load_properties()
{
    # User configured properties.
    configfile="./$1"
    if [ ! -r $configfile ]; then
        echo "Error: Properties file does not exist, copy $1.dist to $1 and edit the values according to your system"
        exit 1
    fi
    . $configfile
}
