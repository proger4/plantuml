PRAGMA foreign_keys = ON;

ALTER TABLE user_settings ADD COLUMN sidebar_width INTEGER NOT NULL DEFAULT 240;
ALTER TABLE user_settings ADD COLUMN trace_height INTEGER NOT NULL DEFAULT 112;
