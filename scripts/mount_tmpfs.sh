#!/bin/bash

#mkdir -p /mnt/ram
#mount -t ramfs -o size=20m ramfs /mnt/ram

mkdir -p /mnt/tmp
mount -t tmpfs -o size=20m tmpfs /mnt/tmp

df -m

umount /mnt/tmp

df -m

