#!/bin/bash
#
# SkillSwap Database Backup Script
# Performs automated MySQL database backups with compression and retention
#
# Usage: ./backup_database.sh
# Cron: 0 2 * * * /path/to/scripts/backup_database.sh

# Configuration
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/skillswap"
DB_NAME="skillswap"
DB_USER="root"
DB_PASS=""  # Set this or use .my.cnf for security
RETENTION_DAYS=30
LOG_FILE="$BACKUP_DIR/backup.log"

# Email notification (optional)
ADMIN_EMAIL="admin@skillswap.com"
SEND_EMAIL=false  # Set to true to enable email notifications

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Log function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "Starting database backup..."

# Perform backup
BACKUP_FILE="$BACKUP_DIR/skillswap_$DATE.sql.gz"

if [ -z "$DB_PASS" ]; then
    # Use .my.cnf or no password
    mysqldump -u"$DB_USER" "$DB_NAME" | gzip > "$BACKUP_FILE"
else
    mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_FILE"
fi

# Check if backup was successful
if [ $? -eq 0 ] && [ -f "$BACKUP_FILE" ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    log "✓ Backup successful: $BACKUP_FILE ($BACKUP_SIZE)"
    
    # Verify backup integrity
    gunzip -t "$BACKUP_FILE" 2>/dev/null
    if [ $? -eq 0 ]; then
        log "✓ Backup integrity verified"
    else
        log "✗ WARNING: Backup file may be corrupted!"
        if [ "$SEND_EMAIL" = true ]; then
            echo "Backup integrity check failed for $BACKUP_FILE" | mail -s "SkillSwap Backup Warning" "$ADMIN_EMAIL"
        fi
    fi
    
    # Clean up old backups
    log "Cleaning up backups older than $RETENTION_DAYS days..."
    find "$BACKUP_DIR" -name "skillswap_*.sql.gz" -mtime +$RETENTION_DAYS -delete
    REMAINING=$(find "$BACKUP_DIR" -name "skillswap_*.sql.gz" | wc -l)
    log "✓ Cleanup complete. $REMAINING backup(s) remaining."
    
else
    log "✗ Backup failed!"
    if [ "$SEND_EMAIL" = true ]; then
        echo "Database backup failed at $(date)" | mail -s "SkillSwap Backup Failure" "$ADMIN_EMAIL"
    fi
    exit 1
fi

log "Backup process completed."
exit 0
