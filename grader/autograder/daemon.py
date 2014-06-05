#!/usr/bin/python

DAEMON_ADDRESS = ('127.0.0.1', 8080)

VALID_SENDERS = {
	'aaaaa': ['127.0.0.1']
}

EXCEPTION_LIST = {
	'unauthenticated_request':	"The request is not authenticated.",
	'invalid_json_request':		"The data sent cannot be JSON decoded.",
	'invalid_request':			"The request is not of valid format.",
	'unknown_exception':		"An unknown exception has occurred.",
	'unimplemented_function':	"The request involves unimplemented function.",
	'db_access_denied_error':	"Failed to connect Autograder database.",
	'db_does_not_exist_error':	"Database does not exist."
	'db_unknown_error':			"Unknown database error."
}

MYSQL_PARAMS = {
	'host': '127.0.0.1',
	'user': 'root',
	'password': '',
	'port': 3306,
	'database': 'ag_daemon'
}

import sys
import json
import sqlite3
import datetime
import threading
import SocketServer
import mysql.connector

class DaemonRequestHandler(SocketServer.BaseRequestHandler):
	
	def get_error_obj(self, err_id):
		return {
			'error': err_id,
			'error_description': EXCEPTION_LIST[err_id]
		}
	
	def send_json_obj(self, response):
		self.request.send(json.dumps(response, indent = 4))
	
	def handle(self):
		
		self.conn = None
		
		addr, port = self.client_address
		
		buf = ""
		while True:
			data = self.request.recv(1024)
			if data == "":
				break
			buf = buf + data
		
		response = {}
		err = {}
		
		# Load JSON object and verify the request
		try:
			json_obj = json.loads(buf)
			if ('api_key' not in json_obj or 
				json_obj['api_key'] not in VALID_SENDERS or
				addr not in VALID_SENDERS[json_obj['api_key']]):
				err = self.get_error_obj('unauthenticated_request')
			self.conn = mysql.connector.connect(**MYSQL_PARAMS)
		except ValueError:
			err = self.get_error_obj('invalid_json_request')
		except mysql.connector.Error as err:
			if err.errno == errorcode.ER_ACCESS_DENIED_ERROR:
				err = self.get_err_obj('db_access_denied_error')
			elif err.errno == errorcode.ER_BAD_DB_ERROR:
				err = self.get_err_obj('db_does_not_exist_error')
			else:
				err = self.get_err_obj('db_unknown_error')
		except:
			err = self.get_err_obj('unknown_exception')
		
		if err:
			self.send_json_obj(err)
			return
		
		# Process the request
		if 'path' in json_obj:
			pass
		elif 'raw' in json_obj:
			# Should save the file somewhere and update the path param
			err = self.get_err_obj('unimplemented_function')
		else:
			err = self.get_err_obj('invalid_request')
		
		if err:
			self.send_json_obj(err)
			return
		
		response['status'] = 'accepted'
		response['timestamp'] = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
		
		self.send_json_obj(response)
		return
	
	def finish(self):
		if self.conn != None:
			self.conn.close()
	
class DaemonServer(SocketServer.ThreadingMixIn, SocketServer.TCPServer):
	pass
	
if __name__ == '__main__':
	try:
		SocketServer.TCPServer.allow_reuse_address = True
		server = DaemonServer(DAEMON_ADDRESS, DaemonRequestHandler)
		server.serve_forever()
		# server_thread = threading.Thread(target=server.serve_forever)
		# Exit the server thread when the main thread terminates
		# server_thread.daemon = True
		# server_thread.start()
		# server_thread.join()
	except KeyboardInterrupt:
		server.socket.close()
		sys.exit(0)
