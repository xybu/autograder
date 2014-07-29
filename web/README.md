Autograder-Web
==============

The web interface of autograder project.

Copy `app/config/globals.ini.def` to `app/config/globals.ini` and update its credentials.

File Permissions
================
 * *tmp*: RW
	 * *cache*: RW
	 * *upload*: RW

Setting up the System
=====================

While there is an admin panel for handling configurations, this 
section introduces how to manually set up the system.

## Manage Roles

Each user is assigned a specific role, which specifies the scope of permitted operations.

The role table is stored in `data/roles.json` file.

## Manage Users

The user table is stored in `data/users.json` file, whose format is 

```javascript
{
	"role":
}
```

## Manage Assignments

The assignment table is stored in `data/assignments.json` file.

## Manage Graders

The grader table is in `data/graders.json` file.

The general format is

```javascript
{
	"IDENTIFIER": {
		"ip": "IP_ADDRESS",
		"port": "PORT_NUMBER",
		"type": "passive",
		"protocol": "PROTOCOL",
		"accept_roles": "ROLE1, ROLE2, ...",
		"private_key": "PRIVATE_KEY"
	}
}
```

where

 * `IDENTIFIER`:
 * `IP_ADDRESS`:
 * `PORT_NUMBER`:
 * `PROTOCOL`: either `file` or `path`
 * `ROLE1, ROLE2, ...`: 
 * `PRIVATE_KEY`: 

Web API
=======

## User Authentication

## Assignment Submission

