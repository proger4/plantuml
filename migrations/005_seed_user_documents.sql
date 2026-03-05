PRAGMA foreign_keys = ON;

-- Seed baseline personal documents for each seeded user (ivan/vladimir/anna).
INSERT INTO documents(id, author_id, unique_slug, is_public, status, current_revision, code, is_deleted)
VALUES
  (
    11,
    1,
    'ivan-collab-seed',
    1,
    'valid',
    1,
    '@startuml
title Ivan Seed
Alice -> Bob: Ivan doc
@enduml',
    0
  ),
  (
    12,
    2,
    'vladimir-collab-seed',
    1,
    'valid',
    1,
    '@startuml
title Vladimir Seed
Bob -> Alice: Vladimir doc
@enduml',
    0
  ),
  (
    13,
    3,
    'anna-collab-seed',
    1,
    'valid',
    1,
    '@startuml
title Anna Seed
Anna -> Team: Anna doc
@enduml',
    0
  )
ON CONFLICT(id) DO UPDATE SET
  author_id = excluded.author_id,
  unique_slug = excluded.unique_slug,
  is_public = excluded.is_public,
  status = excluded.status,
  current_revision = excluded.current_revision,
  code = excluded.code,
  is_deleted = excluded.is_deleted;

INSERT INTO revisions(id, document_id, revision, ts_created, code, is_valid, ts_rendered, svg_path)
VALUES
  (
    11,
    11,
    1,
    datetime('now'),
    (SELECT code FROM documents WHERE id = 11),
    1,
    datetime('now'),
    'var/renders/doc_11_rev_1.svg'
  ),
  (
    12,
    12,
    1,
    datetime('now'),
    (SELECT code FROM documents WHERE id = 12),
    1,
    datetime('now'),
    'var/renders/doc_12_rev_1.svg'
  ),
  (
    13,
    13,
    1,
    datetime('now'),
    (SELECT code FROM documents WHERE id = 13),
    1,
    datetime('now'),
    'var/renders/doc_13_rev_1.svg'
  )
ON CONFLICT(id) DO UPDATE SET
  document_id = excluded.document_id,
  revision = excluded.revision,
  code = excluded.code,
  is_valid = excluded.is_valid,
  ts_rendered = excluded.ts_rendered,
  svg_path = excluded.svg_path;

INSERT INTO sessions(document_id, locked_by_user_id, lock_ts)
VALUES (11, NULL, NULL), (12, NULL, NULL), (13, NULL, NULL)
ON CONFLICT(document_id) DO NOTHING;
