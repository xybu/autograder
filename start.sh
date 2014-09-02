#!/bin/bash

# Usage
# sudo ./start.sh

if [ "$USER" != "root" ] ; then
	echo "This script needs to run under root permission."
	exit 1
fi

cd grader
./daemon.py < /dev/null > ../log/ag_daemon.out.log 2>&1 &
