#!/usr/bin/python

"""The daemon host and port"""
DAEMON_ADDRESS = ('127.0.0.1', 8080)

"""Temporary path"""
TEMP_PATH = "/tmp/autograder/"
TEMP_FILE_NAME = "submission.archive"
GRADEBOOK_FILE_NAME = "grades.json"

"""The trusted API keys and their hosts"""
VALID_SOURCES = {
	'aaaaaa': ['127.0.0.1']
}

"""MySQL database connector params"""
MYSQL_PARAMS = {
	'host': '127.0.0.1',
	'user': 'root',
	'password': '123456',
	'port': 3306,
	'database': 'ag_daemon'
}

"""The number of grader workers that can run simultaneously."""
MAX_WORKER_NUM = 2

"""The exception codes and their descriptions"""
EXCEPTION_LIST = {
	'unauthenticated_request':	"The request is not authenticated.",
	'invalid_json_request':		"The data sent cannot be JSON decoded.",
	'invalid_request':		"The request is not of valid format.",
	'unknown_exception':		"An unknown exception has occurred.",
	'unimplemented_function':	"The request involves unimplemented function.",
}

import os
import gc
import json
import errno
import shutil
import tarfile
import datetime
import threading
import subprocess
import SocketServer
import mysql.connector

IncomingTaskEvent = threading.Event()
ConnectionLock = threading.RLock()

def finishGradingWithPath_callBack(grade_result):
	print json.dumps(grade_result, indent = 4)

def handleTaskWithPath(submission_row):
	# cwd = os.getcwd()
	task_id, task_submission_id, task_user_id, task_priority, task_file_path, task_api_key, task_assignment, task_date_created = submission_row
	task_assignment = json.loads(task_assignment)
	
	submission_temp_path = TEMP_PATH + str(task_id) + "/"
	try:
		# mkdir -p
		os.makedirs(submission_temp_path, 0777)
		# os.chdir(submission_temp_path)
	except OSError as ex:
		if ex.errno == errno.EEXIST and os.path.isdir(submission_temp_path):
			pass
		else:
			raise
	
	shutil.copyfile(task_file_path, submission_temp_path + TEMP_FILE_NAME)
	
	if 'grader_tar' in task_assignment and task_assignment['grader_tar'] != '':
		try:
			t = tarfile.open(task_assignment['grader_tar'])
			t.extractall(path = submission_temp_path)
		except:
			# TODO: what if the file fails to be extracted?
			print "Failed to extract the grader tar file."
			pass
	
	# if it is a tar, try to decompress it
	if 'submit_filetype' in task_assignment and ('gz' in task_assignment['submit_filetype'] or 'tar' in task_assignment['submit_filetype'] or 'zip' in task_assignment['submit_filetype']):
		# WARNING: the tar ball may contain files with name having '/' or '..'
		try:
			t = tarfile.open(submission_temp_path + TEMP_FILE_NAME)
			t.extractall(path = submission_temp_path)
		except:
			# TODO: what if the file fails to be extracted?
			print "Failed to extract the submission tar file."
			pass
	
	# execute the grading script
	if 'grader_script' not in task_assignment:
		print "Grading cannot proceed. Grader script not specified."
	else:
		try:
			subp = subprocess.Popen([task_assignment['grader_script']], stdout = subprocess.PIPE, stderr = subprocess.PIPE, cwd = submission_temp_path)
			result = subp.communicate()
			print result
		except OSError as e:
			print "Grading script is not executable."
	
	# assemble the grading result and send it to callback function
	if os.path.isfile(submission_temp_path + GRADEBOOK_FILE_NAME):
		grade_detail = "{total: 0}"
		with open(submission_temp_path + GRADEBOOK_FILE_NAME, "r") as f:
			grade_detail = f.read()
		with tarfile.open(submission_temp_path + "../dump_" + str(task_id) + ".tar.gz", "w:gz") as t:
			t.add(submission_temp_path, arcname = "dump")
		cb_data = {
			'grade': grade_detail,
			'protocol_type': 'path',
			'dump_file': os.path.normpath(submission_temp_path + "../dump_" + str(task_id) + ".tar.gz"),
			'internal_log': result[0],
			'formal_log': result[1]
		}
		shutil.rmtree(submission_temp_path)
		finishGradingWithPath_callBack(cb_data)
	else:
		print "Grade book file \"" + submission_temp_path + GRADEBOOK_FILE_NAME + "\" was not generated."
		
	
