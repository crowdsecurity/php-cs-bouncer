#!/bin/bash

# Check state.
if [ -z "${NEW_GIT_VERSION}" ]; then
    echo "No \$NEW_GIT_VERSION env var found. Exiting."
    exit 1
fi

# Initilize
platform='unknown'
unamestr=`uname`
if [[ "$unamestr" == 'Linux' ]]; then
   platform='linux'
elif [[ "$unamestr" == 'FreeBSD' ]]; then
   platform='freebsd'
elif [[ "$unamestr" == 'Darwin' ]]; then
   platform='osx'
fi
git_base_dir=`git rev-parse --show-toplevel`

# Update version everywhere (add and commit changes), tag and release
git checkout main
if [[ $platform == 'linux' ]]; then
   sed -i -E "s/v[0-9]+\.[0-9]+\.[0-9]/$NEW_GIT_VERSION/" $git_base_dir/src/Constants.php
else
   sed -i "" -E "s/v[0-9]+\.[0-9]+\.[0-9]/$NEW_GIT_VERSION/" $git_base_dir/src/Constants.php
fi
git add $git_base_dir/src/Constants.php

git tag $NEW_GIT_VERSION
git commit -m "bump version to $NEW_GIT_VERSION"
git push
git push origin $NEW_GIT_VERSION
gh release create --draft $NEW_GIT_VERSION --title $NEW_GIT_VERSION