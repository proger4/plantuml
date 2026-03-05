PRAGMA foreign_keys = ON;

ALTER TABLE users ADD COLUMN color TEXT;

UPDATE users
SET color = CASE name
  WHEN 'ivan' THEN '#ef4444'
  WHEN 'vladimir' THEN '#22c55e'
  WHEN 'anna' THEN '#3b82f6'
  ELSE '#f59e0b'
END
WHERE color IS NULL OR color = '';
