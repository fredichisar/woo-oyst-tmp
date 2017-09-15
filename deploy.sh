#!/bin/bash

ROOT_PATH=$(pwd)"/"
PLUGIN_SLUG="woo-oyst"
TEMP_SVN_REPO="/tmp/$PLUGIN_SLUG"
SVN_REPO="http://plugins.svn.wordpress.org/"${PLUGIN_SLUG}"/"
SVNUSER="oyst1click"
PLUGINDIR=$(pwd)
MAINFILE="$PLUGIN_SLUG.php"


# Check version in readme.txt is the same as plugin file after translating both to unix line breaks to work around grep's failure to identify mac line breaks
PLUGINVERSION=`grep "Version:" $PLUGINDIR/$MAINFILE | awk -F' ' '{print $NF}' | tr -d '\r'`
echo "$MAINFILE version: $PLUGINVERSION"
READMEVERSION=`grep "^Stable tag:" $PLUGINDIR/README.txt | awk -F' ' '{print $NF}' | tr -d '\r'`
echo "readme.txt version: $READMEVERSION"

if [ "$READMEVERSION" = "trunk" ]; then
	echo "Version in readme.txt & $MAINFILE don't match, but Stable tag is trunk. Let's proceed..."
elif [ "$PLUGINVERSION" != "$READMEVERSION" ]; then
	echo "Version in readme.txt & $MAINFILE don't match. Exiting...."
	exit 1;
elif [ "$PLUGINVERSION" = "$READMEVERSION" ]; then
	echo "Versions match in readme.txt and $MAINFILE. Let's proceed..."
fi

# CHECKOUT SVN DIR IF NOT EXISTS
if [[ ! -d $TEMP_SVN_REPO ]];
then
	echo "Checking out WordPress.org plugin repository"
	svn checkout $SVN_REPO  || { echo "Unable to checkout repo."; exit 1; }
fi

cd $PLUGIN_SLUG

cd tags
touch $PLUGINVERSION
currentVersion=$(ls [[:digit:]]+.[[:digit:]]+.[[:digit:]]+ | sort -V -r | head -n 1)
if [[ $PLUGINVERSION != $currentVersion ]];
then
    echo "Current tag different ($currentVersion)"
    exit 1;
fi

cd ..

svn rm trunk
mkdir trunk

rsync -av --exclude='woo-oyst' --exclude='.git' --exclude='*.sh' ../* trunk

rm -rf trunk/woo-oyst *.sh *.yml

svn add trunk/
svn ci --non-interactive  --username $SVNUSER --password $WP_ORG_PASSWORD -m "Deploy version $VERSION"
svn copy --non-interactive --username $SVNUSER --password $WP_ORG_PASSWORD $SVN_REPO/trunk/* \
 $SVN_REPO/tags/$PLUGINVERSION  -m "Release ${PLUGINVERSION}"
