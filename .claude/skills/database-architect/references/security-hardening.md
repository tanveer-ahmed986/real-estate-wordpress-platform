# Security Hardening Reference

Database security best practices covering authentication, authorization, encryption, auditing, and injection prevention.

---

## Security Layers

```
Network Security          → Firewalls, VPN, private subnets, TLS
  ↓
Authentication            → Who can connect (credentials, certificates, SSO)
  ↓
Authorization             → What they can do (roles, permissions, RLS)
  ↓
Data Protection           → Encryption at rest + in transit, masking
  ↓
Auditing & Monitoring     → Who did what, when, alerting on anomalies
  ↓
Injection Prevention      → Parameterized queries, input validation
```

---

## Authentication

### Principle: No Shared Credentials
```sql
-- Create dedicated application user (not root/sa/postgres)
CREATE ROLE app_service LOGIN PASSWORD 'strong_random_password';

-- Create read-only user for reporting
CREATE ROLE reporting_reader LOGIN PASSWORD 'different_strong_password';
```

### Password Policies
- Minimum 16 characters, randomly generated
- Rotate credentials via secret manager (AWS Secrets Manager, HashiCorp Vault, Azure Key Vault)
- Never store passwords in application code, config files, or environment variables in plain text
- Use connection string from secret manager at runtime

### Certificate-Based Authentication (PostgreSQL)
```
# pg_hba.conf
hostssl  mydb  app_service  10.0.0.0/24  cert  clientcert=verify-full
```

### Windows/AD Authentication (SQL Server)
```sql
-- Preferred over SQL authentication
CREATE LOGIN [DOMAIN\AppService] FROM WINDOWS;
```

---

## Authorization (Least Privilege)

### Role-Based Access Control
```sql
-- PostgreSQL: Create roles with specific permissions
CREATE ROLE app_readwrite;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_readwrite;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_readwrite;
-- Do NOT grant: DROP, TRUNCATE, ALTER, CREATE

CREATE ROLE app_readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO app_readonly;

-- Assign roles to users
GRANT app_readwrite TO app_service;
GRANT app_readonly TO reporting_reader;
```

### Schema-Level Isolation
```sql
-- Revoke default public access
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO app_readwrite;
GRANT USAGE ON SCHEMA public TO app_readonly;
```

### Row-Level Security (PostgreSQL)
```sql
ALTER TABLE customer_data ENABLE ROW LEVEL SECURITY;

-- Tenant isolation policy
CREATE POLICY tenant_isolation ON customer_data
    USING (tenant_id = current_setting('app.tenant_id')::INT);

-- Force RLS for table owner too
ALTER TABLE customer_data FORCE ROW LEVEL SECURITY;
```

### Dynamic Data Masking (SQL Server)
```sql
-- Mask sensitive columns
ALTER TABLE customer
    ALTER COLUMN email ADD MASKED WITH (FUNCTION = 'email()');
ALTER TABLE customer
    ALTER COLUMN phone ADD MASKED WITH (FUNCTION = 'partial(0,"XXX-XXX-",4)');
ALTER TABLE customer
    ALTER COLUMN ssn ADD MASKED WITH (FUNCTION = 'default()');

-- Users without UNMASK permission see masked values
GRANT UNMASK TO trusted_analyst;
```

---

## Encryption

### At Rest

**Transparent Data Encryption (TDE)**
```sql
-- SQL Server
CREATE DATABASE ENCRYPTION KEY WITH ALGORITHM = AES_256
    ENCRYPTION BY SERVER CERTIFICATE MyServerCert;
ALTER DATABASE MyDatabase SET ENCRYPTION ON;

-- PostgreSQL: Use filesystem encryption (LUKS, dm-crypt) or pgcrypto for column-level
-- MySQL: InnoDB tablespace encryption
ALTER TABLE sensitive_data ENCRYPTION='Y';
```

**Column-Level Encryption**
```sql
-- PostgreSQL with pgcrypto
INSERT INTO user_account (ssn_encrypted)
VALUES (pgp_sym_encrypt('123-45-6789', 'encryption_key'));

SELECT pgp_sym_decrypt(ssn_encrypted, 'encryption_key') FROM user_account;
```

### In Transit
```
# PostgreSQL: Force SSL connections
# postgresql.conf
ssl = on
ssl_cert_file = '/path/to/server.crt'
ssl_key_file = '/path/to/server.key'
ssl_min_protocol_version = 'TLSv1.3'

# pg_hba.conf: Reject non-SSL connections
hostnossl  all  all  0.0.0.0/0  reject
hostssl    all  all  0.0.0.0/0  scram-sha-256
```

```
# MySQL: Require SSL
ALTER USER 'app_service'@'%' REQUIRE SSL;

# my.cnf
[mysqld]
require_secure_transport = ON
tls_version = TLSv1.3
```

