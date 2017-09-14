#!/bin/bash

#if [[ -z "$TRAVIS" ]]; then
#	echo "Script is only to be run by Travis CI" 1>&2
#	exit 1
#fi

if [[ -z "$WP_ORG_PASSWORD" ]]; then
	echo "WordPress.org password not set" 1>&2
	exit 1
fi




WP_ORG_USERNAME="oyst1click" #ok
PLUGIN="woo-oyst" #ok
PROJECT_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
echo "project root :" PROJECT_ROOT
PLUGIN_BUILDS_PATH="$PROJECT_ROOT/src"
echo "project build path :" PLUGIN_BUILDS_PATH

VERSION="v1.0.2"
#VERSION=$(/usr/bin/php -f "$PLUGIN_BUILD_CONFIG_PATH/utils/get_plugin_version.php" "$PROJECT_ROOT" "$PLUGIN")
#Version -> get from tag of readme file

#ZIP_FILE="$PLUGIN_BUILDS_PATH/$PLUGIN-$VERSION.zip"
ZIP_FILE="$PLUGIN_BUILDS_PATH/$PLUGIN.zip"

# Ensure the zip file for the current version has been built
if [ ! -f "$ZIP_FILE" ]; then
    echo "Built zip file $ZIP_FILE does not exist" 1>&2
    exit 1
fi

# Check if the tag exists for the version we are building
TAG=$(svn ls "https://plugins.svn.wordpress.org/$PLUGIN/tags/$VERSION")
error=$?
if [ $error == 0 ]; then
    # Tag exists, don't deploy
    echo "Tag already exists for version $VERSION, aborting deployment"
    exit 1
fi

cd "$PLUGIN_BUILDS_PATH"
# Remove any unzipped dir so wwoo-oyst (4).zipe start from scratch
rm -fR "$PLUGIN"
# Unzip the built plugin
unzip -q -o "$ZIP_FILE"

# Clean up any previous svn dir
rm -fR svn
echo "clean previous dir"
# Checkout the SVN repo
svn co -q "http://svn.wp-plugins.org/$PLUGIN" svn
echo "checkout svn"
# Move out the trunk directory to a temp location
mv svn/trunk ./svn-trunk
echo "move trunk"
# Create trunk directory
mkdir svn/trunk
echo "create new trunk"
# Copy our new version of the plugin into trunk
rsync -r -p $PLUGIN/* svn/trunk
echo "copy to new trunk"

# Copy all the .svn folders from the checked out copy of trunk to the new trunk.
# This is necessary as the Travis container runs Subversion 1.6 which has .svn dirs in every sub dir
cd svn/trunk/
TARGET=$(pwd)
cd ../../svn-trunk/
echo "enter to the trunk"
# Find all .svn dirs in sub dirs
SVN_DIRS=`find . -type d -iname .svn`
echo "find svn dir"
for SVN_DIR in $SVN_DIRS; do
    SOURCE_DIR=${SVN_DIR/.}
    TARGET_DIR=$TARGET${SOURCE_DIR/.svn}
    TARGET_SVN_DIR=$TARGET${SVN_DIR/.}
    if [ -d "$TARGET_DIR" ]; then
        # Copy the .svn directory to trunk dir
        cp -r $SVN_DIR $TARGET_SVN_DIR
    fi
done

# Back to builds dir
cd ../

# Remove checked out dir
rm -fR svn-trunk
echo "remove trunk"
# Add new version tag
mkdir svn/tags/$VERSION
echo "create new trunk"
rsync -r -p $PLUGIN/* svn/tags/$VERSION

# Add new files to SVN
svn stat svn | grep '^?' | awk '{print $2}' | xargs -I x svn add x@
echo "add new file to svn"
# Remove deleted files from SVN
svn stat svn | grep '^!' | awk '{print $2}' | xargs -I x svn rm --force x@
svn stat svn
echo "remove deleted files"
# Commit to SVN
svn ci --no-auth-cache --username $WP_ORG_USERNAME --password $WP_ORG_PASSWORD svn -m "Deploy version $VERSION"
echo "commit to svn"
# Remove SVN temp dir
rm -fR svn
echo "remove tmp dir"