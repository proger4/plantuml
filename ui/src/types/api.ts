export type LoginResponse = { token: string; user: { id: number; name: string; color?: string } };

export type DocumentRow = {
  id: number;
  author_id: number;
  unique_slug: string | null;
  is_public: number;
  status: string;
  current_revision: number;
  code: string;
  is_deleted: number;
};

export type GetDocumentResponse = { ok: true; document: DocumentRow };
export type ListDocumentsResponse = { ok: true; documents: DocumentRow[] };

export type SaveRevisionResponse = {
  ok: boolean;
  docId: number;
  revision: number;
  revisionId: number;
  isValid: boolean;
  svgPath: string;
  svg: string;
};

export type JoinSessionResponse = {
  ok: boolean;
  sessionId: number;
  wsEnabled: boolean;
  wsUrl: string | null;
  docId: number;
};

export type StatsResponse = { ok: true; stats: { revisions: number; attempts: number } };

export type QuizRandomResponse = {
  ok: boolean;
  quiz: { id: number; formulation: string };
  beforeDoc: { id: number; code: string };
};

export type QuizSubmitResponse = {
  ok: boolean;
  score: number;
  isPass: boolean;
  attemptId: number;
};
