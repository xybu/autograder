#!/usr/bin/python

import BaseHTTPServer
import sqlite3
import json

from sys import version as python_version
from cgi import parse_header, parse_multipart

if python_version.startswith('3'):
	from urllib.parse import parse_qs
else:
    from urlparse import parse_qs

class AutograderHttpDaemon(BaseHTTPServer.HTTPServer):
	
	def __init__(self, hostname, port, valid_hosts, valid_keys):
		AutograderHttpHandler.valid_keys = valid_keys
		AutograderHttpHandler.valid_hosts = valid_hosts
		BaseHTTPServer.HTTPServer.__init__(self, (hostname, port), AutograderHttpHandler)

class AutograderHttpHandler(BaseHTTPServer.BaseHTTPRequestHandler):
	
	def do_POST(s):
		
		s.send_response(200)
		s.send_header("Content-type", "application/json")
		s.end_headers()
		
		# Verify the request
		#if "X-Authenticate" not in s.headers or s.headers["X-Authenticate"] not in AutograderHttpHandler.valid_keys or s.client_address[0] not in AutograderHttpHandler.valid_hosts:
		#	err = {
		#		"error": "unauthenticated_request",
		#		"error_description": "The request is not authenticated."
		#	}
		#	s.wfile.write(json.dumps(err))
		#	return
		
		if s.headers["Content-Type"] == 'multipart/form-data':
			pdict = parse_header(s.headers)
			post_vars = parse_multipart(s.rfile, pdict)
		elif s.headers["Content-Type"] == 'application/x-www-form-urlencoded':
			length = int(s.headers['Content-Length'])
			post_vars = parse_qs(s.rfile.read(length), keep_blank_values=1)
		
		
		
		s.wfile.write("<html><head><title>Title goes here.</title></head>")
		s.wfile.write("<body><p>This is a test.</p>")
		# Path is something like "/foo/bar/".
		s.wfile.write("<p>You accessed path: %s</p>" % s.path)
		s.wfile.write("</body></html>")
		
