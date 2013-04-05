#!/bin/bash
if [ $# -lt 1 ]
then
  echo "Usage: $0 version"
  exit 1
fi

mkdir doofinder
cp *.php doofinder
cp logo.* doofinder
cp *.tpl doofinder
cp -r css doofinder
cp README.md doofinder
zip -r doofinder-p1.4-$1.zip doofinder
rm -Rf doofinder