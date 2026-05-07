#!/usr/bin/env bash
# =============================================================================
# migrate.sh — Run HBMS database migrations on a bare server (no Docker)
# =============================================================================
# Usage:
#   bash migrations/migrate.sh [options]
#
# Options:
#   -h HOST      MySQL host      (default: localhost)
#   -P PORT      MySQL port      (default: 3306)
#   -u USER      MySQL user      (default: root)
#   -p PASS      MySQL password  (prompt if omitted)
#   -d DATABASE  Database name   (default: hbmsdb)
#   -f FROM      Start from migration file prefix, e.g. V002 (optional)
#   --dry-run    Print the SQL files that would be applied, but don't run them
#
# Examples:
#   # Run all migrations interactively (will prompt for password)
#   bash migrations/migrate.sh
#
#   # Run with credentials supplied (e.g. in a CI/CD pipeline)
#   bash migrations/migrate.sh -h 127.0.0.1 -u hbmsuser -p 'Secret123' -d hbmsdb
#
#   # Only apply V002 onwards (V001 already applied on this server)
#   bash migrations/migrate.sh -f V002
#
#   # Dry run — see what would be applied
#   bash migrations/migrate.sh --dry-run
# =============================================================================

set -euo pipefail

# ── Defaults ──────────────────────────────────────────────────────────────────
DB_HOST="localhost"
DB_PORT="3306"
DB_USER="root"
DB_PASS=""
DB_NAME="hbmsdb"
FROM_PREFIX=""
DRY_RUN=false
PASS_PROVIDED=false

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Argument parsing ───────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
  case "$1" in
    -h) DB_HOST="$2";   shift 2 ;;
    -P) DB_PORT="$2";   shift 2 ;;
    -u) DB_USER="$2";   shift 2 ;;
    -p) DB_PASS="$2";   PASS_PROVIDED=true; shift 2 ;;
    -d) DB_NAME="$2";   shift 2 ;;
    -f) FROM_PREFIX="$2"; shift 2 ;;
    --dry-run) DRY_RUN=true; shift ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# ── Password prompt if not supplied ───────────────────────────────────────────
if [[ "$PASS_PROVIDED" == false ]]; then
  read -rsp "MySQL password for ${DB_USER}@${DB_HOST}: " DB_PASS
  echo
fi

# ── Build mysql base command ───────────────────────────────────────────────────
MYSQL_CMD="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USER} -p${DB_PASS} ${DB_NAME}"

# ── Collect migration files in order ──────────────────────────────────────────
mapfile -t FILES < <(ls "${SCRIPT_DIR}"/V*.sql 2>/dev/null | sort)

if [[ ${#FILES[@]} -eq 0 ]]; then
  echo "No migration files found in ${SCRIPT_DIR}/"
  exit 0
fi

# ── Apply migrations ──────────────────────────────────────────────────────────
APPLIED=0
SKIPPED=0

echo ""
echo "========================================"
echo " HBMS Database Migration Runner"
echo " Target: ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
echo " Mode:   $([ "$DRY_RUN" == true ] && echo 'DRY RUN' || echo 'LIVE')"
echo "========================================"
echo ""

for FILE in "${FILES[@]}"; do
  BASENAME="$(basename "$FILE")"

  # Skip files before FROM_PREFIX if specified
  if [[ -n "$FROM_PREFIX" && "$BASENAME" < "${FROM_PREFIX}" ]]; then
    echo "  [SKIP] $BASENAME  (before --from ${FROM_PREFIX})"
    ((SKIPPED++))
    continue
  fi

  if [[ "$DRY_RUN" == true ]]; then
    echo "  [WOULD APPLY] $BASENAME"
    ((APPLIED++))
  else
    echo "  [APPLYING] $BASENAME ..."
    if $MYSQL_CMD < "$FILE"; then
      echo "  [OK]      $BASENAME"
      ((APPLIED++))
    else
      echo ""
      echo "  [ERROR] Failed on $BASENAME. Stopping."
      echo "  Fix the issue and re-run with: -f $(echo "$BASENAME" | grep -o '^V[0-9]\+')"
      exit 1
    fi
  fi
done

echo ""
echo "========================================"
echo " Done. Applied: ${APPLIED}  Skipped: ${SKIPPED}"
echo "========================================"
echo ""
