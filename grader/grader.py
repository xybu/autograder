#!/usr/bin/python

import os, sys, time, json
import unittest
import subprocess

DEFAULT_MAX_RAM = "24"	# in MiB
DEFAULT_TIMEOUT = 20
GRADEBOOK_FILE_NAME = "grades.json"

container_name = None

def SandboxedArgs(cmd, max_ram = DEFAULT_MAX_RAM, timeout = DEFAULT_TIMEOUT, sb_args = None):
	"""
	Modify the command-line args so that it will run inside the sandbox.
	
	@param max_ram: the maximum amount of memory that the process can use. E.g., '24m'
	@param timeout: the sandboxed process will be killed if it does not exit after the specified number of seconds.
	@param sb_args: the additional args to append to the sandbox command.
	"""
	
	global container_name
	argv = ['mbox', '-i', '-p', '/home/public/settings/mbox.conf', '-n']
	if container_name != None:
		argv = ['cgexec', '-g', container_name, '--sticky'] + argv
	
	if sb_args != None: argv = argv + sb_args
	if timeout != None: argv = ['timeout', '--signal=9', str(timeout)] + argv
	if max_ram != None: cmd = ['-m', str(max_ram)] + cmd
	
	return argv + ['/usr/bin/setulimits'] + cmd

def WriteFormalLog(s):
	"""
	Write the string s to formal log (held by stderr).
	Formal log is the log that will be parsed to student-viewable grade report.
	WARNING: one should leave formal log as it is; do not write to it.
	"""
	sys.stderr.write(s + "\n")

def WriteInternalLog(s):
	"""
	Write the string s to the internal log (held by stdout).
	Internal log is the log that only admin can read (and understand).
	"""
	sys.stdout.write(str(s) + "\n")

def execute(cmd_args, input_data = None):
	"""
	An execvp-like function to execute a command.
	Returns a 3-tuple of return value, stdout data, stderr data, of the command.
	"""
	try:
		WriteInternalLog(cmd_args)
		subp = subprocess.Popen(cmd_args, 0, None, stdin = subprocess.PIPE, stdout = subprocess.PIPE, stderr = subprocess.PIPE)
		oe = subp.communicate(input_data)
		return (subp.wait(), oe[0], oe[1])
	except (OSError, IOError) as e:
		assert False, "System Error: {1} ({0}).".format(e.errno, e.strerror)

class Grade:
	
	def __init__(self, max_score = 100):
		self.max_score = max_score
		self.current_score = 0
	
	def set_max_score(self, m):
		self.max_score = m
	
	def plus(self, p):
		self.current_score += p
	
	def times(self, t):
		self.current_score *= t
	
	def zero(self):
		self.current_score = 0

grade = Grade()

