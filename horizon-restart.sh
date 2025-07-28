#!/bin/bash

HORIZON_PID_FILE="storage/horizon.pid"

check_horizon_running() {
    if [ -f "$HORIZON_PID_FILE" ]; then
        local pid=$(cat "$HORIZON_PID_FILE")
        if ps -p "$pid" > /dev/null 2>&1; then
            return 0  # Running
        fi
    fi
    return 1  # Not running
}

wait_for_horizon_stop() {
    local max_wait=30  # Maximum wait time in seconds
    local wait_time=0
    
    while check_horizon_running && [ $wait_time -lt $max_wait ]; do
        echo "Waiting for Horizon to stop... (${wait_time}s)"
        sleep 1
        wait_time=$((wait_time + 1))
    done
    
    if [ $wait_time -ge $max_wait ]; then
        echo "Warning: Horizon did not stop within ${max_wait} seconds"
        return 1
    fi
    
    return 0
}

echo "Checking Horizon status..."
if check_horizon_running; then
    echo "Terminating Horizon..."
    ./vendor/bin/sail artisan horizon:terminate
    
    if wait_for_horizon_stop; then
        echo "Horizon stopped successfully"
    else
        echo "Force killing Horizon processes..."
        ./vendor/bin/sail artisan horizon:terminate --force 2>/dev/null || true
        sleep 2
    fi
else
    echo "Horizon is not running"
fi

echo "Clearing caches..."
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear

echo "Starting Horizon..."
./vendor/bin/sail artisan horizon &

echo "Horizon restart completed"