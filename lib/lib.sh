#!/bin/bash

# Checks that the provided cmd commands are properly set.
check_cmds()
{
    genericstr=" has a valid value or overwrite the default one using webserver_config.properties"

    ${phpcmd} -v > /dev/null
    errorcode=$?
    if [ "$errorcode" != 0 ]; then
        echo 'Error: Ensure $phpcmd'$genericstr
        exit $errorcode
    fi

    # Only if mysql is being used.
    if [ "$dbtype" == "mysqli" ]; then
        ${mysqlcmd} -V > /dev/null
        errorcode=$?
        if [ "$errorcode" != 0 ]; then
            echo 'Error: Ensure $mysqlcmd'$genericstr
            exit $errorcode
        fi

        ${mysqldumpcmd} -V > /dev/null
        errorcode=$?
        if [ "$errorcode" != 0 ]; then
            echo 'Error: Ensure $mysqldumpcmd'$genericstr
            exit $errorcode
        fi
    fi

    # Only if pgsql is being used.
    if [ "$dbtype" == "pgsql" ]; then
        ${pgsqlcmd} --version > /dev/null
        errorcode=$?
        if [ "$errorcode" != 0 ]; then
            echo 'Error: Ensure $pgsqlcmd'$genericstr
            exit $errorcode
        fi

        ${pgsqldumpcmd} --version > /dev/null
        errorcode=$?
        if [ "$errorcode" != 0 ]; then
            echo 'Error: Ensure $pgsqldumpcmd'$genericstr
            exit $errorcode
        fi
    fi

    ${gitcmd} version > /dev/null
    errorcode=$?
    if [ "$errorcode" != 0 ]; then
        echo 'Error: Ensure $gitcmd'$genericstr
        exit $errorcode
    fi

    ${curlcmd} -V > /dev/null
    errorcode=$?
    if [ "$errorcode" != 0 ]; then
        echo 'Error: Ensure $curlcmd'$genericstr
        exit $errorcode
    fi
}

# Loads configuration and static vars. Should be a first include before moving to other directories.
# For non-config files the caller script should check that the file exists to provide a more acurate error message.
load_properties()
{
    # User configured properties.
    configfile="./$1"
    if [ ! -r "$configfile" ]; then
        echo "Error: Properties file does not exist, copy $1.dist to $1 and edit the values according to your system"
        exit 1
    fi
    . $configfile
}

# Checks out the specified branch codebase for the specified repository
# $1 = repo, $2 = remote alias, $3 = branch
checkout_branch()
{

    # Getting the code.
    if [ ! -e ".git" ]; then
        ${gitcmd} init --quiet
    fi

    # Add/update the remote if necessary.
    remotes="$( ${gitcmd} remote show )"
    if [[ "$remotes" == *$2* ]] || [ "$remotes" == "$2" ]; then
        # Remove the remote if it already exists and it is different.
        remoteinfo="$( ${gitcmd} remote show "$2" -n | head -n 3 )"
        if [[ ! "$remoteinfo" == *$1* ]]; then
            ${gitcmd} remote rm $2
            ${gitcmd} remote add $2 $1
        fi
    else
        ${gitcmd} remote add $2 $1
    fi
    remoteexitcode=$?
    if [ "$remoteexitcode" -ne "0" ]; then
        echo "Error: The '$1' remote value you provided does not exist or it is not set. Check webserver_config.properties.dist"
        exit $remoteexitcode
    fi


    ${gitcmd} fetch $2 --quiet

    # Checking if it is a branch or a hash.
    isareference="$( ${gitcmd} show-ref | grep "refs/remotes/$2/$3" | wc -l )"
    if [ "$isareference" == "1" ]; then

        # Checkout the last version of the branch.
        # Reset to avoid conflicts if there are git history changes.
        ${gitcmd} checkout -B $3 $2/$3 --quiet
        checkoutexitcode=$?
        if [ "$checkoutexitcode" -ne "0" ]; then
            echo "Error: The '$3' tag or branch you provided does not exist or it is not set. Check webserver_config.properties.dist"
            exit $checkoutexitcode
        fi

    else
        # Just checkout the hash and let if fail if it is incorrect.
        ${gitcmd} checkout $3 --quiet
        checkoutexitcode=$?
        if [ "$checkoutexitcode" -ne "0" ]; then
            echo "Error: The '$3' hash you provided does not exist or it is not set. Check webserver_config.properties.dist"
            exit $checkoutexitcode
        fi

    fi

}

# Shows the time elapsed in hours, mins and secs.
show_elapsed_time()
{
    h=$((${1}/3600))
    m=$(((${1}%3600)/60))
    s=$((${1}%60))
    printf "Elapsed time: %02d:%02d:%02d\n" $h $m $s
}

# Creates a file with data about the site. Requires scripts to 
# move to moodle/ before calling it and returning to root if necessary.
save_moodle_site_data()
{

    # We should already be in moodle/.
    if [ ! -f "version.php" ]; then
        echo "Error: save_moodle_site_data() should only be called after cd to moodle/"
        exit 1
    fi

    # Getting the current site data.
    siteversion="$(cat version.php | grep '$version' | grep -o '[0-9]\+.[0-9]\+' | head -n 1)"
    sitebranch="$(cat version.php | grep '$branch' | grep -o '[0-9]\+' | head -n 1)"
    sitecommit="$(${gitcmd} show --oneline | head -n 1)"

    sitedatacontents="siteversion=\"$siteversion\"
sitebranch=\"$sitebranch\"
sitecommit=\"$sitecommit\""

    echo "${sitedatacontents}" > site_data.properties
    permissionsexitcode=$?
    if [ "$permissionsexitcode" -ne "0" ]; then
        echo "Error: Site data properties file can not be written, check $currentwd/moodle directory permissions."
        exit $permissionsexitcode
    fi
}
