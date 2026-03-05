import React, { useEffect, useMemo, useRef, useState } from "react";
import { api } from "../store/api";
import { useAuthStore } from "../store/authStore";
import { useStudioStore } from "../store/studioStore";
import { connectCollab } from "../features/collaboration/wsClient";
import { Editor } from "../components/Editor";
import { Preview } from "../components/Preview";
import { TopBar } from "../components/layout/TopBar";
import { DocumentSidebar } from "../components/layout/DocumentSidebar";
import { Panel } from "../components/uikit/Panel";

export function StudioPage() {
  const token = useAuthStore((s) => s.token)!;
  const user = useAuthStore((s) => s.user)!;

  const docId = useStudioStore((s) => s.docId);
  const docs = useStudioStore((s) => s.docs);
  const code = useStudioStore((s) => s.code);
  const svg = useStudioStore((s) => s.svg);
  const revision = useStudioStore((s) => s.revision);
  const lockUserId = useStudioStore((s) => s.lockUserId);
  const quizId = useStudioStore((s) => s.quizId);

  const setSnapshot = useStudioStore((s) => s.setSnapshot);
  const setCode = useStudioStore((s) => s.setCode);
  const setSvg = useStudioStore((s) => s.setSvg);
  const setLock = useStudioStore((s) => s.setLock);
  const setRevision = useStudioStore((s) => s.setRevision);
  const setDocId = useStudioStore((s) => s.setDocId);
  const setDocs = useStudioStore((s) => s.setDocs);
  const setQuizId = useStudioStore((s) => s.setQuizId);

  const [wsState, setWsState] = useState<ReturnType<typeof connectCollab> | null>(null);
  const [stats, setStats] = useState<{ revisions: number; attempts: number } | null>(null);
  const debounce = useRef<number | null>(null);

  const loadDocument = useMemo(
    () => async (nextDocId: number) => {
      const d = await api.getDocument(token, nextDocId);
      setSnapshot({
        doc: d.document,
        code: d.document.code,
        revision: d.document.current_revision,
        lockUserId: null,
      });

      wsState?.close();

      const s = await api.joinSession(token, nextDocId);
      const conn = connectCollab(s.wsUrl, token, nextDocId, {
        onSnapshot: (p) => {
          setCode(p.code);
          setRevision(p.revision);
          setLock(p.lockUserId ?? null);
        },
        onEditApplied: (p) => {
          if (typeof p.revision === "number") setRevision(p.revision);
        },
        onRenderFinished: (p) => {
          if (typeof p.svg === "string") setSvg(p.svg);
          if (typeof p.revision === "number") setRevision(p.revision);
        },
        onLockChanged: (p) => setLock(p.lockUserId ?? null),
        onError: (p) => console.error("WS error", p),
      });

      setWsState(conn);
      conn.acquireLock();
    },
    [setCode, setLock, setRevision, setSnapshot, setSvg, token, wsState]
  );

  useEffect(() => {
    (async () => {
      const [list, userStats] = await Promise.all([api.listDocuments(token, "personal"), api.getStats(token)]);
      setDocs(list.documents);
      setStats(userStats.stats);
      const firstDocId = list.documents[0]?.id ?? 1;
      setDocId(firstDocId);
      await loadDocument(firstDocId);
    })();

    return () => {
      wsState?.close();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const canEdit = lockUserId === null || lockUserId === user.id;

  const saveNow = useMemo(
    () => async (nextCode: string) => {
      const r = await api.saveRevision(token, docId, nextCode);
      if (r.ok) {
        setSvg(r.svg);
        setRevision(r.revision);
      }
    },
    [token, docId, setSvg, setRevision]
  );

  const onChange = useMemo(
    () => (next: string, caretLeft: number, caretRight: number) => {
      setCode(next);

      if (wsState && canEdit) {
        wsState.sendFullReplace(next, caretLeft, caretRight);
      }

      if (debounce.current) window.clearTimeout(debounce.current);
      debounce.current = window.setTimeout(async () => {
        await saveNow(next);
      }, 500);
    },
    [wsState, canEdit, setCode, saveNow]
  );

  return (
    <div className="h-screen p-3">
      <Panel className="flex h-full flex-col overflow-hidden">
        <TopBar
          docId={docId}
          revision={revision}
          lockUserId={lockUserId}
          meId={user.id}
          userName={user.name}
          onRender={() => wsState?.requestRender()}
          onSave={() => saveNow(code)}
        />

        <div className="grid h-full grid-cols-[240px_1fr]">
          <DocumentSidebar
            docs={docs}
            activeId={docId}
            onPick={async (id) => {
              if (id === docId) return;
              setDocId(id);
              await loadDocument(id);
            }}
          />

          <div className="grid h-full grid-cols-2">
            <Editor code={code} onChange={onChange} />
            <Preview svg={svg} />
          </div>
        </div>

        <div className="flex items-center gap-4 border-t border-black/10 px-4 py-2 text-xs text-black/60">
          <span>stats revisions: {stats?.revisions ?? 0}</span>
          <span>stats attempts: {stats?.attempts ?? 0}</span>
          <button
            className="rounded bg-black/5 px-2 py-1 hover:bg-black/10"
            onClick={async () => {
              const q = await api.startQuiz(token);
              setQuizId(q.quiz.id);
              setCode(q.beforeDoc.code);
            }}
          >
            Random quiz
          </button>
          <button
            className="rounded bg-black/5 px-2 py-1 hover:bg-black/10"
            onClick={async () => {
              if (!quizId) return;
              const result = await api.submitQuiz(token, quizId, code);
              window.alert(`Quiz score: ${result.score}`);
            }}
          >
            Submit quiz
          </button>
          {!canEdit && <span className="text-red-600">Документ заблокирован другим пользователем</span>}
        </div>
      </Panel>
    </div>
  );
}
