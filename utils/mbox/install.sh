#!/bin/bash

sudo cp ./setulimits /usr/bin/setulimits
git clone https://github.com/tsgates/mbox.git
cd mbox
cd src
cp {.,}configsbox.h
./configure
make
sudo make install
cd ../..
rm -rfv mbox
