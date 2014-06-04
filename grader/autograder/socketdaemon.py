#!/usr/bin/python

import asyncore
import socket
import io

class SocketHandler(asyncore.dispatcher_with_send):
	
	BUFFER_SIZE = 8192			# in Bytes
	MAX_BUFFER_SIZE = 524288	# 500 KiB
	
	# Assuming the APi sends only string
	def handle_read(self):
		data = ""
		while True:
			buf = self.recv(SocketHandler.BUFFER_SIZE)
			print "Buffer:"
			print buf
			if buf == "":
				break
			data = data + buf
		
		if data:
			f = open("data.txt", "w")
			f.write(data)
			f.close()
		
		self.send("OK")
		
		#if data:
		#	self.send(data)

class SocketDaemon(asyncore.dispatcher):
	
	# Maximum number of items to queue in the server
	# No need to change the value (system-dependent).
	BACKLOG_SIZE = 5
	
	def __init__(self, host, port):
		asyncore.dispatcher.__init__(self)
		self.create_socket(socket.AF_INET, socket.SOCK_STREAM)
		self.set_reuse_addr()
		self.bind((host, port))
		self.listen(SocketDaemon.BACKLOG_SIZE)
	
	def handle_accept(self):
		pair = self.accept()
		if pair is not None:
			sock, addr = pair
			print 'Received a connection from %s' % repr(addr)
			handler = SocketHandler(sock)

if __name__ == '__main__':
	server = SocketDaemon('localhost', 8080)
	try:
		asyncore.loop()
	except KeyboardInterrupt:
		server.close()
		print "Server has been closed."
	
