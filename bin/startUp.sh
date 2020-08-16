#!/bin/bash

DBHOST=127.0.0.1
DBPORT=33306
DBUSER=docker
DBPASS=docker

export ROOTPATH="$( cd "$(dirname "$0")/.." ; pwd -P )"

mysql -h ${DBHOST} -P ${DBPORT} -u ${DBUSER} -p${DBPASS} < ${ROOTPATH}/db/createTables.sql
