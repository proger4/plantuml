-- Migration 001: init schema only.
-- SQLite docs: https://www.sqlite.org/lang.html

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version TEXT PRIMARY KEY,
  ts_applied TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  author_id INTEGER NOT NULL,
  unique_slug TEXT,
  is_public INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'valid',
  current_revision INTEGER NOT NULL DEFAULT 1,
  code TEXT NOT NULL,
  is_deleted INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY(author_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS revisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  document_id INTEGER NOT NULL,
  revision INTEGER NOT NULL,
  ts_created TEXT NOT NULL,
  code TEXT NOT NULL,
  is_valid INTEGER NOT NULL DEFAULT 1,
  ts_rendered TEXT,
  svg_path TEXT,
  FOREIGN KEY(document_id) REFERENCES documents(id),
  UNIQUE(document_id, revision)
);

CREATE TABLE IF NOT EXISTS sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  document_id INTEGER NOT NULL UNIQUE,
  locked_by_user_id INTEGER,
  lock_ts TEXT,
  FOREIGN KEY(document_id) REFERENCES documents(id),
  FOREIGN KEY(locked_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS quizzes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  formulation TEXT NOT NULL,
  before_document_id INTEGER NOT NULL,
  required_document_id INTEGER NOT NULL,
  FOREIGN KEY(before_document_id) REFERENCES documents(id),
  FOREIGN KEY(required_document_id) REFERENCES documents(id)
);

CREATE TABLE IF NOT EXISTS attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  quiz_id INTEGER NOT NULL,
  tryout_revision_id INTEGER NOT NULL,
  ts_created TEXT NOT NULL,
  score INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(quiz_id) REFERENCES quizzes(id),
  FOREIGN KEY(tryout_revision_id) REFERENCES revisions(id)
);
