#!/usr/bin/python

# Random password generator
# author	Xiangyu Bu (xybu92@live.com)

import random
import string

def generate(num, size, base = string.ascii_uppercase + string.ascii_lowercase + string.digits, unique = True):
	l = []
	while len(l) < num:
		s = ''.join(random.choice(base) for _ in range(size))
		if s not in l or not unique:
			l.append(s)
	return l

import os

if os.path.exists("user.lst"):
	with open("user.lst") as f:
		unames = f.readlines()
	lst = generate(num = len(unames), size = 9)
	dic = {}
	for i, val in enumerate(unames):
		dic[val.rstrip()] = lst[i]
	
	# plain dictionary output
	# print dic
	
	# to json
	import json
	print json.dumps(dic)
	
else:
	lst = generate(num = 20, size = 10)
	print lst

