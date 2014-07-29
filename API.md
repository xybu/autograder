AutoGrader/doc
==============

This directory saves the documents regarding how terms are 
defined and how to use the system.

Table of Contents
=================

 - [Components](#components)
 	 - [Web Application](#web-application)
 	 - [Grader Daemon](#grader-daemon)
 	 - [Test Runner](#test-runner)
 - [Definitions](#definitions)
 	 - [Users and Roles](#users-and-roles)

Components
==========

## Web Application

Web application provides interfaces for displaying the list 
of assignments and submitting assignments. When a file is submitted,
it talks to one grader daemon from the list to assign the grading work,
and waits for the feedback (i.e., *callback*) from the grader.

## Grader Daemon

The daemon is a multi-threaded program that listens to a TCP socket for 
incoming tasks, and when receiving a grading request, puts it to the queue.

There is a sub-component named `worker` that fetches items from the queue, goes 
over the grading steps, and sends the result back to *web*.

## Test Runner

Each assignment will have its own test runner, which is written as a subclass of 
the `grader.GraderTestCase` (in /grader/grader.py) class.

Remember that a test case consists of three items: **identifier**, **input**, and 
**expected outcome**, corresponding to the function name, command to run and data
to feed to its stdin, and the output hander, respectively.

Definitions
===========

## Users and Roles