class GraderWorker(threading.Thread):
	
	_conn = None	
	
	def __init__(self):
		threading.Thread.__init__(self)
		self.daemon = True
	
	def run(self):
		while True:
			ConnectionLock.acquire()
			IncomingTaskEvent.clear()
			self._conn = mysql.connector.connect(**MYSQL_PARAMS)
			print "Worker is fetching new tasks."
			cursor = self._conn.cursor()
			query = ("SELECT id, submission_id, user_id, priority, file_path, api_key, assignment, date_created FROM queue ORDER BY priority DESC, date_created ASC LIMIT 1")
			cursor.execute(query)
			row = cursor.fetchone()
			cursor.close()
			if row != None:
				cursor = self._conn.cursor()
				query = ("DELETE FROM queue WHERE id = %s")
				cursor.execute(query, [row[0]])
				self._conn.commit()
				cursor.close()
				self._conn.close()
				ConnectionLock.release()
				handleTaskWithPath(row)
				gc.collect()
			else:
				print "There is no new task in the queue. Sleep."
				self._conn.close()
				ConnectionLock.release()
				IncomingTaskEvent.wait()

class DaemonTCPHandler(SocketServer.StreamRequestHandler):
	
	_conn = None
	
	def get_error(self, err_id):
		return {
			'error': err_id,
			'error_description': EXCEPTION_LIST[err_id]
		}
	
	def send_json_obj(self, response):
		self.wfile.write(json.dumps(response, indent = 4))
	
	def handle(self):
		cli_addr = self.client_address[0]
		# verify data source
		
		data = self.rfile.readline().strip()
		print "{} wrote:".format(cli_addr)
		print data
        	
		try:
			self.json_data = json.loads(data)
			if ('api_key' not in self.json_data or self.json_data['api_key'] not in VALID_SOURCES or cli_addr not in VALID_SOURCES[self.json_data['api_key']]):
				self.send_json_obj(self.get_error('unauthenticated_request'))
				return
		except ValueError:
			self.send_json_obj(self.get_error('invalid_json_request'))
			return
        	
        	if 'protocol_type' not in self.json_data:
			self.send_json_obj(self.get_error('invalid_request'))
			return
		elif self.json_data['protocol_type'] == 'path':
			# add task to database
			# assume the json_data has valid data structure
			add_task = ("INSERT INTO queue (submission_id, user_id, priority, file_path, api_key, assignment, date_created)"
					"VALUES (%(submission_id)s, %(user_id)s, %(priority)s, %(file_path)s, %(api_key)s, %(assignment)s, NOW())")
			data_task = {
				'submission_id': self.json_data['submission_id'],
				'user_id': self.json_data['user_id'],
				'priority': self.json_data['priority'],
				'assignment': json.dumps(self.json_data['assignment']),
				'api_key': self.json_data['api_key'],
				'file_path': self.json_data['src_file'],
			}
			cursor = DaemonTCPHandler._conn.cursor()
			cursor.execute(add_task, data_task)
			queued_id = cursor.lastrowid
			DaemonTCPHandler._conn.commit()
			cursor.close()
			
			IncomingTaskEvent.set()
			
			response = {
				'status': 'queued',
				'queued_id': queued_id
			}
			
			self.wfile.write(json.dumps(response) + "\r\n")
			
		elif self.json_data['protocol_type'] == 'base64':
			self.send_json_obj(self.get_error('unimplemented_function'))
			return
		else:
			# unknown prococol type
			self.send_json_obj(self.get_error('invalid_request'))
			return
        	
		# self.wfile.write("done.")

class DaemonTCPServer(SocketServer.ThreadingMixIn, SocketServer.TCPServer):
	pass

if __name__ == "__main__":
	
	try: 
		DaemonTCPHandler._conn = mysql.connector.connect(**MYSQL_PARAMS)
	except mysql.connector.Error as err:
		if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
			print "Daemon database access denied."
		elif err.errno == errorcode.ER_BAD_DB_ERROR:
			print "Daemon database \"" + MYSQL_PARAMS["database"] + "\" does not exist."
		else:
			print "Some error occurred connecting to the database."
		import sys
		sys.exit(1)
	
	for x in range(MAX_WORKER_NUM):
		GraderWorker().start()
	
	SocketServer.TCPServer.allow_reuse_address = True
	server = DaemonTCPServer(DAEMON_ADDRESS, DaemonTCPHandler)
	server.serve_forever()