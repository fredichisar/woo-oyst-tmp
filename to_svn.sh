#! /bin/bash
# Based on hard work by Dean Clatworthy, Brent Shepherd, Patrick Rauland, Ben Balter and Gary Jones
# See https://github.com/ihorvorotnov/wordpress-plugin-git-to-svn-deploy for instructions and credits.

echo
echo "WordPress Plugin Git to SVN Deploy v1.0.0"
echo
echo "Step 1. Let's collect some information first."
echo
echo "Default values are in brackets - just hit enter to accept them."
echo



# Set up some default values. Feel free to change these in your own script
CURRENTDIR=`pwd`
PLUGINSLUG="woo-oyst"
SVNPATH="/tmp/$PLUGINSLUG"
SVNURL="http://plugins.svn.wordpress.org/$PLUGINSLUG"
SVNUSER="oyst1click"
default_plugindir="$CURRENTDIR/$PLUGINSLUG"
MAINFILE="$PLUGINSLUG.php"



echo "1e) Your local plugin root directory, the Git repo. No trailing slash."
printf "($default_plugindir): "
read -e  input
input="${input%/}" # Strip trailing slash
PLUGINDIR="${input:-$default_plugindir}" # Populate with default if empty
echo

echo "That's all of the data collected."
echo
echo "Slug: $PLUGINSLUG"
echo "Temp checkout path: $SVNPATH"
echo "Remote SVN repo: $SVNURL"
echo "SVN username: $SVNUSER"
echo "Plugin directory: $PLUGINDIR"
echo "Main file: $MAINFILE"
echo

printf "OK to proceed (Y|n)? "
read -e input
PROCEED="${input:-y}"
echo

# Allow user cancellation
if [ "$PROCEED" != "y" ]; then echo "Aborting..."; exit 1; fi


# Let's begin...
echo ".........................................."
echo
echo "Preparing to deploy WordPress plugin"
echo
echo ".........................................."
echo

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


echo
echo "Creating local copy of SVN repo trunk ..."
svn checkout $SVNURL $SVNPATH --depth immediates
svn update --quiet $SVNPATH/trunk --set-depth infinity

svn propset svn:mime-type image/jpeg *.jpg

echo "Ignoring GitHub specific files"
svn propset svn:ignore "README.md
LICENSE
Thumbs.db
.git
.gitignore" "$SVNPATH/trunk/"



echo "Changing directory to SVN and committing to trunk"
cd $SVNPATH/trunk/
# Delete all files that should not now be added.
svn status | grep -v "^.[ \t]*\..*" | grep "^\!" | awk '{print $2}' | xargs svn del
# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2}' | xargs svn add
svn commit --username=$SVNUSER -m "Preparing for $PLUGINVERSION release"



echo "Creating new SVN tag and committing it"
cd $SVNPATH
svn update --quiet $SVNPATH/tags/$PLUGINVERSION
svn copy --quiet trunk/ tags/$PLUGINVERSION/
# Remove assets and trunk directories from tag directory
svn delete --force --quiet $SVNPATH/tags/$PLUGINVERSION/assets
svn delete --force --quiet $SVNPATH/tags/$PLUGINVERSION/trunk
cd $SVNPATH/tags/$PLUGINVERSION
svn commit --username=$SVNUSER -m "Tagging version $PLUGINVERSION"

echo "Removing temporary directory $SVNPATH"
cd $SVNPATH
cd ..
rm -fr $SVNPATH/

echo "*** FIN ***"