#!/bin/bash

#deprecated Version

rm -f   .travis.yml
rm -f   *.sh

zip -r woo-oyst.zip *
tar --exclude='woo-oyst.zip' -cvzf woo-oyst.tar.gz *