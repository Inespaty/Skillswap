# SkillSwap Backup & Recovery Guide

## Overview

This document outlines the backup and recovery procedures for the SkillSwap platform to ensure data integrity and business continuity.

## Automated Backup System

### Database Backups

**Script Location**: `scripts/backup_database.sh`

**Features**:
- Automated daily backups
- Gzip compression to save space
- 30-day retention policy
- Integrity verification
- Optional email notifications
- Detailed logging

**Setup Instructions**:

1. **Configure the script**:
   ```bash
   cd /path/to/skillswap
   chmod +x scripts/backup_database.sh
   nano scripts/backup_database.sh
   ```

2. **Set database credentials**:
   ```bash
   DB_USER="your_db_user"
   DB_PASS="your_db_password"  # Or use .my.cnf for security
   BACKUP_DIR="/var/backups/skillswap"
   ```

3. **Create backup directory**:
   ```bash
   sudo mkdir -p /var/backups/skillswap
   sudo chown www-data:www-data /var/backups/skillswap
   ```

4. **Schedule with cron**:
   ```bash
   sudo crontab -e
   ```
   
   Add this line for daily backups at 2 AM:
   ```cron
   0 2 * * * /path/to/skillswap/scripts/backup_database.sh
   ```

5. **Test the backup**:
   ```bash
   ./scripts/backup_database.sh
   ```

### File Backups

**What to backup**:
- User uploads: `uploads/` directory
- Configuration files: `.env`, `backend/db.php`
- Application code (use Git repository)

**Recommended approach**:
```bash
# Backup uploads directory
tar -czf /var/backups/skillswap/uploads_$(date +%Y%m%d).tar.gz uploads/

# Keep last 30 days
find /var/backups/skillswap -name "uploads_*.tar.gz" -mtime +30 -delete
```

## Recovery Procedures

### Database Recovery

**Script Location**: `scripts/restore_database.sh`

**Features**:
- Safety backup before restore
- Integrity verification
- Automatic rollback on failure
- Confirmation prompts

**Usage**:

1. **List available backups**:
   ```bash
   ls -lh /var/backups/skillswap/skillswap_*.sql.gz
   ```

2. **Restore from backup**:
   ```bash
   chmod +x scripts/restore_database.sh
   ./scripts/restore_database.sh /var/backups/skillswap/skillswap_20231215_020000.sql.gz
   ```

3. **Verify restoration**:
   ```bash
   mysql -u root -p skillswap -e "SELECT COUNT(*) FROM users;"
   ```

### File Recovery

**Restore uploads**:
```bash
cd /path/to/skillswap
tar -xzf /var/backups/skillswap/uploads_20231215.tar.gz
```

## Disaster Recovery Plan

### Scenario 1: Database Corruption

1. Stop the web server
2. Identify the latest valid backup
3. Run restore script
4. Verify data integrity
5. Restart web server
6. Monitor application logs

### Scenario 2: Server Failure

1. Provision new server
2. Install dependencies (PHP, MySQL, Apache/Nginx)
3. Clone Git repository
4. Restore database from backup
5. Restore uploads directory
6. Configure environment variables
7. Test application
8. Update DNS if needed

### Scenario 3: Accidental Data Deletion

1. Identify the deletion time
2. Find the most recent backup before deletion
3. Extract specific data from backup:
   ```bash
   gunzip < backup.sql.gz | mysql -u root -p temp_db
   # Extract specific records
   mysqldump temp_db table_name --where="id IN (1,2,3)" > recovery.sql
   # Import to production
   mysql -u root -p skillswap < recovery.sql
   ```

## Backup Monitoring

### Check Backup Status

```bash
# View backup log
tail -f /var/backups/skillswap/backup.log

# List recent backups
ls -lht /var/backups/skillswap/skillswap_*.sql.gz | head -10

# Check disk space
df -h /var/backups/skillswap
```

### Verify Backup Integrity

```bash
# Test latest backup
LATEST=$(ls -t /var/backups/skillswap/skillswap_*.sql.gz | head -1)
gunzip -t "$LATEST" && echo "✓ Backup OK" || echo "✗ Backup corrupted"
```

## Offsite Backup (Recommended)

### Using rsync to Remote Server

```bash
# Sync backups to remote server
rsync -avz --delete /var/backups/skillswap/ user@backup-server:/backups/skillswap/
```

### Using Cloud Storage (AWS S3 Example)

```bash
# Install AWS CLI
sudo apt-get install awscli

# Configure credentials
aws configure

# Sync to S3
aws s3 sync /var/backups/skillswap/ s3://skillswap-backups/
```

## Testing Recovery Procedures

### Monthly Recovery Test

1. Create a test database
2. Restore latest backup to test database
3. Verify data integrity
4. Document any issues
5. Update procedures if needed

**Test script**:
```bash
#!/bin/bash
TEST_DB="skillswap_test"
LATEST_BACKUP=$(ls -t /var/backups/skillswap/skillswap_*.sql.gz | head -1)

# Create test database
mysql -u root -p -e "DROP DATABASE IF EXISTS $TEST_DB; CREATE DATABASE $TEST_DB;"

# Restore to test database
gunzip < "$LATEST_BACKUP" | mysql -u root -p "$TEST_DB"

# Verify
USER_COUNT=$(mysql -u root -p -se "SELECT COUNT(*) FROM $TEST_DB.users;")
echo "Test restore: $USER_COUNT users found"

# Cleanup
mysql -u root -p -e "DROP DATABASE $TEST_DB;"
```

## Retention Policy

- **Daily backups**: Keep for 30 days
- **Weekly backups**: Keep for 3 months (1st of each week)
- **Monthly backups**: Keep for 1 year (1st of each month)
- **Yearly backups**: Keep indefinitely

## Security Considerations

1. **Encrypt backups** for sensitive data:
   ```bash
   mysqldump skillswap | gzip | openssl enc -aes-256-cbc -salt -out backup.sql.gz.enc
   ```

2. **Secure backup storage**:
   - Restrict directory permissions: `chmod 700 /var/backups/skillswap`
   - Use separate backup user with limited privileges
   - Store credentials in `.my.cnf` instead of scripts

3. **Audit backup access**:
   - Log all backup and restore operations
   - Monitor backup directory access
   - Alert on failed backups

## Contact Information

**System Administrator**: admin@skillswap.com  
**Emergency Contact**: +1-XXX-XXX-XXXX  
**Backup Server**: backup-server.skillswap.com

## Revision History

| Date | Version | Changes |
|------|---------|---------|
| 2023-12-15 | 1.0 | Initial backup procedures documented |
