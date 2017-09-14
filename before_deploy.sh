#!/bin/bash

#deprecated Version
mkdir travis-deploy
cd travis-deploy
cp -r ../* ./

rm -f   .travis.yml
rm -f   *.sh
rm -rf   travis-deploy

zip -r ../woo-oyst.zip *
tar -cvzf ../woo-oyst.tar.gz *

cd ..
rm -rf travis-deploy