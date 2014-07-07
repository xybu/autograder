CREATE USER 'ag_daemon_u'@'localhost' IDENTIFIED BY 'demo';
CREATE USER 'ag_web_u'@'localhost' IDENTIFIED BY 'demo';
GRANT ALL PRIVILEGES ON ag_daemon.* TO 'ag_daemon_u'@'localhost';
GRANT ALL PRIVILEGES ON ag_web.* TO 'ag_web_u'@'localhost';