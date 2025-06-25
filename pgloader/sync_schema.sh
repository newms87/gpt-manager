#!/bin/bash

# Set color codes for better UI
RED="\033[0;31m"
GREEN="\033[0;32m"
YELLOW="\033[0;33m"
BLUE="\033[0;34m"
MAGENTA="\033[0;35m"
CYAN="\033[0;36m"
NC="\033[0m" # No Color

# Default values for ports
LOCAL_TUNNEL_PORT=5433

# Title display function
print_title() {
    echo -e "\n${MAGENTA}┌──────────────────────────────────────────────┐${NC}"
    echo -e "${MAGENTA}│${CYAN}               Database Sync Utility               ${MAGENTA}│${NC}"
    echo -e "${MAGENTA}└──────────────────────────────────────────────┘${NC}\n"
}

# Print section header
print_section() {
    echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━ $1 ━━━━━━━━━━━━━━━━━━━━${NC}\n"
}

# Read local database settings from .env file
read_local_db_settings() {
    print_section "Reading Local Database Settings"
    
    ENV_FILE="../.env"
    
    if [ -f "$ENV_FILE" ]; then
        echo -e "${GREEN}Loading local database settings from .env file...${NC}"
        
        # Parse the .env file for database settings
        LOCAL_DB_HOST=$(grep -E '^DB_HOST=' "$ENV_FILE" | cut -d '=' -f2)
        LOCAL_DB_PORT=$(grep -E '^FORWARD_DB_PORT=' "$ENV_FILE" | cut -d '=' -f2) 
        if [ -z "$LOCAL_DB_PORT" ]; then
            LOCAL_DB_PORT=$(grep -E '^DB_PORT=' "$ENV_FILE" | cut -d '=' -f2)
        fi
        LOCAL_DB_NAME=$(grep -E '^DB_DATABASE=' "$ENV_FILE" | cut -d '=' -f2)
        LOCAL_DB_USER=$(grep -E '^DB_USERNAME=' "$ENV_FILE" | cut -d '=' -f2)
        LOCAL_DB_PASS=$(grep -E '^DB_PASSWORD=' "$ENV_FILE" | cut -d '=' -f2)
        
        # If any value is missing, set default
        LOCAL_DB_HOST=${LOCAL_DB_HOST:-"localhost"}
        LOCAL_DB_PORT=${LOCAL_DB_PORT:-"5432"}
        LOCAL_DB_NAME=${LOCAL_DB_NAME:-"laravel"}
        LOCAL_DB_USER=${LOCAL_DB_USER:-"root"}
        LOCAL_DB_PASS=${LOCAL_DB_PASS:-"password"}
        
        echo -e "${GREEN}Local database settings loaded successfully.${NC}"
    else
        echo -e "${YELLOW}Warning: .env file not found at $ENV_FILE${NC}"
        echo -e "${YELLOW}Using default local database settings...${NC}"
        
        # Default values for local database
        LOCAL_DB_HOST="localhost"
        LOCAL_DB_PORT="5444" # Default port from original script
        LOCAL_DB_NAME="laravel"
        LOCAL_DB_USER="sail"
        LOCAL_DB_PASS="password"
    fi
}

