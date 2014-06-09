#!/usr/bin/python

import unittest

class GraderTestSuite:
	def __init__(self):
		pass	

class HandlerFactory:
	pass

class GraderResult(unittest.TextTestResult):
"""A Python UnitTest test runner implementation"""

	def __init__(self, stream=sys.stderr, descriptions=1, verbosity=1):
		unittest.TextTestResult.__init__(self, stream, descriptions, verbosity)
	
	def startTest(self, test):
		"""The function is called every time when the test case test is about to run."""
		pass
	
	def stopTest(self, test):
		"""The function is called every time when the test case is done."""
		pass
	
class GraderRunner:
	pass