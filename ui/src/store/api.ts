import type {
  GetDocumentResponse,
  JoinSessionResponse,
  ListDocumentsResponse,
  LoginResponse,
  QuizRandomResponse,
  QuizSubmitResponse,
  SaveRevisionResponse,
  StatsResponse,
} from "../types/api";

const API_BASE = import.meta.env.VITE_API_BASE ?? "http://127.0.0.1:8000";

async function json<T>(res: Response): Promise<T> {
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return (await res.json()) as T;
}

const authHeaders = (token: string) => ({ Authorization: `Bearer ${token}` });

export const api = {
  async login(name: string, password: string): Promise<LoginResponse> {
    const res = await fetch(`${API_BASE}/api/auth/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, password }),
    });
    return json<LoginResponse>(res);
  },

  async getDocument(token: string, id: number): Promise<GetDocumentResponse> {
    const res = await fetch(`${API_BASE}/api/documents/${id}`, {
      headers: authHeaders(token),
    });
    return json<GetDocumentResponse>(res);
  },

  async listDocuments(token: string, filter: "personal" | "favorites" | "public"): Promise<ListDocumentsResponse> {
    const res = await fetch(`${API_BASE}/api/me/documents?filter=${filter}`, {
      headers: authHeaders(token),
    });
    return json<ListDocumentsResponse>(res);
  },

  async getStats(token: string): Promise<StatsResponse> {
    const res = await fetch(`${API_BASE}/api/me/stats`, { headers: authHeaders(token) });
    return json<StatsResponse>(res);
  },

  async joinSession(token: string, documentId: number): Promise<JoinSessionResponse> {
    const res = await fetch(`${API_BASE}/api/sessions`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...authHeaders(token) },
      body: JSON.stringify({ documentId }),
    });
    return json<JoinSessionResponse>(res);
  },

  async saveRevision(token: string, documentId: number, code: string): Promise<SaveRevisionResponse> {
    const res = await fetch(`${API_BASE}/api/documents/${documentId}/revisions`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...authHeaders(token) },
      body: JSON.stringify({ code }),
    });
    return json<SaveRevisionResponse>(res);
  },

  async createDocument(token: string, code: string): Promise<GetDocumentResponse> {
    const res = await fetch(`${API_BASE}/api/documents`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...authHeaders(token) },
      body: JSON.stringify({ code, isPublic: false }),
    });
    return json<GetDocumentResponse>(res);
  },

  async startQuiz(token: string): Promise<QuizRandomResponse> {
    const res = await fetch(`${API_BASE}/api/quizzes/random`, {
      method: "POST",
      headers: authHeaders(token),
    });
    return json<QuizRandomResponse>(res);
  },

  async submitQuiz(token: string, quizId: number, code: string): Promise<QuizSubmitResponse> {
    const res = await fetch(`${API_BASE}/api/quizzes/${quizId}/submit`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...authHeaders(token) },
      body: JSON.stringify({ code }),
    });
    return json<QuizSubmitResponse>(res);
  },
};