# Read production database settings from .env or prompt user
read_prod_db_settings() {
    print_section "Production Database Connection"
    
    ENV_FILE="../.env"
    
    # Check if production database settings exist in .env
    if [ -f "$ENV_FILE" ]; then
        PROD_DB_HOST=$(grep -E '^PROD_DB_HOST=' "$ENV_FILE" | cut -d '=' -f2)
        PROD_DB_PORT=$(grep -E '^PROD_DB_PORT=' "$ENV_FILE" | cut -d '=' -f2)
        PROD_DB_NAME=$(grep -E '^PROD_DB_NAME=' "$ENV_FILE" | cut -d '=' -f2)
        PROD_DB_USER=$(grep -E '^PROD_DB_USER=' "$ENV_FILE" | cut -d '=' -f2)
        PROD_DB_PASS=$(grep -E '^PROD_DB_PASS=' "$ENV_FILE" | cut -d '=' -f2)
        SSH_KEY_FILE=$(grep -E '^SSH_KEY_FILE=' "$ENV_FILE" | cut -d '=' -f2)
        JUMPBOX_CONNECTION=$(grep -E '^JUMPBOX_CONNECTION=' "$ENV_FILE" | cut -d '=' -f2)
    fi
    
    # Check if all required values are present and SSH key file exists
    if [ -n "$PROD_DB_HOST" ] && [ -n "$PROD_DB_PORT" ] && [ -n "$PROD_DB_NAME" ] && \
       [ -n "$PROD_DB_USER" ] && [ -n "$PROD_DB_PASS" ] && [ -n "$SSH_KEY_FILE" ] && \
       [ -n "$JUMPBOX_CONNECTION" ] && [ -f "$SSH_KEY_FILE" ]; then
        
        echo -e "${GREEN}Using production database settings from .env file:${NC}"
        echo -e "  SSH Key File: ${GREEN}${SSH_KEY_FILE}${NC}"
        echo -e "  Jumpbox Connection: ${GREEN}${JUMPBOX_CONNECTION}${NC}"
        echo -e "  Production DB Host: ${GREEN}${PROD_DB_HOST}${NC}"
        echo -e "  Production DB Port: ${GREEN}${PROD_DB_PORT}${NC}"
        echo -e "  Production DB Name: ${GREEN}${PROD_DB_NAME}${NC}"
        echo -e "  Production DB User: ${GREEN}${PROD_DB_USER}${NC}"
        echo -e "  Production DB Password: ${GREEN}********${NC}"
        
        # Update .env file to ensure consistency
        update_env_file
        return 0
    fi
    
    # Prompt for production database settings or confirm defaults
    echo -e "${CYAN}Please provide production database connection details:${NC}"
    
    # SSH Key File
    if [ -n "$SSH_KEY_FILE" ]; then
        echo -e "${YELLOW}SSH Key File [${SSH_KEY_FILE}]: ${NC}"
        read -r SSH_KEY_FILE_INPUT
        SSH_KEY_FILE=${SSH_KEY_FILE_INPUT:-$SSH_KEY_FILE}
    else
        echo -e "${YELLOW}SSH Key File (e.g., /home/user/.ssh/id_rsa): ${NC}"
        read -r SSH_KEY_FILE
        while [ ! -f "$SSH_KEY_FILE" ]; do
            echo -e "${RED}Error: SSH key file not found. Please provide a valid path: ${NC}"
            read -r SSH_KEY_FILE
        done
    fi
    
    # Jumpbox Connection
    if [ -n "$JUMPBOX_CONNECTION" ]; then
        echo -e "${YELLOW}Jumpbox Connection [${JUMPBOX_CONNECTION}]: ${NC}"
        read -r JUMPBOX_CONNECTION_INPUT
        JUMPBOX_CONNECTION=${JUMPBOX_CONNECTION_INPUT:-$JUMPBOX_CONNECTION}
    else
        echo -e "${YELLOW}Jumpbox Connection (e.g., user@host): ${NC}"
        read -r JUMPBOX_CONNECTION
        while [ -z "$JUMPBOX_CONNECTION" ]; do
            echo -e "${RED}Error: Jumpbox connection cannot be empty. Please provide a valid connection string: ${NC}"
            read -r JUMPBOX_CONNECTION
        done
    fi
    
    # Production DB Host
    if [ -n "$PROD_DB_HOST" ]; then
        echo -e "${YELLOW}Production DB Host [${PROD_DB_HOST}]: ${NC}"
        read -r PROD_DB_HOST_INPUT
        PROD_DB_HOST=${PROD_DB_HOST_INPUT:-$PROD_DB_HOST}
    else
        echo -e "${YELLOW}Production DB Host: ${NC}"
        read -r PROD_DB_HOST
        while [ -z "$PROD_DB_HOST" ]; do
            echo -e "${RED}Error: Production DB host cannot be empty. Please provide a valid host: ${NC}"
            read -r PROD_DB_HOST
        done
    fi
    
    # Production DB Port
    if [ -n "$PROD_DB_PORT" ]; then
        echo -e "${YELLOW}Production DB Port [${PROD_DB_PORT}]: ${NC}"
        read -r PROD_DB_PORT_INPUT
        PROD_DB_PORT=${PROD_DB_PORT_INPUT:-$PROD_DB_PORT}
    else
        echo -e "${YELLOW}Production DB Port [5432]: ${NC}"
        read -r PROD_DB_PORT_INPUT
        PROD_DB_PORT=${PROD_DB_PORT_INPUT:-"5432"}
    fi
    
    # Production DB Name
    if [ -n "$PROD_DB_NAME" ]; then
        echo -e "${YELLOW}Production DB Name [${PROD_DB_NAME}]: ${NC}"
        read -r PROD_DB_NAME_INPUT
        PROD_DB_NAME=${PROD_DB_NAME_INPUT:-$PROD_DB_NAME}
    else
        echo -e "${YELLOW}Production DB Name: ${NC}"
        read -r PROD_DB_NAME
        while [ -z "$PROD_DB_NAME" ]; do
            echo -e "${RED}Error: Production DB name cannot be empty. Please provide a valid database name: ${NC}"
            read -r PROD_DB_NAME
        done
    fi
    
    # Production DB User
    if [ -n "$PROD_DB_USER" ]; then
        echo -e "${YELLOW}Production DB User [${PROD_DB_USER}]: ${NC}"
        read -r PROD_DB_USER_INPUT
        PROD_DB_USER=${PROD_DB_USER_INPUT:-$PROD_DB_USER}
    else
        echo -e "${YELLOW}Production DB User: ${NC}"
        read -r PROD_DB_USER
        while [ -z "$PROD_DB_USER" ]; do
            echo -e "${RED}Error: Production DB user cannot be empty. Please provide a valid username: ${NC}"
            read -r PROD_DB_USER
        done
    fi
    
    # Production DB Password
    if [ -n "$PROD_DB_PASS" ]; then
        echo -e "${YELLOW}Production DB Password [********]: ${NC}"
        read -rs PROD_DB_PASS_INPUT
        echo
        PROD_DB_PASS=${PROD_DB_PASS_INPUT:-$PROD_DB_PASS}
    else
        echo -e "${YELLOW}Production DB Password: ${NC}"
        read -rs PROD_DB_PASS
        echo
        while [ -z "$PROD_DB_PASS" ]; do
            echo -e "${RED}Error: Production DB password cannot be empty. Please provide a valid password: ${NC}"
            read -rs PROD_DB_PASS
            echo
        done
    fi
    
    # Update .env file with production settings
    update_env_file
}

