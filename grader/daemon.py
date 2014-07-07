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
import urllib
import threading
import subprocess
import SocketServer
import mysql.connector

with open('grader.json', 'r') as s_f:
	Settings = json.load(s_f)

# this event is used to wake up sleeping workers
IncomingTaskEvent = threading.Event()

# just in case more than one worker gets the same task
ConnectionLock = threading.RLock()

# used to hold the daemon log
Logger = logger.Logger()

class Connector:
	
	@staticmethod
	def get_sender(api_key, cli_addr):
		lst = Settings['TRUSTED_KEY_LIST']
		if api_key in lst and cli_addr in lst[api_key]:
			return lst[api_key][cli_addr]
		return None
	
	@staticmethod
	def task_start_callback(sender_api_key, cli_addr, submission_id):
		cb_sender = Connector.get_sender(sender_api_key, cli_addr)
		if cb_sender == None: return
		params = urllib.urlencode({
			'grader_key': Settings["API_KEY"],
			'type': 'start',
			'submission_id': submission_id
		})
		f = urllib.urlopen(cb_sender['url'], params)
		if f.getcode() != 200:
			Logger.warning('Got HTTP code ' + str(f.getcode()) + ' when calling back ' + cb_sender['url'] + '.')
		else:
			response_data = json.loads(f.read())
			if 'error' in response_data:
				Logger.warning('Got error "' + response_data['error'] + '" when executing task_start_callback(' + sender_api_key + ', ' + submission_id + ')')
			elif 'status' in response_data:
				pass
		f.close()
	
	@staticmethod
	def task_done_error_callback(sender_api_key, cli_addr, submission_id, error_code, error_desc):
		cb_sender = Connector.get_sender(sender_api_key, cli_addr)
		if cb_sender == None: return
		params = {
			'grader_key': Settings["API_KEY"],
			'type': 'done',
			'submission_id': submission_id,
			'error': error_code,
			'error_description': error_desc,
		}
		f = urllib.urlopen(cb_sender['url'], urllib.urlencode(params))
		if f.getcode() != 200:
			Logger.warning('Got HTTP code ' + str(f.getcode()) + ' when calling back ' + cb_sender['url'] + '.')
		else:
			response_data = json.loads(f.read())
			if 'error' in response_data:
				Logger.warning('Got error "' + response_data['error'] + '" when executing task_start_callback(' + sender_api_key + ', ' + submission_id + ')')
			else:
				pass
		f.close()
	
	@staticmethod
	def task_done_callback(sender_api_key, cli_addr, submission_id, dump_file_path, grade_detail, internal_log, formal_log):
		cb_sender = Connector.get_sender(sender_api_key, cli_addr)
		if cb_sender == None: return
		params = {
			'grader_key': Settings["API_KEY"],
			'type': 'done',
			'submission_id': submission_id,
			'protocol_type': cb_sender['protocol_type'],
			'grade_detail': grade_detail.encode("base64"),
			'internal_log': internal_log.encode("base64"),
			'formal_log': formal_log.encode("base64"),
		}
		
		if 'protocol_type' not in cb_sender:
			Logger.critical('Protocol type undefined for server (' + sender_api_key + ', ' + cli_addr + ').')
			return
		elif cb_sender['protocol_type'] == 'path':
			params['dump_file'] = dump_file_path
		elif cb_sender['protocol_type'] == 'base64':
			with open(dump_file_path, "rb") as f:
				params['dump_file'] = f.read().encode('base64')
		else:
			Logger.critical('Unsupported protocol type "{0}" of server ({1}, {2}).'.format(cb_sender['protocol_type'], sender_api_key, cli_addr))
			return
		
		f = urllib.urlopen(cb_sender['url'], urllib.urlencode(params))
		if f.getcode() != 200:
			Logger.warning('Got HTTP code ' + str(f.getcode()) + ' when calling back ' + cb_sender['url'] + '.')
		else:
			response_data = json.loads(f.read())
			if 'error' in response_data:
				Logger.warning('Got error "' + response_data['error'] + '" when executing task_start_callback(' + sender_api_key + ', ' + submission_id + ')')
			elif 'status' in response_data and response_data['status'] == 'success':
				try:
					os.remove(dump_file_path)
				except:
					pass
		f.close()	
	
