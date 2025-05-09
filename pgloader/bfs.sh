#!/bin/bash

PROD_DB_USER=vapor
PROD_DB_PASS=iMoKYGU59rRJSeH6t8h6gVFjJ6DGWn5Hsfuj07Ae
PROD_DB_HOST=sageus.cluster-c0dmc8x7csyg.us-east-1.rds.amazonaws.com
PROD_DB_PORT=5432
PROD_DB_NAME=vapor
SSH_KEY_FILE=/home/dan/.ssh/predict_jumpbox
JUMPBOX_CONNECTION=ec2-user@ec2-18-234-164-36.compute-1.amazonaws.com
LOCAL_TUNNEL_PORT=5433

LOCAL_DB_USER=sail
LOCAL_DB_PASS=password
LOCAL_DB_NAME=laravel
LOCAL_DB_PORT=5444

ssh -i ${SSH_KEY_FILE} -L ${LOCAL_TUNNEL_PORT}:${PROD_DB_HOST}:${PROD_DB_PORT} ${JUMPBOX_CONNECTION} -N

# Run pgloader with your migration command
./build/bin/pgloader postgresql://${PROD_DB_USER}:${PROD_DB_PASS}@localhost:${LOCAL_TUNNEL_PORT}/${PROD_DB_NAME} postgres://${LOCAL_DB_USER}:${LOCAL_DB_PASS}@localhost:${LOCAL_DB_PORT}/${LOCAL_DB_NAME}
