#!/bin/bash

# Import the MySQL database dumps to local mysqld
# Xiangyu Bu <xybu92@live.com>

# CHANGE THE VALUES TO YOUR OWN ONES
MYSQL_ROOT_USERNAME=root
MYSQL_ROOT_PASSWORD=123456

# Create a random string for seeds and passwords
function get_random_str() {
	echo `cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w ${1:-$1} | head -n 1`
}

# replace_seeds file_name db_name
function cp_replace_seeds() {
	username_seed=`get_random_str 5`
	password_seed=`get_random_str 10`
	
	echo "Related Database: $2" >> credentials.txt
	echo "	Username: $2_$username_seed" >> credentials.txt
	echo "	Password: $password_seed" >> credentials.txt
	echo "" >> credentials.txt
	
	sed "s/_USERNAME_SEED_/$username_seed/g" $1 > $1.new
	sed "s/_PASSWORD_SEED_/$password_seed/g" $1.new > $1.sql
	
	rm $1.new
}

touch credentials.txt

cp ag_web.def ag_web.sql
cp ag_daemon.def ag_daemon.sql
cp_replace_seeds useradd_agweb.def ag_web
cp_replace_seeds useradd_agdaemon.def ag_daemon

for dumpfile in *.sql
do
	mysql --user=$MYSQL_ROOT_USERNAME --password=$MYSQL_ROOT_PASSWORD -h 127.0.0.1 < $dumpfile
	if [ $? -eq 0 ] ; then
		rm $dumpfile
	fi
done

echo "Please update the database credentials of grader component and web component:"
echo "	grader/grader.json"
echo "	web/app/config/globals.ini"
