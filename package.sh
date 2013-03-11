#!/bin/bash
mkdir doofinder
cp *.php doofinder
cp logo.* doofinder
cp *.tpl doofinder
cp -r css doofinder
cp README.md doofinder
zip -r doofinder.zip doofinder
rm -Rf doofinder