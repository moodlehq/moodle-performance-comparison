#!/bin/bash

##############################################
# Script to insert data to the moodle site.
#
# Optimistic point of view about how moosh will
# work, so we don't output errors.
#
# Usage: cd /path/to/moodle-performance-comparison && ./datagenerator.sh {small|medium|big}
#
# Arguments:
# * $1 => How much data do you want to generate
#
##############################################

set -e

# Useful when developing, to avoid duplicates into the database.
COUNTERSTART=1

# Dependencies.
. ./lib/lib.sh

# User should specify how much data will be generated.
if [ -z "$1" ]; then
    echo "Usage: `basename $0` {small|medium|big}"
    exit 1
fi

# Check if $1 has an appropriate value
check_size_value $1

# Load properties
load_properties

testusers=$(eval "echo \$$(echo $1_testusers)")
testcourses=$(eval "echo \$$(echo $1_testcourses)")
enrolsperuser=$(eval "echo \$$(echo $1_enrolsperuser)")
otherusers=$(eval "echo \$$(echo $1_otherusers)")
othercourses=$(eval "echo \$$(echo $1_othercourses)")

# Move to the Moodle site.
cd moodle

# Arrays to store the created identifiers.
createdusers=()
createdcourses=()

# Create users for the tests.
testusers=`expr $COUNTERSTART + $testusers`
for ((iuser=$COUNTERSTART; iuser<$testusers; iuser++)); do
    create_user "testuser_$iuser"
done

# Create courses for the tests.
testcourses=`expr $COUNTERSTART + $testcourses`
for ((icourse=$COUNTERSTART; icourse<$testcourses; icourse++)); do
    create_course "testcourse_$icourse" "$icourse"
done

# Enrols $testusers users to $testcourses courses.
enrol_users

# Create additional users (not used in tests).
otherusers=`expr $otherusers + $testusers`
for ((iuser=$testusers; iuser<$otherusers; iuser++)); do
    create_user "user_$iuser"
done

# Create additional courses (not used in tests).
othercourses=`expr $othercourses + $testcourses`
for ((icourse=$testcourses; icourse<$otherusers; icourse++)); do
    create_course "course_$icourse"
done


# Return to root.
cd ..

# Generate csv file with the test users list.
csvcontents=""
for ((i=$COUNTERSTART; i<$testusers; i++)); do
    csvcontents=$csvcontents"testuser_$i,moodle
"
done

# Store it in resources/.
echo "${csvcontents}" > "testplandata/users_$1_$branch.csv"

# TODO Change test plan depending on $size vars, otherwise this template thing is useless.
text="$( cat ./templates/testplan_$1.jmx.template )"
echo "${text}" > "testplandata/testplan_$1_$branch.jmx"

# We will need to know what the hell we have done.
# Single file as we should get rid of all previous generated data.
echo "size=$1" > "testplandata/generateddata.properties"

echo ""
echo "Data generation completed successfully"