class GraderTestCase(unittest.TestCase):
	"""
	The test suite will be run under current working directory.
	This class should be seen as abstract; each assignment will need its own grading script.
	"""
	
	def make(self, makefile_name = "", sandboxed = True, file_target = [], handler = lambda r,o,e,t:r, max_ram = None, timeout = None, sb_args = None):
		"""
		A shortcut function for executing Makefile to make target `all`.
		
		@param	makefile_name (optional): Use the default Makefile name (aka. "Makefile") if not set; otherwise execute the specific Makefile.
		@param	sandboxed (optional): If set True, `make` will run inside the sandbox.
		@param	max_ram: the RAM limit for the sandbox; only effective when sandboxed is set True.
		@param	timeout: the timeout limit for the sandbox; only effective when sandbox is set True.
		@param	sb_args: the additional args for the sandbox.
		@param	file_target (optional): A list of files that will be removed before running `make`, and whose existence will be checked after running `make`. All relative paths.
		@param	handler (required): It determines how to deal with the result of running `make` command.
		"""
		
		# remove all file targets
		if len(file_target) > 0:
			for name in file_target:
				try:
					os.remove("./" + name)
				except:
					pass
		
		# prepare command-line args for `make`
		make_args = ["make"]
		if makefile_name != "":
			make_args += ["-f", makefile_name]
		
		# execute `make` command with arguments
		if sandboxed:
			make_args = SandboxedArgs(make_args, max_ram = max_ram, timeout = timeout, sb_args = sb_args)
		
		roe = execute(make_args)
		
		# pass the result to handler
		handler(roe[0], roe[1], roe[2], file_target)
	
	def clean(self, makefile_name = "", sandboxed = True, max_ram = None, timeout = None, sb_args = None):
		"""
		A shortcut function for executing `make clean`.
		
		@param	makefile_name (optional): Use the default Makefile name (aka. "Makefile") if not set; otherwise execute the specific Makefile.
		@param	sandboxed (optional): If set True, `make clean` will run inside the sandbox.
		@param	max_ram: the RAM limit for the sandbox; only effective when sandboxed is set True.
		@param	timeout: the timeout limit for the sandbox; only effective when sandbox is set True.
		@param	sb_args: the additional args for the sandbox.
		"""
		
		make_args = ["make", "clean"]
		if makefile_name != "":
			make_args += ["-f", makefile_name]
		
		# execute `make` command with arguments
		if sandboxed:
			make_args = SandboxedArgs(make_args, max_ram = max_ram, timeout = timeout, sb_args = sb_args)
		
		roe = execute(make_args)
	
	def execvp(self, cmd = [], sandboxed = True, stdin = None, handler = lambda r,o,e,a:r, handler_args = None, max_ram = DEFAULT_MAX_RAM, timeout = DEFAULT_TIMEOUT, sb_args = None):
		"""
		An execvp-like function to execute commands.
		
		@param cmd (required): a LIST of arguments. e.g., ['ls', '-asl']
		@param sandboxed: if set True, cmd will run inside the sandbox
		@param stdin: the data to feed to cmd process's stdin
		@param handler (required): the handler to process the output of cmd
		@param handler_args: the additional args for the handler function
		@param max_ram: the RAM limit for the sandbox; only effective when sandboxed is set True
		@param timeout: the timeout limit for the sandbox; only effective when sandbox is set True
		@param sb_args: the additional args for the sandbox
		"""
		if type(cmd) != list:
			assert False, "Configuration Error: cmd argument must be a list."
		elif len(cmd) == 0:
			assert False, "Configuration Error: cmd argument is empty."
		elif cmd[0][:2] == "./":
			assert os.path.exists(cmd[0]), "Executable \"{0}\" not found.".format(cmd[0])
		
		if sandboxed:
			cmd = SandboxedArgs(cmd, max_ram = max_ram, timeout = timeout, sb_args = sb_args)
		
		roe = execute(cmd, stdin)
		handler(roe[0], roe[1], roe[2], handler_args)
	
	# API abort_test(self) was abandoned for its uselessness.
	# To control the flow use an internal boolean variable as flags, and 
	# check if the flag is set before executing commands.
	
class UtilFactory:
	'''
	Some handy utility functions for standardized test
	'''
	
	@staticmethod
	def has_segfault(str):
		'''
		@param str: the string to test whether to contain segfault keywords or not.
		'''
		str = str.lower()
		return 'segmentation fault' in str or ('core' in str and 'dumped' in str)
	
	@staticmethod
	def prepend_empty_lines(str):
		'''
		prepend some beautiful chars to lines starting with whitespaces
		so that the log formatter will not ignore them.
		
		@param str: the string to be beautified.
		'''
		ne = ''
		s = str.split("\n")
		for line in s:
			if line.startswith(' '): line = '|-' + line
			if line != '': ne = ne + line + "\n"
		return ne

