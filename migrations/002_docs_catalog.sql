-- Migration 002: docs catalog table for importing project documentation into SQLite.
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS docs_catalog (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  path TEXT NOT NULL UNIQUE,
  kind TEXT NOT NULL,
  sha256 TEXT NOT NULL,
  content TEXT NOT NULL,
  ts_imported TEXT NOT NULL
);
