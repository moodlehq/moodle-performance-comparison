#!/bin/bash

##
# Tests the intial setup of the tool to avoid big problems.
#
# Those tests are fragile, but they are better than nothing.
#
# Note that this script should run just after a checkout
# as it is supposed to begin from a clear base and it's
# purpose is to test the tool setup.
#
# Usage:
#   cd tests/
#   ./test.sh
##

# Cleans the site data after running this script.
clean_test()
{
    rm webserver_config.properties
    rm jmeter_config.properties

    if [ ! -z "$cwd" ] && [ -d "$cwd/testdataroot" ]; then
        rm -rf "$cwd/testdataroot"
    fi
}

# Looks for the expected string in the command output.
# $1 => command, $2 => expected output substring, $3 => error msg.
check_output()
{
    output=$( $1 )
    if [[ "$output" != *"$2"* ]]; then
        echo "Test failed: "$output
        echo $3

        # Clean the tool.
        clean_test

        exit 1
    fi
}

################################################################

# Not set -e here.

# Hardcoded values #########
cwd=`pwd`

# Move to the tool dir.
cd ..

# Ensure that the tool is not set up. This should be a framework level failure.
if [ -f "moodle" ] || [ -f "webserver_config.properties" ] || [ -f "jmeter_config.properties" ]; then
    echo "Error: The tool has been previously used or initialized, checkout a new clone and run the tests there."
    exit 1
fi


# We should receive an error when trying to run the tool without config file.
check_output "./compare.sh" "Properties file does not exist" "The tool should not work without configuration files"

# We should receive errors when we just copy the config files.
cp webserver_config.properties.dist webserver_config.properties
cp jmeter_config.properties.dist jmeter_config.properties
check_output "./compare.sh" "/your/dataroot/directory" "/your/dataroot/directory should not be created"

clean_test

# Return to tests/ just in case.
cd tests

echo "All tests passed :)"
exit 0
