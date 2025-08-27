#!/bin/bash

# Remove existing pgloader
sudo apt remove pgloader -y

# Install dependencies
sudo apt-get install sbcl unzip libsqlite3-dev make curl gawk freetds-dev libzip-dev

# Clone the repository
git clone https://github.com/dimitri/pgloader.git

cd pgloader || exit

# Set a higher memory limit for SBCL
export SBCL_DYNAMIC_SPACE_SIZE=12288  # 4GB of memory, adjust as needed

# Clean previous build
make clean

# build
make pgloader
make save  # THIS STEP IS CRUCIAL

sudo mv build/bin/pgloader /usr/local/bin/pgloader --force
