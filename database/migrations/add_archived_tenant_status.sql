-- Add 'archived' to tenants.status ENUM
ALTER TABLE tenants MODIFY COLUMN status ENUM('active','inactive','suspended','archived') NOT NULL DEFAULT 'active';
