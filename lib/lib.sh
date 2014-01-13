#!/bin/bash
#
# Common functions.

################################################
# Checks that last command was successfully executed
# otherwise exits showing an error.
#
# Arguments:
#   * $1 => The error message
#
################################################
throw_error()
{
    local errorcode=$?
    if [ "$errorcode" -ne "0" ]; then

        # Print the provided error message.
        if [ ! -z "$1" ]; then
            echo "Error: $1" >&2
        fi

        # Exit using the last command error code.
        exit $errorcode
    fi
}

################################################
# Deletes the files
#
# Arguments:
#   * $1 => The file/directories to delete
#   * $2 => Set $2 will make the function exit if it is an unexisting file
#
# Accepts dir/*.extension format like ls or rm does.
#
################################################
delete_files()
{
    # Checking that the provided value is not empty or it is a "dangerous" value.
    # We can not prevent anything, just a few of them.
    if [ -z "$1" ] || \
            [ "$1" == "." ] || \
            [ "$1" == ".." ] || \
            [ "$1" == "/" ] || \
            [ "$1" == "./" ] || \
            [ "$1" == "../" ] || \
            [ "$1" == "*" ] || \
            [ "$1" == "./*" ] || \
            [ "$1" == "../*" ]; then
        echo "Error: delete_files() does not accept \"$1\" as something to delete" >&2
        exit 1
    fi

    # Checking that the directory exists. Exiting as it is a development issue.
    if [ ! -z "$2" ]; then
        test -e "$1" || \
            throw_error "The provided \"$1\" file or directory does not exist or is not valid."
    fi

    # Kill them all (ok, yes, we don't always require that options).
    rm -rf "$1"
}

################################################
# Checks that the provided cmd commands are properly set.
#
################################################
check_cmds()
{
    local readonly genericstr=" has a valid value or overwrite the default one using webserver_config.properties"

    ${phpcmd} -v > /dev/null || \
        throw_error 'Ensure $phpcmd'$genericstr

    # Only if mysql is being used.
    if [ "$dbtype" == "mysqli" ]; then
        ${mysqlcmd} -V > /dev/null || \
            throw_error 'Ensure $mysqlcmd'$genericstr

        ${mysqldumpcmd} -V > /dev/null || \
            throw_error 'Ensure $mysqldumpcmd'$genericstr
    fi

    # Only if pgsql is being used.
    if [ "$dbtype" == "pgsql" ]; then
        ${pgsqlcmd} --version > /dev/null || \
            throw_error 'Ensure $pgsqlcmd'$genericstr

        ${pgsqldumpcmd} --version > /dev/null || \
            throw_error 'Ensure $pgsqldumpcmd'$genericstr
    fi

    ${gitcmd} version > /dev/null || \
        throw_error 'Ensure $gitcmd'$genericstr

    ${curlcmd} -V > /dev/null || \
        throw_error 'Ensure $curlcmd'$genericstr
}

################################################
# Loads configuration and static vars.
#
# Should be a first include before moving to other directories.
#
# For non-config files the caller script should check that the
# file exists to provide a more acurate error message.
#
# Arguments:
#   $1 => The file to include
#
################################################
load_properties()
{
    # User configured properties.
    local configfile="./$1"
    if [ ! -r "$configfile" ]; then
        echo "Error: Properties file does not exist, copy $1.dist to $1 and edit the values according to your system" >&2
        exit 1
    fi
    . $configfile
}

################################################
# Checks out the specified branch codebase for the specified repository
#
# Arguments:
#   $1 => repo
#   $2 => remote alias
#   $3 => branch
#
################################################
checkout_branch()
{

    # Getting the code.
    if [ ! -e ".git" ]; then
        ${gitcmd} init --quiet
    fi

    # Add/update the remote if necessary.
    local remotes="$( ${gitcmd} remote show )"
    if [[ "$remotes" == *$2* ]] || [ "$remotes" == "$2" ]; then

        # Remove the remote if it already exists and it is different.
        local remoteinfo="$( ${gitcmd} remote show "$2" -n | head -n 3 )"
        if [[ ! "$remoteinfo" == *$1* ]]; then
            ${gitcmd} remote rm $2 || \
                throw_error "$1 remote value you provide can not be removed. Check webserver_config.properties.dist"
            ${gitcmd} remote add $2 $1 || \
                throw_error "$1 remote value you provided can not be added as $2. Check webserver_config.properties.dist"
        fi
    # Add it if it is not there.
    else
        ${gitcmd} remote add $2 $1 || \
            throw_error "$1 remote can not be added as $2. Check webserver_config.properties.dist"
    fi

    # Fetching from the repo.
    ${gitcmd} fetch $2 --quiet || \
        throw_error "$2 remote can not be fetched. Check webserver_config.properties.dist"

    # Checking if it is a branch or a hash.
    local isareference="$( ${gitcmd} show-ref | grep "refs/remotes/$2/$3" | wc -l )"
    if [ "$isareference" == "1" ]; then

        # Checkout the last version of the branch.
        # Reset to avoid conflicts if there are git history changes.
        ${gitcmd} checkout -B $3 $2/$3 --quiet || \
            throw_error "The '$3' tag or branch you provided does not exist or it is not set. Check webserver_config.properties.dist"

    else
        # Just checkout the hash and let if fail if it is incorrect.
        ${gitcmd} checkout $3 --quiet || \
            throw_error "The '$3' hash you provided does not exist or it is not set. Check webserver_config.properties.dist"

    fi

}

################################################
# Shows the time elapsed in hours, mins and secs.
#
# Arguments:
#   $1 => Number of seconds
#
################################################
show_elapsed_time()
{
    local h=$((${1}/3600))
    local m=$(((${1}%3600)/60))
    local s=$((${1}%60))
    printf "Elapsed time: %02d:%02d:%02d\n" $h $m $s
}

################################################
# Creates a file with data about the site.
#
# Requires scripts to move to moodle/ before
# calling it and returning to root if necessary.
#
################################################
save_moodle_site_data()
{

    # We should already be in moodle/.
    if [ ! -f "version.php" ]; then
        echo "Error: save_moodle_site_data() should only be called after cd to moodle/" >&2
        exit 1
    fi

    # Getting the current site data.
    local siteversion="$(cat version.php | \
        grep '$version' | \
        grep -o '[0-9]\+.[0-9]\+' | \
        head -n 1)"
    local sitebranch="$(cat version.php | \
        grep '$branch' | \
        grep -o '[0-9]\+' | \
        head -n 1)"
    local sitecommit="$(${gitcmd} show --oneline | \
        head -n 1 | \
        sed 's/\"/\\"/g')"

    local sitedatacontents="siteversion=\"$siteversion\"
sitebranch=\"$sitebranch\"
sitecommit=\"$sitecommit\""

    echo "${sitedatacontents}" > site_data.properties || \
        throw_error "Site data properties file can not be written, check $currentwd/moodle directory permissions."
}
