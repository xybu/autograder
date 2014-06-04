Autograder-Grader
=================

Introduction
============

The essence of Autograder is to *run a command and process its output*.



Notes
=====

Graders should be multi-threaded to accept tasks while performing grading.

Each passive grader must have a built-in task queue.

it is up to the grader how the grade of a submission is determined.
