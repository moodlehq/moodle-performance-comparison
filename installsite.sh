#!/bin/bash

##############################################
# Script to install the moodle site.
#
# Installs a moodle site based on the
# configured settings (config.properties). It
# also clones and installs moosh to generate
# the data.
#
# Usage: sh ./installsite.sh
#
##############################################

# Dependencies.
. ./lib/lib.sh

# Load properties
load_properties

# Download Moodle and moosh.
git clone git://github.com/moodle/moodle.git moodle
git clone git://github.com/dmonllao/moosh.git moodle/moosh

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
configfilecontents="$( cat ./config.php.template )"
for i in ${replacements}; do
    configfilecontents=$( echo "${configfilecontents}" | sed "s#${i}#g" )
done

## Save the config.php into dirroot.
echo "${configfilecontents}" > moodle/config.php
permissionsexitcode=$?
if [ "$permissionsexitcode" -ne "0" ] ; then
    echo "Moodle's config.php can not be written, check `pwd`/moodle directory (and `pwd`/moodle/config.php if it exists) permissions"
    exit $permissionsexitcode
fi
chmod 755 moodle/config.php

# Move to moodle site dir.
cd moodle

# Checkout the specified branch.
git checkout $branch
branchexitcode=$?
if [ "$branchexitcode" -ne "0" ] ; then
    echo "The specified branch does not exist, check your config.properties file."
    exit $branchexitcode
fi

# Run install from config.php.
php admin/cli/install_database.php --agree-license --adminuser=$adminusername --adminpass="$adminpassword" --fullname="$sitefullname" --shortname="$siteshortname"
installexitcode=$?
if [ "$installexitcode" -ne "0" ] ; then
    echo "Error installing the site, check your config.properties values."
    exit $installexitcode
fi

# Update moosh composer dependencies
cd moosh
curl http://getcomposer.org/installer | php
curlexitcode=$?
if [ "$curlexitcode" -ne "0" ] ; then
    echo "Error downloading composer.phar from http://getcomposer.org/installer using curl, in moodle/moosh follow http://getcomposer.org/download manually and run 'php composer.phar update'"
    exit $curlexitcode
fi
php composer.phar update

