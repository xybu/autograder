AutoGrader
============

**AutoGrader** is a project that aims to 

 * accept submissions from student code online, and 
 * raise and queue the grading tasks
 * grade them automatically given a set of instructor-defined test cases.

The three functions are designed as three separate modules which are connected by APIs. 
Each module can be used separately.

Advantages
==========C/C++ assignments, especially those involving systems programming.


Compared to Web-CAT (http://web-cat.org/), this AutoGrader has several notable advantages:

 * virtually no influence on student code
 * easier to access OS kernel and control system calls when needed
 * more flexible APIs to design test cases
 * distributed grader hosts

And thus it is better used for grading 
File Structures
===============

The file hierarchy of the project is as below:

 * **conf** stores all the configuration files for the project
 * **database** the SQL dump files that will be used to import to mysqld in the future
 * **grader** the grader daemon and worker parts and the superclass of grader test cases.
 * **log** is the default directory to store log files. Excluded from Git and will be created by **inst.sh**.
 * **submissions** the default directory to save submissions that are sent to the web. Excluded from Git and will be created by **inst.sh**.
 * **utils** has some handy utility programs that will ease instructors' life.
 * **web** stores the web application. The root dir of the web server should be pointed here.
 * **inst.sh** is the installation script.

Most directories have a specific **README.md** that gives more details.

Support
=======

For technical support, contact Xiangyu Bu (https://github.com/xybu92).
