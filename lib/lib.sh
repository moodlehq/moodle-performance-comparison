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

    ${mysqlcmd} -V > /dev/null
    errorcode=$?
    if [ "$errorcode" != 0 ]; then
        echo 'Error: Ensure $mysqlcmd'$genericstr
        exit $errorcode
    fi

    ${pgsqlcmd} --version > /dev/null
    errorcode=$?
    if [ "$errorcode" != 0 ]; then
        echo 'Error: Ensure $pgsqlcmd'$genericstr
        exit $errorcode
    fi

    ${mysqldumpcmd} -V > /dev/null
    errorcode=$?
    if [ "$errorcode" != 0 ]; then
        echo 'Error: Ensure $mysqldumpcmd'$genericstr
        exit $errorcode
    fi

    ${pgsqldumpcmd} --version > /dev/null
    errorcode=$?
    if [ "$errorcode" != 0 ]; then
        echo 'Error: Ensure $pgsqldumpcmd'$genericstr
        exit $errorcode
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

    # rm + add as it can change.
    # Only if it exists.
    ${gitcmd} ls-remote $2 --quiet
    if [ "$?" == "0" ]; then
        ${gitcmd} remote rm $2
    fi
    ${gitcmd} remote add $2 $1

    ${gitcmd} fetch $2 --quiet

    # Checking if it is a branch or a hash.
    ${gitcmd} show-ref --verify --quiet refs/remotes/$2/$3
    if [ $? == "0" ]; then

        # Checkout the last version of the branch.
        ${gitcmd} show-ref --verify --quiet refs/heads/$3
        if [ $? == "0" ]; then
            # Delete to avoid conflicts if there are git history changes.
            ${gitcmd} checkout master --quiet
            if [ "$3" == "master" ]; then
                # Master history is constant.
                ${gitcmd} rebase $2/master --quiet 2> /dev/null

                # In case there was a repository change master can be completely different.
                if [ $? != "0" ]; then
                    ${gitcmd} rebase --abort
                    ${gitcmd} checkout $2/master --quiet
                    ${gitcmd} branch -D master --quiet
                    ${gitcmd} checkout master --quiet
                fi
            else
                ${gitcmd} branch -D $3 --quiet
                ${gitcmd} checkout -b $3 $2/$3 --quiet
            fi
        else
            ${gitcmd} checkout -b $3 $2/$3 --quiet
        fi

    else
        # Just checkout the hash.
        ${gitcmd} checkout $3 --quiet
        if [ $? != "0" ]; then
            echo "Error: The provided branch/hash can not be found."
            exit 1
        fi
    fi

}

# Shows the time elapsed in hours, mins and secs.
show_elapsed_time()
{
    ((h=${1}/3600))
    ((m=(${1}%3600)/60))
    ((s=${1}%60))
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
    siteversion="$(cat version.php | grep '$version' | grep -o '[0-9].[0-9]\+')"
    sitebranch="$(cat version.php | grep '$branch' | grep -o '[0-9]\+')"
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
