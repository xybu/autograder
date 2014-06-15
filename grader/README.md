Autograder/Grader
=================

Introduction
============

This directory saves the `task scheduler` and `grader core` parts of the Autograder project, where
 * `task scheduler` refers to the daemon program, which is a TCP socket server that accepts
   grading requests from the web client. It has an internal, thread-safe priority queue to help 
   schedule the tasks sent to it.
 * `grader core` is an abstract class that helps define a concrete grading script.

Logistics
=========
The essence of Autograder is to *run a command and process its output*.


Notes
=====

Graders should be multi-threaded to accept tasks while performing grading.

it is up to the grader how the grade of a submission is determined.
