# Database Management & Migrations

This document outlines how the database is managed, initialized, and reset in the local Docker environment for the HBMS project.

## 1. How Docker Initializes the Database

When you first start the database container using `docker-compose up`, the official MySQL Docker image automatically looks for files mapped to the `/docker-entrypoint-initdb.d/` directory.

In our `docker-compose.yml`, we map our initialization files to run in alphabetical order:
1. `00_hbmsdb.sql` - The base database structure and seed data.
2. `01_V001...sql` through `07_V007...sql` - Our versioned schema migrations.

**Important:** These scripts *only* execute if the database volume (`db_data`) is completely empty. If the database was already created in a previous run, Docker will skip the initialization scripts, even if you added new ones!

## 2. Docker Commands: `down` vs `down -v`

It is crucial to understand the difference between stopping containers and destroying data volumes.

* **`docker-compose down`**
  * Stops all running containers and removes them.
  * **Data is safe:** Your database files are preserved inside the `db_data` volume. When you run `docker-compose up` again, all your test users and bookings will still be there.

* **`docker-compose down -v`**
  * Stops containers **AND** completely deletes the `db_data` volume.
  * **Data is destroyed:** Your database is entirely wiped clean. 
  * **Why use this?** If you've messed up the schema, or you need Docker to re-run all the initialization and migration scripts from scratch, running `down -v` followed by `up` is the best way to get a perfectly clean slate.

## 3. Using the `rollback.sh` Script

If you don't want to bring down your entire Docker stack, but you still need to forcefully wipe the database, you can use the `rollback.sh` script located in the root folder.

```bash
./rollback.sh
```

**What it does:**
1. Connects to the running MySQL container.
2. Drops the entire `hbmsdb` database.
3. Recreates the database and imports *only* the base `hbmsdb.sql` file.

**Note:** This script drops you back to the very beginning. It does *not* apply the `VXXX` migrations automatically. If you want the migrations applied as well, it is highly recommended to use `docker-compose down -v && docker-compose up` instead.

## 4. Adding New Migrations

If you add a new column or table to the application:
1. Do not edit past migration files (e.g., `V002`).
2. Create a new file in the `migrations/` folder (e.g., `V008__your_feature_name.sql`).
3. **CRITICAL:** Update `docker-compose.yml` to map your new file into the `docker-entrypoint-initdb.d/` directory. If you forget this step, your migration will never run when the database is rebuilt!
4. To test your migration locally, run `docker-compose down -v` and `docker-compose up` to verify it builds the schema correctly.
