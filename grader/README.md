Autograder/Grader
=================

Rename `grader.json.def` to `grader.json` and update its credentials.

Introduction
============

This directory saves the `task scheduler` and `grader core` parts of the Autograder project, where
 * `task scheduler` refers to the daemon program, which is a TCP socket server that accepts
   grading requests from the web client. It has an internal, thread-safe priority queue to help 
   schedule the tasks sent to it.
 * `grader core` is an abstract class that helps define a concrete grading script.

Logistics
=========

Always remember that the grader is nothing but an output parser (which transforms the `(return code, stdout, stderr)` tuple of executing a command to a grade and an explanation).
What makes this mechanism extremely flexible is the wide variety of commands available. You can even write bash script or simply anything
executable to use as the middleware for grading.

Logging Mechanism
=================

There are several logging objects that are used for specific purposes. Do not mess them up:

 * in `daemon.py` process
 	 * `daemon log` refers to the log object whose content is about the daemon process.
 	 * `handler log` explains how the grading request is handled. This log will be sent back to the web server.
 * in `core.py` and its subclasses
 	 * `formal log` will be used to parse the grading report and thus should not be arbitrarily written to AT ALL.
 	 * `internal log` helps the instructor to record some data for debugging purpose.
 	 * both `formal log` and `internal log` will be sent back to the web server.

Notes
=====

Graders should be multi-threaded to accept tasks while performing grading.

it is up to the grader how the grade of a submission is determined.
