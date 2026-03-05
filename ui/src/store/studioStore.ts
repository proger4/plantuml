import { create } from "zustand";
import type { DocumentRow } from "../types/api";

type StudioState = {
  docId: number;
  docs: DocumentRow[];
  doc: DocumentRow | null;
  code: string;
  revision: number;
  svg: string;
  lockUserId: number | null;
  editorFontSize: number;
  quizId: number | null;

  setSnapshot: (p: { doc: DocumentRow; code: string; revision: number; lockUserId: number | null }) => void;
  setCode: (code: string) => void;
  setSvg: (svg: string) => void;
  setLock: (lockUserId: number | null) => void;
  setRevision: (rev: number) => void;
  setDocId: (docId: number) => void;
  setDocs: (docs: DocumentRow[]) => void;
  setQuizId: (quizId: number | null) => void;
};

export const useStudioStore = create<StudioState>((set) => ({
  docId: 1,
  docs: [],
  doc: null,
  code: "",
  revision: 0,
  svg: "",
  lockUserId: null,
  editorFontSize: 13,
  quizId: null,

  setSnapshot: ({ doc, code, revision, lockUserId }) => set({ doc, code, revision, lockUserId, docId: doc.id }),
  setCode: (code) => set({ code }),
  setSvg: (svg) => set({ svg }),
  setLock: (lockUserId) => set({ lockUserId }),
  setRevision: (rev) => set({ revision: rev }),
  setDocId: (docId) => set({ docId }),
  setDocs: (docs) => set({ docs }),
  setQuizId: (quizId) => set({ quizId }),
}));
