#!/bin/bash

set -ex

vapor="php ./vendor/bin/vapor"

${vapor} deploy production --commit="$(git rev-parse HEAD)" --message="$(git log -1 --pretty=%B)"
