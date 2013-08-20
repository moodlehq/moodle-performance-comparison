#!/bin/bash

##############################################
# Script to install the moodle site.
#
# Installs a moodle site based on the
# configured settings (config.properties). It
# also clones and installs moosh to generate
# the data.
#
# Usage: cd /path/to/moodle-performance-comparison && ./installsite.sh
#
##############################################

set -e

# Dependencies.
. ./lib/lib.sh

# Load properties
load_properties

# Download or update Moodle and moosh.
if [ ! -d "moodle" ]; then
    git clone $moodlerepo moodle
else
    cd moodle
    git fetch origin
    cd ../
fi
if [ ! -d "moodle/moosh" ]; then
    git clone git://github.com/dmonllao/moosh.git moodle/moosh
else
    cd moodle/moosh
    git fetch origin
    cd ../../
fi

# Create a Moodle config.php from the defined site properties.
replacements="%%dbtype%%#$dbtype
%%dbhost%%#$dbhost
%%dbname%%#$dbname
%%dbuser%%#$dbuser
%%dbpass%%#$dbpass
%%prefix%%#$prefix
%%wwwroot%%#$wwwroot
%%dataroot%%#$PWD/moodledata"

# Replace values from the config template.
configfilecontents="$( cat ./templates/config.php.template )"
for i in ${replacements}; do
    configfilecontents=$( echo "${configfilecontents}" | sed "s#${i}#g" )
done

## Save the config.php into dirroot.
echo "${configfilecontents}" > moodle/config.php
permissionsexitcode=$?
if [ "$permissionsexitcode" -ne "0" ] ; then
    echo "Error: Moodle's config.php can not be written, check `pwd`/moodle directory (and `pwd`/moodle/config.php if it exists) permissions"
    exit $permissionsexitcode
fi
chmod 755 moodle/config.php

# Creating moodledata directory.
if [ -d "moodledata" ]; then
    # TODO: Point to a drop.sh script as soon as is available.
    echo "Error: Site already installed, truncate the database and delete moodledata/ to reinstall it."
    exit 1
fi
mkdir -m 777 "moodledata"

# Move to moodle site dir.
cd moodle

# Checkout the specified branch and rebase in case is not the first install.
git checkout $branch
git rebase origin/$branch
branchexitcode=$?
if [ "$branchexitcode" -ne "0" ] ; then
    echo "Error: The specified branch does not exist, check your config.properties file."
    exit $branchexitcode
fi

# Run install from config.php.
php admin/cli/install_database.php --agree-license --adminuser="$adminusername" --adminpass="$adminpassword" --fullname="$sitefullname" --shortname="$siteshortname"
installexitcode=$?
if [ "$installexitcode" -ne "0" ] ; then
    echo "Error: Site can not be installed, check your config.properties values."
    exit $installexitcode
fi

# Update moosh composer dependencies.
cd moosh
curl http://getcomposer.org/installer | php
curlexitcode=$?
if [ "$curlexitcode" -ne "0" ] ; then
    echo "Error: composer.phar can not be downloaded from http://getcomposer.org/installer using curl. cd to moodle/moosh, follow http://getcomposer.org/download steps manually and run \"php composer.phar update\""
    exit $curlexitcode
fi
php composer.phar update

echo ""
echo "Installation completed successfully"
