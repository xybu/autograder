#!/bin/bash

cd grader
sudo ./daemon.py < /dev/null > ../log/ag_daemon.out.log 2>&1 &