# Update .env file with production settings
update_env_file() {
    ENV_FILE="../.env"
    
    if [ -f "$ENV_FILE" ]; then
        echo -e "${GREEN}Updating .env file with production database settings...${NC}"
        
        # Check if PROD settings already exist in .env
        if grep -q "^PROD_DB_HOST=" "$ENV_FILE"; then
            # Update existing entries
            sed -i "s|^PROD_DB_HOST=.*|PROD_DB_HOST=${PROD_DB_HOST}|" "$ENV_FILE"
            sed -i "s|^PROD_DB_PORT=.*|PROD_DB_PORT=${PROD_DB_PORT}|" "$ENV_FILE"
            sed -i "s|^PROD_DB_NAME=.*|PROD_DB_NAME=${PROD_DB_NAME}|" "$ENV_FILE"
            sed -i "s|^PROD_DB_USER=.*|PROD_DB_USER=${PROD_DB_USER}|" "$ENV_FILE"
            sed -i "s|^PROD_DB_PASS=.*|PROD_DB_PASS=${PROD_DB_PASS}|" "$ENV_FILE"
            sed -i "s|^SSH_KEY_FILE=.*|SSH_KEY_FILE=${SSH_KEY_FILE}|" "$ENV_FILE"
            sed -i "s|^JUMPBOX_CONNECTION=.*|JUMPBOX_CONNECTION=${JUMPBOX_CONNECTION}|" "$ENV_FILE"
        else
            # Add new entries
            echo "" >> "$ENV_FILE"
            echo "# Production database settings" >> "$ENV_FILE"
            echo "PROD_DB_HOST=${PROD_DB_HOST}" >> "$ENV_FILE"
            echo "PROD_DB_PORT=${PROD_DB_PORT}" >> "$ENV_FILE"
            echo "PROD_DB_NAME=${PROD_DB_NAME}" >> "$ENV_FILE"
            echo "PROD_DB_USER=${PROD_DB_USER}" >> "$ENV_FILE"
            echo "PROD_DB_PASS=${PROD_DB_PASS}" >> "$ENV_FILE"
            echo "SSH_KEY_FILE=${SSH_KEY_FILE}" >> "$ENV_FILE"
            echo "JUMPBOX_CONNECTION=${JUMPBOX_CONNECTION}" >> "$ENV_FILE"
        fi
        
        echo -e "${GREEN}Successfully updated .env file.${NC}"
    else
        echo -e "${YELLOW}Warning: .env file not found. Could not save production settings.${NC}"
    fi
}

