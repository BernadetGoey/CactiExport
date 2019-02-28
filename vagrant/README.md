The Vagrant folder can be used for local development. It sets up a vagrant environment with Cacti and InfluxDB.

The Cacti mysql user is:
Username: cactiuser
Password: cactiuser
Host: localhost
Port: 3306
Database: cacti

The InfluxDB user is:
Username: export
Password: export
Host: localhost
Port: 8086
Database: exportdb

Make sure to set the InfluxDB settings in the plugin settings after installing the plugin

Cacti will be available on: http://localhost:8018/cacti