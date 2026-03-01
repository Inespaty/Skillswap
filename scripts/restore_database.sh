#!/bin/bash
#
# SkillSwap Database Restore Script
# Restores MySQL database from compressed backup
#
# Usage: ./restore_database.sh <backup_file>
# Example: ./restore_database.sh /var/backups/skillswap/skillswap_20231215_020000.sql.gz

# Configuration
DB_NAME="skillswap"
DB_USER="root"
DB_PASS=""  # Set this or use .my.cnf for security
LOG_FILE="/var/backups/skillswap/restore.log"

# Log function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Check if backup file is provided
if [ -z "$1" ]; then
    echo "Usage: $0 <backup_file>"
    echo "Example: $0 /var/backups/skillswap/skillswap_20231215_020000.sql.gz"
    exit 1
fi

BACKUP_FILE="$1"

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    log "✗ Error: Backup file not found: $BACKUP_FILE"
    exit 1
fi

log "Starting database restore from: $BACKUP_FILE"

# Verify backup integrity before restoring
log "Verifying backup integrity..."
gunzip -t "$BACKUP_FILE" 2>/dev/null
if [ $? -ne 0 ]; then
    log "✗ Error: Backup file is corrupted!"
    exit 1
fi
log "✓ Backup integrity verified"

# Confirm restore operation
echo ""
echo "⚠️  WARNING: This will REPLACE the current database!"
echo "Database: $DB_NAME"
echo "Backup file: $BACKUP_FILE"
echo ""
read -p "Are you sure you want to continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    log "Restore cancelled by user"
    exit 0
fi

# Create a safety backup of current database
SAFETY_BACKUP="/tmp/skillswap_pre_restore_$(date +%Y%m%d_%H%M%S).sql.gz"
log "Creating safety backup of current database..."
if [ -z "$DB_PASS" ]; then
    mysqldump -u"$DB_USER" "$DB_NAME" | gzip > "$SAFETY_BACKUP"
else
    mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$SAFETY_BACKUP"
fi

if [ $? -eq 0 ]; then
    log "✓ Safety backup created: $SAFETY_BACKUP"
else
    log "✗ Failed to create safety backup. Aborting restore."
    exit 1
fi

# Perform restore
log "Restoring database..."
if [ -z "$DB_PASS" ]; then
    gunzip < "$BACKUP_FILE" | mysql -u"$DB_USER" "$DB_NAME"
else
    gunzip < "$BACKUP_FILE" | mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"
fi

# Check if restore was successful
if [ $? -eq 0 ]; then
    log "✓ Database restore successful!"
    log "Safety backup kept at: $SAFETY_BACKUP"
    echo ""
    echo "✓ Restore completed successfully!"
    echo "Safety backup: $SAFETY_BACKUP"
    exit 0
else
    log "✗ Database restore failed!"
    echo ""
    echo "✗ Restore failed! Attempting to restore from safety backup..."
    
    # Attempt to restore from safety backup
    if [ -z "$DB_PASS" ]; then
        gunzip < "$SAFETY_BACKUP" | mysql -u"$DB_USER" "$DB_NAME"
    else
        gunzip < "$SAFETY_BACKUP" | mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"
    fi
    
    if [ $? -eq 0 ]; then
        log "✓ Rolled back to safety backup"
        echo "✓ Database rolled back to pre-restore state"
    else
        log "✗ CRITICAL: Failed to rollback! Manual intervention required!"
        echo "✗ CRITICAL ERROR: Failed to rollback! Check logs and restore manually."
    fi
    exit 1
fi
