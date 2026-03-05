PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS document_favorites (
  user_id INTEGER NOT NULL,
  document_id INTEGER NOT NULL,
  ts_created TEXT NOT NULL,
  PRIMARY KEY (user_id, document_id),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_settings (
  user_id INTEGER PRIMARY KEY,
  editor_font_size INTEGER NOT NULL DEFAULT 13,
  preview_split REAL NOT NULL DEFAULT 0.5,
  updated_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