### Always Encrypted (SQL Server)
```sql
-- Client-side encryption: data never visible to server
CREATE COLUMN MASTER KEY CMK1 WITH (
    KEY_STORE_PROVIDER_NAME = 'AZURE_KEY_VAULT',
    KEY_PATH = 'https://myvault.vault.azure.net/keys/CMK1/...'
);
CREATE COLUMN ENCRYPTION KEY CEK1 WITH VALUES (
    COLUMN_MASTER_KEY = CMK1,
    ALGORITHM = 'RSA_OAEP',
    ENCRYPTED_VALUE = 0x...
);
-- Column encrypted, database server cannot decrypt
ALTER TABLE patient ADD ssn VARCHAR(11)
    ENCRYPTED WITH (COLUMN_ENCRYPTION_KEY = CEK1,
                    ENCRYPTION_TYPE = DETERMINISTIC,
                    ALGORITHM = 'AEAD_AES_256_CBC_HMAC_SHA_256');
```

---

## SQL Injection Prevention

### Parameterized Queries (ALWAYS)
```python
# Python - CORRECT
cursor.execute("SELECT * FROM user_account WHERE email = %s", (email,))

# Python - WRONG (SQL injection vulnerable)
cursor.execute(f"SELECT * FROM user_account WHERE email = '{email}'")
```

```java
// Java - CORRECT
PreparedStatement ps = conn.prepareStatement("SELECT * FROM user_account WHERE email = ?");
ps.setString(1, email);

// Java - WRONG
Statement s = conn.createStatement();
s.executeQuery("SELECT * FROM user_account WHERE email = '" + email + "'");
```

```csharp
// C# - CORRECT
using var cmd = new SqlCommand("SELECT * FROM user_account WHERE email = @email", conn);
cmd.Parameters.AddWithValue("@email", email);

// C# - WRONG
var cmd = new SqlCommand($"SELECT * FROM user_account WHERE email = '{email}'", conn);
```

### Stored Procedures with Parameters
```sql
CREATE PROCEDURE get_user_by_email(p_email VARCHAR(255))
BEGIN
    SELECT user_account_id, email, first_name
    FROM user_account
    WHERE email = p_email;  -- Parameter, not concatenation
END;
```

### Input Validation (Defense in Depth)
- Validate at application boundary BEFORE it reaches the database
- Whitelist allowed characters for identifiers
- Use ORM parameterized queries (but still validate input)
- If dynamic table/column names are needed, validate against a whitelist of known names

---

## Auditing

### PostgreSQL
```sql
-- pgAudit extension
CREATE EXTENSION pgaudit;
-- postgresql.conf
-- pgaudit.log = 'read, write, ddl'
-- pgaudit.log_catalog = off

-- Manual audit trail via triggers
CREATE TABLE audit_log (
    audit_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    operation VARCHAR(10) NOT NULL,  -- INSERT, UPDATE, DELETE
    old_data JSONB,
    new_data JSONB,
    changed_by VARCHAR(100) NOT NULL DEFAULT current_user,
    changed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

### SQL Server
```sql
-- Server-level audit
CREATE SERVER AUDIT SecurityAudit
    TO FILE (FILEPATH = 'C:\Audits\', MAXSIZE = 100 MB);
ALTER SERVER AUDIT SecurityAudit WITH (STATE = ON);

CREATE SERVER AUDIT SPECIFICATION LoginAudit
    FOR SERVER AUDIT SecurityAudit
    ADD (FAILED_LOGIN_GROUP),
    ADD (SUCCESSFUL_LOGIN_GROUP);
ALTER SERVER AUDIT SPECIFICATION LoginAudit WITH (STATE = ON);

-- Database-level audit
CREATE DATABASE AUDIT SPECIFICATION DataAudit
    FOR SERVER AUDIT SecurityAudit
    ADD (SELECT, INSERT, UPDATE, DELETE ON SCHEMA::dbo BY public);
ALTER DATABASE AUDIT SPECIFICATION DataAudit WITH (STATE = ON);
```

### MySQL
```sql
-- General query log (expensive, use selectively)
SET GLOBAL general_log = 'ON';
SET GLOBAL log_output = 'TABLE';  -- mysql.general_log

-- Audit plugin (Enterprise or community plugins)
INSTALL PLUGIN audit_log SONAME 'audit_log.so';
```

---

## Compliance Checklist

| Requirement | Implementation |
|-------------|----------------|
| GDPR: Right to erasure | Soft delete + hard delete procedures, data anonymization |
| GDPR: Data minimization | Only store necessary data, retention policies |
| PCI-DSS: Cardholder data | Column-level encryption, access logging, no raw card storage |
| HIPAA: PHI protection | Encryption at rest + transit, audit trails, access controls |
| SOC 2: Access control | RBAC, MFA for admin, automated access reviews |
| SOX: Financial data | Immutable audit trails, separation of duties |
