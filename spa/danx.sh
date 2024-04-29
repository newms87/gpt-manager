#!/bin/bash

set +x

# Resolve path to quasar-ui-danx (can be up 1 or 2 directories)
# shellcheck disable=SC2046
DANX_PATH="$(realpath $(find ../.. -maxdepth 2 -type d -name "quasar-ui-danx" | head -n 1))/ui"
MODULE_PATH="$(pwd)/node_modules/quasar-ui-danx"

# symlink ../quasar-ui-danx to node_modules/quasar-ui-danx if the directory exists
if [ -d "$DANX_PATH" ]; then
  rm -rf node_modules/quasar-ui-danx
  ln -s "$DANX_PATH" "$MODULE_PATH"
  printf "Symlinked $DANX_PATH --> $MODULE_PATH\n"
fi
