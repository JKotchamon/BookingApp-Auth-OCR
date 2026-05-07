# Database Migrations

This folder contains idempotent, versioned SQL migrations for HBMS.  
Every migration is safe to re-run (`IF NOT EXISTS` throughout).

---

## Migration Index

| File | Depends on | What it adds |
|---|---|---|
| `V001__oauth_user_columns.sql` | `hbms_backup.sql` (original schema) | `auth_method`, `oauth_provider`, `oauth_id`, `DateOfBirth`, `ProfilePhoto` on `tbluser`; creates `tbl_oauth_links`, `tbl_password_set_tokens`, `tbl_email_verifications` |
| `V002__kyc_schema.sql` | V001 | `kyc_status`, `kyc_verified_at`, `kyc_expiry_date` on `tbluser`; creates `tbl_kyc_records`, `tbl_kyc_audit_log`, `tbl_booking_risk_flags` |

---

## Environment Contexts

### A — Local Development (Docker)

`docker-compose.yml` mounts the migration files into `docker-entrypoint-initdb.d/` with numeric prefixes so MySQL applies them **automatically in order** the first time the container starts:

```
00_hbmsdb.sql        ← base schema (hbms_backup.sql equivalent)
01_V001__oauth...    ← OAuth columns + tables
02_V002__kyc...      ← KYC columns + tables
```

**You do not need to run anything manually in local dev.**  
If you need a clean reset: `docker compose down -v && docker compose up -d`

---

### B — Production (Bare Server — no Docker)

> Production runs MySQL directly on a Linux VM without Docker due to storage/memory constraints.  
> Migrations must be applied manually using `migrate.sh`.

#### First-time setup (new production database)

```bash
# 1. Load the base schema
mysql -u root -p hbmsdb < hbmsdb.sql

# 2. Run all migrations in order
bash migrations/migrate.sh -u root -d hbmsdb
# (will prompt for password)
```

#### Applying new migrations to an existing production database

When a new `V00N__*.sql` file is added to this repo, apply only that file:

```bash
# Dry-run first — see what would be applied
bash migrations/migrate.sh -u root -d hbmsdb --dry-run

# Then apply for real
bash migrations/migrate.sh -u root -d hbmsdb
```

To apply from a specific version onwards (e.g. if V001 was already applied):

```bash
bash migrations/migrate.sh -u root -d hbmsdb -f V002
```

#### Full options

```
bash migrations/migrate.sh [options]

  -h HOST      MySQL host      (default: localhost)
  -P PORT      MySQL port      (default: 3306)
  -u USER      MySQL user      (default: root)
  -p PASS      MySQL password  (prompt if omitted)
  -d DATABASE  Database name   (default: hbmsdb)
  -f FROM      Start from this version prefix (e.g. V002)
  --dry-run    Show what would be applied without running it
```

---

## Rules for writing new migrations

1. **Never edit a past migration.** Create a new `V00N__description.sql` instead.
2. **Always idempotent.** Use `CREATE TABLE IF NOT EXISTS` and `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`.
3. **State your dependency** at the top of the file: `-- Depends on: V00N`.
4. **Naming:** `V<NNN>__<snake_case_description>.sql` (3-digit zero-padded number).
5. **After adding a migration**, update `docker-compose.yml` to add a new mount entry for it.
