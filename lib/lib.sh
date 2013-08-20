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

    # Static properties.
    . ./static.properties
}

# A 'size' argument can only be one of those values.
check_size_value()
{
    if [ "$1" != "small" -a "$1" != "medium" -a "$1" != "big" ]; then
        echo "Error: The accepted values are small, medium and big"
        exit 1
    fi
}

# Creates an user.
create_user()
{

    # Creates the user.
    id="$(moosh/moosh.php user-create --auth manual --password moodle $1)"
    # All went ok, extremely optimistic no, we don't want output.
    echo $id | egrep '^[0-9]+$'
    if [ $? -ne 0 ]; then
        echo "Error: Something went wrong creating '$1'"
    fi

    createdusers+=($id)
}

# Creates a course and adds resources.
create_course()
{

    # Creates the course.
    courseid="$(moosh/moosh.php course-create $1)"
    # All went ok, extremely optimistic no, we don't want output.
    echo $courseid | egrep '^[0-9]+$'
    if [ $? -ne 0 ]; then
        echo "Error: Something went wrong creating '$1'"
        exit 1
    fi

    createdcourses[$2]=$courseid

    # Create a few module instances.
    modules="assign data page quiz forum label"
    for modname in ${modules}; do

        # The number of instances is specified in static.properties.
        ninstances=$(eval "echo \$$(echo $modname)")
        for i in `seq 1 $ninstances`; do
            moosh/moosh.php activity-add --name "$modname $i" "$modname" "$courseid"
        done
    done

}

# Enrolls $createdusers to $createdcourses.
enrol_users_to_courses()
{

    ncourses="${#createdcourses[@]}"

    # This sounds bad.
    if [ "$ncourses" -lt "$enrolsperuser" ]; then
        "Error: The number of enrolments per course can not be bigger than the number of courses. Courses: $ncourses, Number of enrolments per user: $enrolsperuser"
        exit 1
    fi

    ienrolsperuser=0
    while [ $ienrolsperuser -lt $enrolsperuser ]; do
        for userid in "${createdusers[@]}"; do

            # Starts courseid and icourse.
            get_next_course

            # Check that the user is not already in this course.
            if [ -n "${enrolments[$userid]}" ]; then

                # Convert to an array.
                userenrolments=(${enrolments[$userid]}) 

                # Prevent infinite loops.
                if [ ${#userenrolments[@]} -ge $ncourses ]; then
                    echo "Error: Too much enrolments for each user"
                    exit 1
                fi

                # Get the next course until we find one where the user is not enrolled.
                found=0
                for enrolledcourse in "${userenrolments[@]}"; do
                    if [ "$enrolledcourse" == "$courseid" ]; then
                        found=1
                    fi
                done
                if [ $found -eq 1 ]; then
                    get_next_course
                fi
            fi

            # Enrolling the user.
            enrol_user_to_course

        done
        ienrolsperuser=`expr $ienrolsperuser + 1`
    done
}

# Enrols an user to a course.
enrol_user_to_course()
{
    # Enrolling the user.
    moosh/moosh.php course-enrol -r student -i $courseid $userid

    # Store the enrolment relation for further checking.
    if [ -n "${enrolments[$userid]}" ]; then
        enrolments[$userid]=${enrolments[$userid]}' '$courseid
    else
        enrolments[$userid]=$courseid
    fi
}

# Gets the next course where $userid can be enrolled.
get_next_course()
{
    # Start from 0 if is the first enrolment of the script.
    if [ -z "$icourse" ]; then
        icourse=$COUNTERSTART
    else
        icourse=`expr $icourse + 1`
    fi

    # Get the next course id.
    courseid="${createdcourses[$icourse]}"

    # Restart from the first course if we reached the last one.
    if [ -z "$courseid" ]; then
        icourse=$COUNTERSTART
        courseid="${createdcourses[$COUNTERSTART]}"
    fi
}

