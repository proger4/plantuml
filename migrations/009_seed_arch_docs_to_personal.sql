PRAGMA foreign_keys = ON;

-- Make architecture docs visible in "Personal" lists.
-- Source: docs_catalog(path, content)
-- Target: documents + revisions + sessions per user.

INSERT INTO documents(author_id, unique_slug, is_public, status, current_revision, code, is_deleted)
SELECT
  u.id AS author_id,
  'arch-' || replace(replace(replace(dc.path, 'docs/', ''), '.puml', ''), '/', '-') || '-u' || u.id AS unique_slug,
  0 AS is_public,
  'valid' AS status,
  1 AS current_revision,
  dc.content AS code,
  0 AS is_deleted
FROM users u
CROSS JOIN docs_catalog dc
WHERE dc.path LIKE 'docs/%.puml'
  AND NOT EXISTS (
    SELECT 1
    FROM documents d
    WHERE d.author_id = u.id
      AND d.unique_slug = 'arch-' || replace(replace(replace(dc.path, 'docs/', ''), '.puml', ''), '/', '-') || '-u' || u.id
      AND d.is_deleted = 0
  );

INSERT INTO revisions(document_id, revision, ts_created, code, is_valid, ts_rendered, svg_path)
SELECT
  d.id,
  1,
  datetime('now'),
  d.code,
  1,
  datetime('now'),
  NULL
FROM documents d
WHERE d.unique_slug LIKE 'arch-%-u%'
  AND d.is_deleted = 0
  AND NOT EXISTS (
    SELECT 1
    FROM revisions r
    WHERE r.document_id = d.id
      AND r.revision = 1
  );

INSERT INTO sessions(document_id, locked_by_user_id, lock_ts)
SELECT d.id, NULL, NULL
FROM documents d
WHERE d.unique_slug LIKE 'arch-%-u%'
  AND d.is_deleted = 0
  AND NOT EXISTS (
    SELECT 1
    FROM sessions s
    WHERE s.document_id = d.id
  );