# Display connection details
display_connection_details() {
    print_section "Connection Details Summary"
    
    echo -e "${CYAN}Local Database:${NC}"
    echo -e "  Host:     ${GREEN}${LOCAL_DB_HOST}${NC}"
    echo -e "  Port:     ${GREEN}${LOCAL_DB_PORT}${NC}"
    echo -e "  Database: ${GREEN}${LOCAL_DB_NAME}${NC}"
    echo -e "  Username: ${GREEN}${LOCAL_DB_USER}${NC}"
    echo -e "  Password: ${GREEN}********${NC}"
    
    echo -e "\n${CYAN}Production Database:${NC}"
    echo -e "  Host:     ${GREEN}${PROD_DB_HOST}${NC}"
    echo -e "  Port:     ${GREEN}${PROD_DB_PORT}${NC}"
    echo -e "  Database: ${GREEN}${PROD_DB_NAME}${NC}"
    echo -e "  Username: ${GREEN}${PROD_DB_USER}${NC}"
    echo -e "  Password: ${GREEN}********${NC}"
    
    echo -e "\n${CYAN}SSH Connection:${NC}"
    echo -e "  Key File:   ${GREEN}${SSH_KEY_FILE}${NC}"
    echo -e "  Jumpbox:    ${GREEN}${JUMPBOX_CONNECTION}${NC}"
    echo -e "  Local Port: ${GREEN}${LOCAL_TUNNEL_PORT}${NC}"
    
    echo -e "\nPress Enter to continue or Ctrl+C to abort..."
    read -r
}

# Choose sync type
choose_sync_type() {
    print_section "Sync Type Selection"
    
    echo -e "${CYAN}Please choose the type of sync:${NC}"
    echo -e "  ${GREEN}1${NC}) Schema Only (tables, indexes, but no data)"
    echo -e "  ${GREEN}2${NC}) Full Sync (schema + data, will completely replace local data)"
    
    local choice
    read -p "Enter your choice (1 or 2): " choice
    
    case $choice in
        1)
            SYNC_TYPE="schema"
            echo -e "\n${GREEN}Selected: Schema Only Sync${NC}"
            ;;
        2)
            SYNC_TYPE="full"
            echo -e "\n${RED}Warning: This will completely replace your local database data with production data.${NC}"
            echo -e "${YELLOW}Are you sure you want to continue? (y/n)${NC}"
            
            local confirm
            read -p "Confirm (y/n): " confirm
            if [[ $confirm != "y" && $confirm != "Y" ]]; then
                echo -e "\n${YELLOW}Operation cancelled.${NC}"
                exit 0
            fi
            echo -e "\n${GREEN}Selected: Full Database Sync${NC}"
            ;;
        *)
            echo -e "\n${RED}Invalid choice. Defaulting to Schema Only.${NC}"
            SYNC_TYPE="schema"
            ;;
    esac
}

# Create pgloader configuration
create_pgloader_config() {
    print_section "Creating PGLoader Configuration"
    
    local config_file="temp_db_sync.load"
    
    echo -e "${GREEN}Creating pgloader configuration file...${NC}"
    
    # Start building the configuration
    cat > "$config_file" << EOL
LOAD DATABASE
     FROM postgresql://${PROD_DB_USER}:${PROD_DB_PASS}@localhost:${LOCAL_TUNNEL_PORT}/${PROD_DB_NAME}
     INTO postgresql://${LOCAL_DB_USER}:${LOCAL_DB_PASS}@localhost:${LOCAL_DB_PORT}/${LOCAL_DB_NAME}

WITH include drop,
     create tables,
     create indexes,
EOL
    
    # Add schema only parameter if requested
    if [ "$SYNC_TYPE" = "schema" ]; then
        echo "     schema only" >> "$config_file"
    else
        echo "     reset sequences," >> "$config_file"
        echo "     foreign keys" >> "$config_file"
    fi
    
    # Add common settings
    cat >> "$config_file" << EOL

SET maintenance_work_mem to '512MB',
    work_mem to '16MB',
    standard_conforming_strings to 'on',
    client_encoding to 'utf8'
EOL
    
    # Add data type mappings for full sync
    if [ "$SYNC_TYPE" = "full" ]; then
        cat >> "$config_file" << EOL

CAST type json to json using identity,
     type datetime to timestamptz,
     type enum when (= "utf8mb4_0900_ai_ci" collation) to text
EOL
    fi
    
    # Add schema handling
    cat >> "$config_file" << EOL

BEFORE LOAD DO
\$\$ DROP SCHEMA IF EXISTS public CASCADE; \$\$,
\$\$ CREATE SCHEMA public; \$\$;
EOL
    
    echo -e "${GREEN}Configuration file created successfully.${NC}"
    return 0
}

