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

# Checks out the specified branch codebase for the specified repository
# $1 = repo, $2 = remote alias, $3 = branch
checkout_branch()
{
    # Getting the code.
    if [ ! -e ".git" ]; then
        git init
    fi

    # rm + add as it can change.
    # Only if it exists.
    git ls-remote $2 --quiet 2> /dev/null
    if [ "$?" == "0" ]; then
        git remote rm $2
    fi
    git remote add $2 $1

    git fetch $2 --quiet

    # Checking if it is a branch or a hash.
    git show-ref --verify --quiet $3
    if [ $? == "0" ]; then

        # Checkout the last version of the branch.
        git show-ref --verify --quiet refs/heads/$3
        if [ $? == "0" ]; then
            # Deleting old local branch in case there are history changes so we avoid conflicts.
            git checkout master --quiet
            git branch -D $3 --quiet
        fi
        git checkout -b $3 $2/$3

    else
        # Just checkout the hash.
        git checkout $3
    fi

}
