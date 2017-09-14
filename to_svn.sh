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
READMEVERSION=`grep "^Stable tag:" $PLUGINDIR/readme.txt | awk -F' ' '{print $NF}' | tr -d '\r'`
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
ls -la
cd $PLUGIN_SLUG

svn rm trunk/*

cp -r ../* trunk

rm -rf trunk/woo-oyst *.sh *.yml


svn add trunk/
svn commit -m "test" --username $SVNUSER --password $WP_ORG_PASSWORD