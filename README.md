AutoGrader
==========

Table of Contents
=================

 - [Introduction](#introduction)
 - [Advantages](#advantages)
 - [File Structure](#file-structure)
 - [Setting up](#set-up)
 	 - [Install](#install)
 	 - [Configure](#configure)
 - [Usage](#usage)
 - [Supplements](#supplements)
 	 - [Reference Environment](#reference-environment)
 - [Support](#support)

Introduction
============

**AutoGrader** is a project that aims to 

 * have students turn-in code online, 
 * raise and queue the grading tasks, and
 * grade them automatically given a set of instructor-defined test cases.

The three functions are designed as three separate modules which are connected by APIs. 
Each module can be used separately.

Advantages
==========

Compared some other auto-grading systems like Web-CAT (http://web-cat.org/), this AutoGrader has several notable advantages:

 * light-weight components and less overheads
 * virtually no influence on student code (the system does not require `#include`ing or `import`ing special libraries),
 * easier to access OS kernel and control system calls when needed,
 * sandbox for controller file I/O and networking (whether to enable sandbox or not is defined by each test case),
 * flexible and extensible APIs to design test cases, 
 * what to write in the grading feedback is totally up to the instructor,
 * distributed grader hosts...

And thus it is better used for grading C/C++/assembly assignments, especially those involving systems programming.

File Structure
==============

The file hierarchy of the project is as below:

 * **conf** stores all the configuration files for the project.
 * **database** hosts the SQL dump files.
 * **doc** has more documents for the project.
 * **grader** the grader daemon and worker parts and the superclass of grader test cases.
 * **log** is the default directory to store log files. Excluded from Git and will be created by **inst.sh**.
 * **submissions** the default directory to save submissions that are sent to the web. Excluded from Git and will be created by **inst.sh**.
 * **utils** has some handy utility programs that will ease instructors' life.
 * **web** stores the web application. The root dir of the web server should be pointed here.
 * **inst.sh** is the installation script.

Most directories have a specific **README.md** that gives more details.

Set-up
======

## Install

Instructions TBA.

## Configure

Instructions TBA.

Usage
=====

For the definitions about the data and API, and the tutorial for writing test suites, 
refer to `/doc/README.md`.

Supplements
===========

## Reference Environment

To ensure fair and transparent grading of C/C++ assignments, it is better to define the 
reference environment the same as the one that hosts the grader and provide students the 
reference environment (without installing AutoGrader), probably in the form of a VM.

Support
=======

For technical support, contact Xiangyu Bu (https://github.com/xybu92).
