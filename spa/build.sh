#!/bin/bash

DANX_DIR="../../quasar-ui-danx"

# If the quasar-ui-danx directory exists, build that first
if [ -d "$DANX_DIR" ]; then
  CURRENT_DIR=$(pwd)
  cd ../../quasar-ui-danx/ui || exit
  rm -rf node_modules
  yarn
  yarn build

  cd "$CURRENT_DIR" || exit
fi

rm -rf node_modules
yarn

# Inject the commit SHA into the environment as the App Version
# Used to both verify current running version and to invalidate cache when server version does not match
VITE_APP_APP_VERSION=$(git rev-parse HEAD) yarn build
