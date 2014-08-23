#!/bin/bash

git clone https://github.com/tsgates/mbox.git
cd mbox
cd src
cp {.,}configsbox.h
./configure
make
sudo make install
sudo cp ./setulimits /usr/bin/setulimits
