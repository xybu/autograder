#!/bin/bash

cd grader
./daemon.py < /dev/null > ../log/ag_daemon.out.log 2>&1 &
