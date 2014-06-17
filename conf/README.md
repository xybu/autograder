AutoGrader/conf
===============

This is the default path to store configuration files.

`assignments.json`, `daemons.json`, `roles.json`, and `users.json`, 
are used for the *web* component only, and must be put in the same directory
defined by `DATA_PATH` variable in `/web/app/config/globals.ini` file.

There is no need to move `grader.json`.

assignments.json
================

The list of assignments. Assignment information will be fetched by *web* component, and be sent to grader along with other information (i.e., *grader* component will not touch it).

daemons.json
============

The list of grader daemons used by *web* component. When a students submits a file to the web, *web* picks a proper server from the list
and sends the grading request to it.

grader.json
===========

The configuration file for the *grader* component.

roles.json
==========

The user roles for the *web* component.

users.json
==========

The user list for the *web* component.

The format for this file is 

```javascript
{
  "role1": {
    "user1": "password1",
    "user2": "password2",
  }, 
  "role2": {
    "user3": "password3",
  }
}

```

where `role` and `role2` are the roles defined in `roles.json`, and inside them are username-password pairs.
