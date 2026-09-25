# Backup & Recovery Reference

Backup strategies, disaster recovery planning, RPO/RTO targets, and high availability.

---

## RPO/RTO Framework

| Metric | Definition | Question |
|--------|------------|----------|
| **RPO** (Recovery Point Objective) | Maximum acceptable data loss | "How much data can we afford to lose?" |
| **RTO** (Recovery Time Objective) | Maximum acceptable downtime | "How long can we be offline?" |

### Target Guidelines

| Tier | RPO | RTO | Strategy | Cost |
|------|-----|-----|----------|------|
| **Critical** (payment, auth) | 0 (zero data loss) | < 1 minute | Synchronous replication + auto-failover | $$$$$ |
| **High** (core business) | < 5 minutes | < 15 minutes | Async replication + warm standby | $$$$ |
| **Standard** (internal apps) | < 1 hour | < 4 hours | Frequent backups + manual failover | $$$ |
| **Low** (dev, staging) | < 24 hours | < 24 hours | Daily backups | $ |

---

## Backup Types

### Full Backup
- Complete copy of entire database
- Largest size, longest duration
- Self-contained restoration
- Schedule: Weekly for most systems

### Incremental Backup
- Only data changed since LAST backup (full or incremental)
- Smallest size, fastest backup
- Restoration: full + chain of incrementals
- Schedule: Every few hours

### Differential Backup
- All data changed since last FULL backup
- Medium size, medium duration
- Restoration: full + latest differential
- Schedule: Daily

### Continuous Archiving (WAL/Binlog)
- Stream transaction logs to archive
- Point-in-time recovery (PITR) capability
- Best RPO (near-zero data loss)

---

## PostgreSQL Backup

### pg_dump (Logical Backup)
```bash
# Full database dump (custom format, compressed)
pg_dump -Fc -f /backups/mydb_$(date +%Y%m%d_%H%M%S).dump mydb

# Specific tables
pg_dump -Fc -t user_account -t purchase_order -f /backups/critical_tables.dump mydb

# Schema only (no data)
pg_dump --schema-only -f /backups/schema.sql mydb

# Restore
pg_restore -d mydb /backups/mydb_20250101_120000.dump

# Parallel restore (faster on multi-core)
pg_restore -j 4 -d mydb /backups/mydb_20250101_120000.dump
```

### Continuous Archiving + PITR
```
# postgresql.conf
archive_mode = on
archive_command = 'cp %p /archive/%f'
wal_level = replica

# Take base backup
pg_basebackup -D /backups/base -Ft -z -P

# Restore to specific point in time
# recovery.conf / postgresql.auto.conf
restore_command = 'cp /archive/%f %p'
recovery_target_time = '2025-06-15 14:30:00'
recovery_target_action = 'promote'
```

### pgBackRest (Production-Grade)
```bash
# Configure: /etc/pgbackrest/pgbackrest.conf
# [global]
# repo1-path=/var/lib/pgbackrest
# repo1-retention-full=4
# repo1-cipher-type=aes-256-cbc
# repo1-cipher-pass=<passphrase>

# Full backup
pgbackrest --stanza=mydb backup --type=full

# Incremental backup
pgbackrest --stanza=mydb backup --type=incr

# Point-in-time restore
pgbackrest --stanza=mydb restore --target='2025-06-15 14:30:00' --target-action=promote
```

---

## MySQL Backup

### mysqldump (Logical)
```bash
# Full database with single transaction (consistent snapshot)
mysqldump --single-transaction --routines --triggers --events \
  -u backup_user -p mydb > /backups/mydb_$(date +%Y%m%d).sql

# Compressed
mysqldump --single-transaction mydb | gzip > /backups/mydb_$(date +%Y%m%d).sql.gz
```

### Percona XtraBackup (Physical, Online)
```bash
# Full backup (no locks for InnoDB)
xtrabackup --backup --target-dir=/backups/full/

# Incremental backup
xtrabackup --backup --target-dir=/backups/incr1/ \
  --incremental-basedir=/backups/full/

# Prepare and restore
xtrabackup --prepare --target-dir=/backups/full/
xtrabackup --prepare --target-dir=/backups/full/ \
  --incremental-dir=/backups/incr1/
```

### Binary Log PITR
```bash
# Enable binary logging: my.cnf
# log_bin = /var/log/mysql/mysql-bin
# expire_logs_days = 14

# Point-in-time recovery
mysqlbinlog --stop-datetime='2025-06-15 14:30:00' \
  /var/log/mysql/mysql-bin.000042 | mysql -u root mydb
```

---

## SQL Server Backup

```sql
-- Full backup
BACKUP DATABASE MyDB TO DISK = 'C:\Backups\MyDB_Full.bak'
    WITH COMPRESSION, CHECKSUM, STATS = 10;

-- Differential backup
BACKUP DATABASE MyDB TO DISK = 'C:\Backups\MyDB_Diff.bak'
    WITH DIFFERENTIAL, COMPRESSION, CHECKSUM;

-- Transaction log backup (for PITR)
BACKUP LOG MyDB TO DISK = 'C:\Backups\MyDB_Log.trn'
    WITH COMPRESSION, CHECKSUM;

-- Restore with PITR
RESTORE DATABASE MyDB FROM DISK = 'C:\Backups\MyDB_Full.bak' WITH NORECOVERY;
RESTORE DATABASE MyDB FROM DISK = 'C:\Backups\MyDB_Diff.bak' WITH NORECOVERY;
RESTORE LOG MyDB FROM DISK = 'C:\Backups\MyDB_Log.trn'
    WITH RECOVERY, STOPAT = '2025-06-15T14:30:00';
```

---

## 3-2-1 Backup Rule

```
3 copies of data (production + 2 backups)
2 different storage media (local disk + cloud/tape)
1 off-site copy (different geographic region)
```

### Implementation
```
Production DB → Local backup (fast restore)
             → Cloud storage (S3, Azure Blob, GCS) for off-site
             → Optional: Cross-region replication for DR
```

---

## High Availability Architectures

### Active-Passive (Warm Standby)
```
Primary ──WAL──→ Standby (receives WAL, ready to promote)
  │                │
  └── VIP/DNS ─────┘  (failover switches VIP to standby)

RTO: 30s - 5min (time to detect failure + promote)
RPO: 0 (synchronous) or seconds (async)
```

### Active-Active (Multi-Primary)
```
Client ──→ Load Balancer ──→ Node 1 (R/W)
                          ──→ Node 2 (R/W)
                          ──→ Node 3 (R/W)

Tools: Citus, CockroachDB, MySQL Group Replication, Galera Cluster
RTO: 0 (automatic failover)
RPO: 0 (synchronous replication between nodes)
```

---

## Disaster Recovery Testing

### Test Schedule
| Test Type | Frequency | Duration |
|-----------|-----------|----------|
| Backup verification (restore to staging) | Weekly | Automated |
| Failover drill (switch to standby) | Monthly | 1-2 hours |
| Full DR simulation (region failure) | Quarterly | 4-8 hours |
| Backup integrity check (CHECKSUM/VERIFY) | Daily | Automated |

### Test Checklist
- [ ] Backup completes within backup window
- [ ] Restore completes within RTO target
- [ ] Data integrity verified post-restore (row counts, checksums)
- [ ] Application connects and functions correctly against restored DB
- [ ] PITR tested to specific timestamp
- [ ] Failover/failback procedure documented and tested
- [ ] Alerts fire correctly when backup fails
