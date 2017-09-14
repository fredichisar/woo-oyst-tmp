#!/bin/bash

#deprecated Version

cd src

zip -r woo-oyst.zip *
tar --exclude='woo-oyst.zip' -cvzf woo-oyst.tar.gz *