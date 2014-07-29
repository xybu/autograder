#!/usr/bin/python

import grader

class ROETest(grader.GraderTestCase):
	
	grader.gradeObj.set_max_score(30)
	
	def test_000(self):
		self.make(sandboxed = False, file_target = ['roe'], handler=grader.HandlerFactory.DefaultMakeHandler)
		grader.gradeObj.plus(10)
	
	def test_001(self):
		def handler(r, o, e, a):
			if o.strip() != "stdout":
				assert False, "Your program should print \"stdout\" to stdout."
			grader.gradeObj.plus(10)
			
			if e.strip() != "stderr":
				assert False, "Your program should print \"stderr\" to stderr."
			grader.gradeObj.plus(10)
		self.execvp(cmd = ['./roe'], handler = handler)
	
	def test_002(self):
		self.clean()
	
grader.main(ROETest)
