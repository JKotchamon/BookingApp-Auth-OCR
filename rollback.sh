#!/usr/bin/env bash

# =============================================================================
# rollback.sh — Reset Docker database to initial hbmsdb.sql state
# =============================================================================
# This script forcefully drops the current hbmsdb database inside the Docker
# container, creates a fresh one, and imports the base hbmsdb.sql file.
# 
# IMPORTANT: This will DELETE all existing data.

echo "WARNING: This will completely destroy the existing database and rollback to hbmsdb.sql."
read -p "Are you sure you want to continue? (y/n): " confirm

if [[ "$confirm" != "y" ]]; then
    echo "Rollback cancelled."
    exit 0
fi

echo "Dropping and recreating database..."
docker-compose exec -T db mysql -u root -pStrongDBPassword@ -e "DROP DATABASE IF EXISTS hbmsdb; CREATE DATABASE hbmsdb;"

echo "Importing initial hbmsdb.sql..."
docker-compose exec -T db mysql -u root -pStrongDBPassword@ hbmsdb < hbmsdb.sql

echo "✅ Rollback complete! The database is now at the exact state of hbmsdb.sql."
echo "If you need to apply the V*.sql migrations, you can restart the container or run migrate.sh."
