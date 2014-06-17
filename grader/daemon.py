#!/usr/bin/python

"""

daemon.py

The daemon and worker layers for the Autograder project.
 * The daemon listens to a TCP socket and queues incoming requests.
 * The workers monitor the queue and process those requests by executing the grading script.
 * The queue is built on mysql database.

The settings for daemon are stored at ../conf/grader.json

@author	Xiangyu Bu <xybu92@live.com>

"""

"""Temporary path"""
TEMP_PATH = "/tmp/autograder/"
TEMP_FILE_NAME = "submission.archive"
GRADEBOOK_FILE_NAME = "grades.json"

"""The exception codes and their descriptions"""
EXCEPTION_LIST = {
	'unauthenticated_request':	"The request is not authenticated.",
	'invalid_json_request':		"The data sent cannot be JSON decoded.",
	'invalid_request':		"The request is not of valid format.",
	'unknown_exception':		"An unknown exception has occurred.",
	'unimplemented_function':	"The request involves unimplemented function.",
}

import os, sys, gc, json, time
import errno
import shutil
import logger
import tarfile
import datetime
import threading
import subprocess
import SocketServer
import mysql.connector

with open('../conf/grader.json', 'r') as s_f:
	Settings = json.load(s_f)

# this event is used to wake up sleeping workers
IncomingTaskEvent = threading.Event()

# just in case more than one worker gets the same task
ConnectionLock = threading.RLock()

# used to hold the daemon log
Logger = logger.Logger()

def getSenderInfo(key, cli_addr):
	lst = Settings['TRUSTED_KEY_LIST']
	if key in lst and cli_addr in lst[key]:
		return lst[key][cli_addr]
	return None

def finishGrading_callBack(grade_result):
	print json.dumps(grade_result, indent = 4)

def handleTask(submission_row):
	# cwd = os.getcwd()
	task_id, task_submission_id, task_user_id, task_priority, task_file_path, task_api_key, task_assignment, task_date_created, task_response_type = submission_row
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
		finishGrading_callBack(cb_data)
	else:
		print "Grade book file \"" + submission_temp_path + GRADEBOOK_FILE_NAME + "\" was not generated."
		
	
class GraderWorker(threading.Thread):
	
	def __init__(self):
		threading.Thread.__init__(self)
		self.daemon = True
	
	def run(self):
		while True:
			ConnectionLock.acquire()
			IncomingTaskEvent.clear()
			self._conn = mysql.connector.connect(**Settings['DATABASE_CONN_PARAM'])
			Logger.info('Querying new tasks.')
			cursor = self._conn.cursor()
			query = ("SELECT id, submission_id, user_id, priority, file_path, api_key, assignment, date_created, response_type FROM queue ORDER BY priority DESC, date_created ASC LIMIT 1")
			cursor.execute(query)
			row = cursor.fetchone()
			cursor.close()
			if row != None:
				cursor = self._conn.cursor()
				query = ("DELETE FROM queue WHERE id = %s")
				cursor.execute(query, [row[0]])
				time.sleep(3)	# TODO: remove this
				self._conn.commit()
				cursor.close()
				self._conn.close()
				ConnectionLock.release()
				handleTask(row)
				gc.collect()
			else:
				Logger.info('Queue is empty. Put thread to sleep.')
				self._conn.close()
				ConnectionLock.release()
				IncomingTaskEvent.wait()

class DaemonTCPHandler(SocketServer.StreamRequestHandler):
	"""
	This TCP handler processes requests sent from PHP socket side.
	When a socket is created between PHP process and the daemon server, this handler gets and processes the request from the socket,
	and write the response or error to the socket to inform PHP process of the result.
	
	It adds valid requests to the queue which the worker threads will parse and run, and 
	refuses invalid or malformed requests.
	"""
	
	def get_error(self, err_id):
		"""
		Return a dict for the error, which will be encoded to JSON object and sent back to web callback side.
		"""
		return {
			'error': err_id,
			'error_description': EXCEPTION_LIST[err_id]
		}
	
	def send_json_obj(self, response):
		"""
		Dump the response to a JSON object and write it to the socket.
		"""
		self.wfile.write(json.dumps(response, indent = 4) + "\r\n")
	
	def handle(self):
		cli_addr = self.client_address[0]
		# verify data source
		
		data = self.rfile.readline().strip()
		Logger.debug('Received request from client ' + cli_addr + ': \n' + data)
		
		sender = None
		try:
			self.json_data = json.loads(data)
			if 'api_key' not in self.json_data or getSenderInfo(self.json_data['api_key'], cli_addr) == None:
				self.send_json_obj(self.get_error('unauthenticated_request'))
				return
		except ValueError:
			self.send_json_obj(self.get_error('invalid_json_request'))
			return
        	
        	if 'protocol_type' not in self.json_data:
			self.send_json_obj(self.get_error('invalid_request'))
			return
		if self.json_data['protocol_type'] == 'base64':
			# TODO: should bsae64_decode the file(s) and transform the request to a path-compatible one
			response_type = 'base64'
			self.send_json_obj(self.get_error('unimplemented_function'))
			return
		elif self.json_data['protocol_type'] == 'path':
			response_type = 'path'
		else:
			self.send_json_obj(self.get_error('invalid_request'))
			return
		
		# add task to database
		# assume the json_data has valid data structure
		add_task = ("INSERT INTO queue (submission_id, user_id, priority, file_path, api_key, assignment, date_created, response_type)"
				"VALUES (%(submission_id)s, %(user_id)s, %(priority)s, %(file_path)s, %(api_key)s, %(assignment)s, NOW(), %(response_type)s)")
		
		data_task = {
			'submission_id': self.json_data['submission_id'],
			'user_id': self.json_data['user_id'],
			'priority': self.json_data['priority'],
			'assignment': json.dumps(self.json_data['assignment']),
			'api_key': self.json_data['api_key'],
			'file_path': self.json_data['src_file'],
			'response_type': response_type
		}
		
		# TODO: should handle connection failure here?
		conn = mysql.connector.connect(**Settings['DATABASE_CONN_PARAM'])
		cursor = conn.cursor()
		cursor.execute(add_task, data_task)
		queued_id = cursor.lastrowid
		conn.commit()
		cursor.close()
		conn.close()
		
		# wake up the worker
		IncomingTaskEvent.set()
		
		response = {
			'status': 'queued',
			'queued_id': queued_id
		}
		Logger.debug("Request got queued with id " + queued_id + ".")
		self.send_json_obj(response)

class DaemonTCPServer(SocketServer.ThreadingMixIn, SocketServer.TCPServer):
	pass

if __name__ == "__main__":
	
	gc.enable()
	print Settings
	
	# test database connection
	try: 
		_conn = mysql.connector.connect(**Settings['DATABASE_CONN_PARAM'])
		_conn.close()
	except mysql.connector.Error as err:
		Logger.critical('mysql error {}.'.format(err))
		sys.exit(1)
	
	for x in range(Settings['NUM_OF_WORKERS']):
		GraderWorker().start()
	
	SocketServer.TCPServer.allow_reuse_address = True
	server = DaemonTCPServer((Settings['DAEMON_HOST'], Settings['DAEMON_PORT']), DaemonTCPHandler)
	try:
		server.serve_forever()
	except KeyboardInterrupt:
		Logger.info('Shutting down')
		server.shutdown()
		Logger.info('Daemon server exited successfully.')
		sys.exit(0)