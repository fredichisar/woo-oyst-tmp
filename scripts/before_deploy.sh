#!/bin/bash

#deprecated Version

mkdir travis_release

cp -r * travis_release

rm -f   travis_release/.travis.yml
rm -rf  travis_release/bin
rm -rf  travis_release/travis_release
rm -f  travis_release/composer.*

cd travis_release

zip -r woo-oyst.zip *
tar --exclude='woo-oyst.zip' -cvzf woo-oyst.tar.gz *