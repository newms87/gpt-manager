#!/bin/bash

pwd
ls -lah
yarn

# Inject the commit SHA into the environment as the App Version
# Used to both verify current running version and to invalidate cache when server version does not match
VITE_APP_APP_VERSION=$(git rev-parse HEAD) yarn build-only

rm -rf node_modules