# Run pgloader with configuration
run_pgloader() {
    print_section "Running Database Sync"
    
    local config_file="temp_db_sync.load"
    
    # Check if pgloader is installed locally
    if command -v pgloader &> /dev/null; then
        echo -e "${GREEN}Running pgloader...${NC}\n"
        pgloader "$config_file"
    else
        # Try using the built version
        if [ -f ./build/bin/pgloader ]; then
            echo -e "${GREEN}Running pgloader from local build...${NC}\n"
            ./build/bin/pgloader "$config_file"
        else
            echo -e "${RED}Error: pgloader not found. Please install it or build it first.${NC}"
            return 1
        fi
    fi
    
    return $?
}

# Setup SSH tunnel
setup_ssh_tunnel() {
    print_section "Setting Up SSH Tunnel"
    
    echo -e "${GREEN}Setting up SSH tunnel to production database...${NC}"
    echo -e "${YELLOW}Command: ssh -f -i ${SSH_KEY_FILE} -L ${LOCAL_TUNNEL_PORT}:${PROD_DB_HOST}:${PROD_DB_PORT} ${JUMPBOX_CONNECTION} -N${NC}"
    
    # Start SSH tunnel in background
    ssh -f -i "${SSH_KEY_FILE}" -L "${LOCAL_TUNNEL_PORT}":"${PROD_DB_HOST}":"${PROD_DB_PORT}" "${JUMPBOX_CONNECTION}" -N
    
    # Check if SSH tunnel was established successfully
    if [ $? -ne 0 ]; then
        echo -e "${RED}Failed to establish SSH tunnel. Exiting.${NC}"
        return 1
    fi
    
    echo -e "${GREEN}SSH tunnel established successfully.${NC}"
    return 0
}

# Cleanup resources
cleanup() {
    print_section "Cleanup"
    
    echo -e "${GREEN}Cleaning up resources...${NC}"
    
    # Remove temporary files
    if [ -f "temp_db_sync.load" ]; then
        rm temp_db_sync.load
        echo -e "${GREEN}Removed temporary configuration file.${NC}"
    fi
    
    # Find and kill the SSH tunnel process
    SSH_PID=$(ps aux | grep "${SSH_KEY_FILE}" | grep "${LOCAL_TUNNEL_PORT}" | grep -v grep | awk '{print $2}')
    if [ -n "$SSH_PID" ]; then
        echo -e "${GREEN}Killing SSH tunnel (PID: $SSH_PID)...${NC}"
        kill "$SSH_PID"
    else
        echo -e "${YELLOW}Warning: Could not find SSH tunnel process.${NC}"
    fi
    
    echo -e "${GREEN}Cleanup completed.${NC}"
}

# Main function
main() {
    # Clear screen and show title
    clear
    print_title
    
    # Read settings
    read_local_db_settings
    read_prod_db_settings
    
    # Display connection details and get confirmation
    display_connection_details
    
    # Choose sync type
    choose_sync_type
    
    # Create pgloader configuration
    create_pgloader_config
    
    # Setup SSH tunnel
    setup_ssh_tunnel
    if [ $? -ne 0 ]; then
        cleanup
        exit 1
    fi
    
    # Run pgloader
    run_pgloader
    sync_result=$?
    
    # Cleanup
    cleanup
    
    # Show final status
    if [ $sync_result -eq 0 ]; then
        print_section "Sync Completed Successfully"
        echo -e "${GREEN}Database sync completed successfully!${NC}"
        if [ "$SYNC_TYPE" = "schema" ]; then
            echo -e "${GREEN}Schema has been synced from production to local database.${NC}"
        else
            echo -e "${GREEN}Full database (schema + data) has been synced from production to local.${NC}"
        fi
    else
        print_section "Sync Failed"
        echo -e "${RED}Database sync failed with exit code $sync_result.${NC}"
        echo -e "${RED}Please check the error messages above for details.${NC}"
    fi
}

# Run the main function
main
