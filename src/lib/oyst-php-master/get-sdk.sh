#!/bin/bash

# Download release of Oyst PHP SDK

GitHub_Owner="oystparis"
GitHub_Repo="oyst-php"
GitHub_Release=

# Do not change under this comment

function download_sdk {
    RET=1
    while [ "$RET" -ne "0" ]; do
        echo exit | curl -sL $GitHubProjectReleaseUrl -o $GitHub_Repo-$GitHub_Release.tar.gz
        RET=$?
        if [ "$RET" -ne "0" ]; then
            echo "Retry download Oyst SDK."
        fi
       sleep 5
    done
}

ScriptDir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $ScriptDir
rm -rf $GitHub_Owner-$GitHub_Repo-* $GitHub_Repo $GitHub_Repo.tar.gz

if [ -n "$GitHub_Release" ]; then
    # Specified release url
    GitHubProjectReleaseUrl=https://api.github.com/repos/$GitHub_Owner/$GitHub_Repo/tarball/$GitHub_Release
else
    # Latest release url
    GitHubProjectLatestReleasesUrl=https://api.github.com/repos/$GitHub_Owner/$GitHub_Repo/releases/latest
    GitHubProjectReleaseUrl=$(curl -LsS --connect-timeout 5 --max-time 10 --retry 5 --retry-delay 0 --retry-max-time 60 $GitHubProjectLatestReleasesUrl | grep 'tarball_url' | cut -d\" -f4)
    GitHub_Release=$(curl -LsS --connect-timeout 5 --max-time 10 --retry 5 --retry-delay 0 --retry-max-time 60 $GitHubProjectLatestReleasesUrl | grep 'tag_name' | cut -d\" -f4)
fi

download_sdk
tar -xzf $GitHub_Repo-$GitHub_Release.tar.gz
mv $GitHub_Owner-$GitHub_Repo-* $GitHub_Repo
echo "Oyst SDK $GitHub_Release is downloaded in $ScriptDir."

# Composer Install
cd $GitHub_Repo
composer install --no-dev
echo "Composer install done."
exit 0