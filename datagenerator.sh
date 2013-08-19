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

# Create users for the tests.
testusers=`expr $COUNTERSTART + $testusers`
for ((i=$COUNTERSTART; i<$testusers; i++)); do

    id="$(moosh/moosh.php user-create --auth manual --password moodle testuser_$i)"
    # All went ok, extremely optimistic no, we don't want output.
    echo $id | egrep '^[0-9]+$'
    if [ $? -eq 0 ]; then
        createdusers[$i]=$id
    fi
done

# Create courses for the tests.
testcourses=`expr $COUNTERSTART + $testcourses`
for ((i=$COUNTERSTART; i<$testcourses; i++)); do

    id="$(moosh/moosh.php course-create testcourse_$i)"
    # All went ok, extremely optimistic no, we don't want output.
    echo $id | egrep '^[0-9]+$'
    if [ $? -eq 0 ]; then
        createdcourses[$i]=$id
    fi
done

# Enrols $testusers users to $testcourses courses.
enrol_users

# Create additional users (not used in tests).
i=`expr $testusers + 1`
otherusers=`expr $otherusers + $i`
for ((i=$i; i<$otherusers; i++)); do
    moosh/moosh.php user-create --auth manual --password moodle user_$i
done

# Create additional courses (not used in tests).
i=`expr $testcourses + 1`
othercourses=`expr $othercourses + $i`
for ((i=$i; i<$otherusers; i++)); do
    moosh/moosh.php course-create course_$i
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