class HandlerFactory:
	@staticmethod
	def DefaultMakeHandler(r, o, e, t = []):
		"""
		The default `make` handler.
		
		@param r: return value
		@param o: the stdout of `make` process
		@param e: the stderr of `make` process
		@param t: the file targets to check for existence.
		"""
		WriteFormalLog(o)	# print make commands to stdout
		
		e = UtilFactory.prepend_empty_lines(e)
		
		if t != []:
			f_t = []
			for name in t:
				if not os.path.exists("./" + name): f_t.append(name)
			assert f_t == [], "Makefile did not build the following files: " + ", ".join(f_t) + "\n" + e
		
		if r != 0:		# if return value of `make` is not 0
			assert False, "Target `all` was not built successfully.\nstderr data: \n" + e
	
	@staticmethod
	def SimpleMakeHandler(r, o, e, t = []):
		"""
		This `make` handler does not reveal stderr data to formal log.
		
		@param r: return value
		@param o: the stdout of `make` process
		@param e: the stderr of `make` process
		@param t: the file targets to check for existence.
		"""
		WriteFormalLog(o)	# print make commands to stdout
		
		if t != []:
			f_t = []
			for name in t:
				if not os.path.exists("./" + name): f_t.append(name)
			assert f_t == [], "Makefile did not build the following files: " + ", ".join(f_t)
		
		if r != 0:		# if return value of `make` is not 0
			assert False, "Target `all` was not built successfully."

class GraderResult(unittest.TextTestResult):
	"""
	A Python UnitTest test runner implementation.
	There is an internal grade tracker to generate gradebook by TEST CASES.
	
	http://code.nabla.net/doc/unittest/api/unittest/unittest.TextTestResult.html
	"""
	
	def __init__(self, stream=sys.stderr, descriptions=1, verbosity=1):
		unittest.TextTestResult.__init__(self, FileWrapper(stream), descriptions, verbosity)
		self.prev_score = 0
		self.grade_history = {}
	
	def startTest(self, test):
		"""The function is called every time when the test case test is about to run."""
		unittest.TextTestResult.startTest(self, test)
		self.prev_score = self._grade.current_score
	
	def stopTest(self, test):
		"""The function is called every time when the test case is done."""
		unittest.TextTestResult.stopTest(self, test)
		self.grade_history[test.id().split('.')[-1]] = self._grade.current_score - self.prev_score
	
	def getGradebook(self):
		self.grade_history['max'] = self._grade.max_score
		self.grade_history['total'] = self._grade.current_score
		return self.grade_history

class FileWrapper(object):
	"""
	Wrap a file object so that GraderResult can write to it.
	"""
	
	def __init__(self, stream):
		self.stream = stream
	
	def __getattr__(self, attr):
		if attr in ('stream', '__getstate__'):
			raise AttributeError(attr)
		return getattr(self.stream,attr)
	
	def writeln(self, data = None):
		if data: self.write(data)
		self.write('\n')

def main(testSuiteClass):
	"""
	The main function to assemble unittest parts and execute the test.
	
	@param	testSuiteClass: the specific test case class, whose test_* functions will form a test suite.
	"""
	global container_name
	if len(sys.argv) == 2:
		container_name = sys.argv[1]
	
	suite = unittest.makeSuite(testSuiteClass, "test_")
	runner = GraderResult()
	global grade
	runner._grade = grade
	
	start_time = time.time()
	suite.run(runner)	
	elapsed_time = time.time() - start_time # in seconds
	gradebook = runner.getGradebook()
	
	# print the header
	WriteFormalLog('')
	WriteFormalLog('Ran {0:d} test(s) in {1:.2f} second(s)'.format(len(gradebook) - 2, elapsed_time))
	WriteFormalLog('Grade: {0:d} / {1:d}'.format(gradebook['total'], gradebook['max']))
	
	# print all errors
	runner.printErrors()
	
	# generate gradebook
	with open(GRADEBOOK_FILE_NAME, "w") as f:
		f.write(json.dumps(runner.getGradebook()))
	