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

import json
import datetime
import threading
import SocketServer
import mysql.connector

IncomingTaskEvent = threading.Event()

class LockedConnection():
	_conn_lock = threading.RLock()
	_conn = None
	
	def get_conn():
		LockedConnection._conn_lock.acquire()
		return LockedConnection._conn
	
	def release_conn():
		LockedConnection._conn_lock.release()

class GraderWorker(threading.Thread):
	def __init__(self):
		threading.Thread.__init__(self)
		self.daemon = True
	
	def run(self):
		print "hi!"

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
		except ValueError:
			self.send_json_obj(self.get_error('invalid_json_request'))
			return
        	
        	if 'protocol_type' not in self.json_data:
			self.send_json_obj(self.get_error('invalid_request'))
			return
		elif self.json_data['protocol_type'] == 'path':
			pass
		elif self.json_data['protocol_type'] == 'base64':
			self.send_json_obj(self.get_error('unimplemented_function'))
			return
		else:
			# unknown prococol type
			self.send_json_obj(self.get_error('invalid_request'))
			return
        	
		self.wfile.write("done.")

class DaemonTCPServer(SocketServer.ThreadingMixIn, SocketServer.TCPServer):
	pass

if __name__ == "__main__":
	
	try: 
		DaemonTCPHandler._conn = mysql.connector.connect(**MYSQL_PARAMS)
		LockedConnection._conn = mysql.connector.connect(**MYSQL_PARAMS)
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