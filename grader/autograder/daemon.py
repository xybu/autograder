#!/usr/bin/python

"""The daemon host and port"""
DAEMON_ADDRESS = ('127.0.0.1', 8080)

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

import gc
import json
import datetime
import threading
import SocketServer
import mysql.connector

IncomingTaskEvent = threading.Event()
ConnectionLock = threading.RLock()

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
				task_id, task_submission_id, task_user_id, task_priority, task_file_path, task_api_key, task_assignment, task_date_created = row
				print task_id
				cursor = self._conn.cursor()
				query = ("DELETE FROM queue WHERE id = %s")
				cursor.execute(query, [task_id])
				self._conn.commit()
				cursor.close()
				self._conn.close()
				ConnectionLock.release()
				print row
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