class GraderWorker(threading.Thread):
	
	def __init__(self):
		threading.Thread.__init__(self)
		self.daemon = True
	
	def handle_task(self, submission_row):
		# cwd = os.getcwd()
		task_id, task_submission_id, task_user_id, task_priority, task_file_path, task_assignment, task_date_created, task_api_key, task_cli_addr = submission_row
		
		try:
			task_assignment = json.loads(task_assignment)
		except ValueError:
			Logger.critical('Invalid JSON assignment info. Row data: {0}.'.format(submission_row))
			Connector.task_done_error_callback(task_api_key, task_cli_addr, task_submission_id, "invalid_json_assignment_info", "The assignment info is not valid JSON data.")
			return
		
		submission_temp_path = Settings['TEMP_PATH'] + str(task_id) + "/"
		try:
			# simulate `mkdir -p`
			os.makedirs(submission_temp_path, 0777)
		except (IOError, OSError) as ex:
			if ex.errno == errno.EEXIST and os.path.isdir(submission_temp_path):	
				pass
			else:
				Logger.critical('Failed to create temporary path for task {0} ({1}).'.format(task_id, ex))
				Connector.task_done_error_callback(task_api_key, task_cli_addr, task_submission_id, "mkdir_error", "Failed to create temporary path to hold task " + str(task_id) + ".")
				return
		
		try:
			print task_file_path
			print submission_temp_path + Settings['SUBMISSION_FILE_NAME']
			shutil.copyfile(task_file_path, submission_temp_path + Settings['SUBMISSION_FILE_NAME'])
		except IOError as e:
			Logger.critical('Failed to copy the submission file for task {0} ({1}).'.format(task_id, e))
			Connector.task_done_error_callback(task_api_key, task_cli_addr, task_submission_id, "cp_error", "Failed to copy the submission file to grading path for task " + str(task_id) + ".")
			return
		except:
			pass
		
		# if there is a grader tar ball
		if 'grader_tar' in task_assignment and task_assignment['grader_tar'] != '':
			try:
				t = tarfile.open(task_assignment['grader_tar'])
				t.extractall(path = submission_temp_path)
			except Exception as e:
				Logger.critical('Failed to extract the grader tar file for task {0} ({1}).'.format(task_id, e))
				Connector.task_done_error_callback(task_api_key, task_cli_addr, task_submission_id, "untar_grader_tarball_error", "Failed to extract the grader tar file for task " + str(task_id) + ".")
				return
		
		# if it is a tar, try to decompress it
		if 'submit_filetype' in task_assignment and ('gz' in task_assignment['submit_filetype'] or 'tar' in task_assignment['submit_filetype'] or 'zip' in task_assignment['submit_filetype']):
			# WARNING: the tar ball may contain files with name having '/' or '..'
			try:
				t = tarfile.open(submission_temp_path + Settings['SUBMISSION_FILE_NAME'])
				t.extractall(path = submission_temp_path)
			except Exception as e:
				Logger.critical('Failed to extract the submission tar file for task {0} ({1}).'.format(task_id, e))
				Connector.task_done_error_callback(task_api_key, task_cli_addr, task_submission_id, "untar_submission_file_error", "Failed to extract the submission file for task " + str(task_id) + ".")
				return
				
		
		# execute the grading script
		if 'grader_script' not in task_assignment or len(task_assignment['grader_script']) == 0:
			Logger.critical('The grader script for submission ' + str(task_submission_id) + ' is not specified.')
			Connector.task_done_error_callback(task_api_key, task_cli_addr, task_submission_id, "undefined_grader_script", "The grader script is not defined.")
			return
		
		try:
			subp = subprocess.Popen([task_assignment['grader_script']], stdout = subprocess.PIPE, stderr = subprocess.PIPE, cwd = submission_temp_path)
			result = subp.communicate()
			Logger.debug("Execution result:\nstdout:\n{0}\nstderr:\n{1}\n".format(result[0], result[1]))
		except OSError as e:
			Logger.critical('Error executing the grading script. Permission denied.')
			Connector.task_done_error_callback(task_api_key, task_cli_addr, task_submission_id, "permission_denied", "The grading script file is not set executable.")
			return
		
		# assemble the grading result and send it to callback function
		if os.path.isfile(submission_temp_path + Settings['GRADEBOOK_FILE_NAME']):
			with open(submission_temp_path + Settings['GRADEBOOK_FILE_NAME'], "r") as f:
				grade_detail = f.read()
			with tarfile.open(submission_temp_path + "../dump_" + str(task_id) + ".tar.gz", "w:gz") as t:
				t.add(submission_temp_path, arcname = "dump")
			shutil.rmtree(submission_temp_path)
			Connector.task_done_callback(sender_api_key = task_api_key, cli_addr = task_cli_addr, submission_id = task_submission_id, dump_file_path = os.path.normpath(submission_temp_path + "../dump_" + str(task_id) + ".tar.gz"), grade_detail = grade_detail, internal_log = result[0], formal_log = result[1])
		else:
			Logger.critical('Gradebook file "' + submission_temp_path + Settings['GRADEBOOK_FILE_NAME'] + '" was not generated.')
			Connector.task_done_error_callback(task_api_key, task_cli_addr, task_submission_id, "no_gradebook", "The gradebook file was not generated. No grade.")
			return
	
	def run(self):
		Logger.debug('Started running.')
		while True:
			ConnectionLock.acquire()
			IncomingTaskEvent.clear()
			Logger.info('Querying new tasks.')
			conn = mysql.connector.connect(**Settings['DATABASE_CONN_PARAM'])
			cursor = conn.cursor()
			query = ("SELECT id, submission_id, user_id, priority, file_path, assignment, date_created, api_key, cli_addr FROM queue ORDER BY priority DESC, date_created ASC LIMIT 1")
			cursor.execute(query)
			row = cursor.fetchone()
			cursor.close()
			if row != None:
				cursor = conn.cursor()
				query = ("DELETE FROM queue WHERE id = %s")
				cursor.execute(query, [row[0]])
				time.sleep(3)	# TODO: remove this
				conn.commit()
				cursor.close()
				conn.close()
				ConnectionLock.release()
				self.handle_task(row)
				gc.collect()
			else:
				conn.close()
				Logger.info('Queue is empty. Put thread to sleep.')
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
		data = self.rfile.readline().strip()
		Logger.debug('Received request from client ' + cli_addr + ': \n' + data)
		
		try:
			self.json_data = json.loads(data)
			if 'api_key' not in self.json_data or Connector.get_sender(self.json_data['api_key'], cli_addr) == None:
				self.send_json_obj(self.get_error('unauthenticated_request'))
				return
		except ValueError:
			self.send_json_obj(self.get_error('invalid_json_request'))
			return
        	
        	if 'protocol_type' not in self.json_data:
			self.send_json_obj(self.get_error('invalid_request'))
			return
		elif self.json_data['protocol_type'] == 'base64':
			# TODO: should bsae64_decode the file(s) and transform the request to a path-compatible one
			
			self.send_json_obj(self.get_error('unimplemented_function'))
			return
		elif self.json_data['protocol_type'] == 'path':
			pass
		else:
			self.send_json_obj(self.get_error('invalid_request'))
			return
		
		# add task to database
		# assume the json_data has valid data structure
		add_task = ("INSERT INTO queue (submission_id, user_id, priority, file_path, assignment, date_created, api_key, cli_addr)"
				"VALUES (%(submission_id)s, %(user_id)s, %(priority)s, %(file_path)s, %(assignment)s, NOW(), %(api_key)s, %(cli_addr)s)")
		
		data_task = {
			'submission_id': self.json_data['submission_id'],
			'user_id': self.json_data['user_id'],
			'priority': self.json_data['priority'],
			'assignment': json.dumps(self.json_data['assignment']),
			'api_key': self.json_data['api_key'],
			'cli_addr': cli_addr,
			'file_path': self.json_data['src_file']
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
		Logger.debug("Request got queued with id " + str(queued_id) + ".")
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