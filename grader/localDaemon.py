#!/usr/bin/python

import time
from autograder import daemon

HOST_NAME = '127.0.0.1'
ACCEPTED_HOSTS = ["localhost", "127.0.0.1"]
ACCEPTED_KEYS = ["aaaaaa"]
PORT_NUMBER = 8720

if __name__ == '__main__':
	httpd = daemon.AutograderHttpDaemon(HOST_NAME, PORT_NUMBER, ACCEPTED_HOSTS,ACCEPTED_KEYS)
	print time.asctime(), "Server Starts - %s:%s" % (HOST_NAME, PORT_NUMBER)
	try:
		httpd.serve_forever()
	except KeyboardInterrupt:
		pass
	httpd.server_close()
	print time.asctime(), "Server Stops - %s:%s" % (HOST_NAME, PORT_NUMBER)
