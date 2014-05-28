Autograder-Web
==============

The web interface of autograder project.

File Permissions
================
 * *tmp*: RW
	 * *cache*: RW
	 * *upload*: RW

Setting up the System
================

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

Web API
=======

## User Authentication

## Assignment Submission

