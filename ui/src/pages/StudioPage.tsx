import React, { useCallback, useEffect, useRef, useState } from "react";
import { api } from "../store/api";
import { useAuthStore } from "../store/authStore";
import { useStudioStore } from "../store/studioStore";
import { connectCollab } from "../features/collaboration/wsClient";
import { Editor } from "../components/Editor";
import { Preview } from "../components/Preview";
import { TopBar } from "../components/layout/TopBar";
import { DocumentSidebar } from "../components/layout/DocumentSidebar";
import { Panel } from "../components/uikit/Panel";

type ToastTone = "ok" | "warn" | "error";

type Toast = {
  message: string;
  tone: ToastTone;
};

type Collaborator = {
  userId: number;
  name: string;
  color: string;
  caretLeft: number;
  caretRight: number;
};

type DragState =
  | { kind: "sidebar"; startX: number; base: number }
  | { kind: "preview"; startX: number; base: number }
  | { kind: "trace"; startY: number; base: number }
  | null;

export function StudioPage() {
  const token = useAuthStore((s) => s.token)!;
  const user = useAuthStore((s) => s.user)!;
  const logout = useAuthStore((s) => s.logout);

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
  const [wsEnabled, setWsEnabled] = useState(true);
  const [stats, setStats] = useState<{ revisions: number; attempts: number } | null>(null);
  const [trace, setTrace] = useState<string[]>([]);
  const [toast, setToast] = useState<Toast | null>(null);
  const [collaborators, setCollaborators] = useState<Record<number, Collaborator>>({});
  const [sidebarWidth, setSidebarWidth] = useState(240);
  const [previewSplit, setPreviewSplit] = useState(0.5);
  const [traceHeight, setTraceHeight] = useState(112);
  const [settingsReady, setSettingsReady] = useState(false);

  const toastTimer = useRef<number | null>(null);
  const settingsSaveTimer = useRef<number | null>(null);
  const wsRef = useRef<ReturnType<typeof connectCollab> | null>(null);
  const bootRef = useRef(false);
  const loadSeqRef = useRef(0);
  const codeRef = useRef("");
  const canEditRef = useRef(true);
  const lastSentCodeRef = useRef("");
  const dragRef = useRef<DragState>(null);

  const canEdit = lockUserId === null || lockUserId === user.id;
  canEditRef.current = canEdit;

  useEffect(() => {
    codeRef.current = code;
  }, [code]);

  const addTrace = useCallback((line: string) => {
    setTrace((prev) => [...prev.slice(-29), `${new Date().toLocaleTimeString()} ${line}`]);
  }, []);

  const showToast = useCallback((message: string, tone: ToastTone = "ok") => {
    setToast({ message, tone });
    if (toastTimer.current) {
      window.clearTimeout(toastTimer.current);
    }
    toastTimer.current = window.setTimeout(() => setToast(null), 2600);
  }, []);

  const closeWs = useCallback(() => {
    wsRef.current?.close();
    wsRef.current = null;
    setWsState(null);
  }, []);

  const loadDocument = useCallback(async (nextDocId: number) => {
    const seq = ++loadSeqRef.current;
    setCollaborators({});
    closeWs();

    const d = await api.getDocument(token, nextDocId);
    if (seq !== loadSeqRef.current) return;

    setSnapshot({
      doc: d.document,
      code: d.document.code,
      revision: d.document.current_revision,
      lockUserId: null,
    });
    setSvg(typeof d.preview?.svg === "string" ? d.preview.svg : "");
    codeRef.current = d.document.code;
    lastSentCodeRef.current = d.document.code;

    const s = await api.joinSession(token, nextDocId);
    if (seq !== loadSeqRef.current) return;

    if (!s.wsEnabled || !s.wsUrl) {
      setWsEnabled(false);
      addTrace("WS disabled by backend (/api/sessions wsEnabled=false)");
      return;
    }

    const conn = connectCollab(s.wsUrl, token, nextDocId, {
      onSnapshot: (p) => {
        addTrace(`IN DOC_SNAPSHOT rev=${p.revision}`);
        const nextCode = String(p.code ?? "");
        setCode(nextCode);
        codeRef.current = nextCode;
        lastSentCodeRef.current = nextCode;
        setRevision(p.revision);
        setLock(p.lockUserId ?? null);
        const entries: Record<number, Collaborator> = {};
        for (const c of p?.collaborators ?? []) {
          const id = Number(c?.userId ?? 0);
          if (id <= 0) continue;
          entries[id] = {
            userId: id,
            name: String(c?.name ?? `user-${id}`),
            color: String(c?.color ?? "#9ca3af"),
            caretLeft: Number(c?.caret?.left ?? 0),
            caretRight: Number(c?.caret?.right ?? 0),
          };
        }
        setCollaborators(entries);
      },
      onLockAcquired: (p) => {
        addTrace(`IN LOCK_ACQUIRED user=${p?.userId ?? "?"}`);
        setLock(Number(p?.userId ?? user.id));
      },
      onLockReleased: () => {
        addTrace("IN LOCK_RELEASED");
        setLock(null);
      },
      onEditAck: (p) => {
        addTrace(`IN DOC_EDIT_ACK ok=${p?.ok ? "true" : "false"} rev=${p?.revision ?? "?"}`);
        if (p?.ok === true) {
          lastSentCodeRef.current = codeRef.current;
          return;
        }

        const err = String(p?.error?.category ?? p?.error?.code ?? "edit_failed");
        addTrace(`IN DOC_EDIT_REJECT ${err}`);
        if (err !== "invalid_transition") {
          showToast(`Edit rejected: ${err}`, "warn");
        }
        void (async () => {
          try {
            const fresh = await api.getDocument(token, nextDocId);
            const freshCode = String(fresh.document.code ?? "");
            setCode(freshCode);
            codeRef.current = freshCode;
            lastSentCodeRef.current = freshCode;
            setRevision(fresh.document.current_revision);
            if (typeof fresh.preview?.svg === "string") {
              setSvg(fresh.preview.svg);
            }
          } catch {
            addTrace("SYNC failed after reject");
          }
        })();
      },
      onEditApplied: (p) => {
        const change = p?.change;
        addTrace(`IN DOC_EDIT_APPLIED rev=${p.revision ?? "?"}`);
        if (change?.type === "replace" && typeof change?.text === "string") {
          const prevCode = useStudioStore.getState().code;
          const l = Number(change?.range?.left ?? 0);
          const r = Number(change?.range?.right ?? 0);
          const safeL = Math.max(0, Math.min(prevCode.length, l));
          const safeR = Math.max(safeL, Math.min(prevCode.length, r));
          const nextCode = prevCode.slice(0, safeL) + change.text + prevCode.slice(safeR);
          setCode(nextCode);
          codeRef.current = nextCode;
          lastSentCodeRef.current = nextCode;
        }
        if (typeof p.revision === "number") setRevision(p.revision);
        const uid = Number(p?.userId ?? 0);
        if (uid > 0) {
          setCollaborators((prev) => ({
            ...prev,
            [uid]: {
              userId: uid,
              name: String(p?.name ?? prev[uid]?.name ?? `user-${uid}`),
              color: String(p?.color ?? prev[uid]?.color ?? "#9ca3af"),
              caretLeft: Number(p?.caret?.left ?? prev[uid]?.caretLeft ?? 0),
              caretRight: Number(p?.caret?.right ?? prev[uid]?.caretRight ?? 0),
            },
          }));
        }
      },
      onCollaboratorJoin: (p) => {
        const uid = Number(p?.userId ?? 0);
        if (uid <= 0) return;
        addTrace(`IN DOC_COLLABORATOR_JOIN user=${uid}`);
        setCollaborators((prev) => ({
          ...prev,
          [uid]: {
            userId: uid,
            name: String(p?.name ?? `user-${uid}`),
            color: String(p?.color ?? "#9ca3af"),
            caretLeft: Number(p?.caret?.left ?? 0),
            caretRight: Number(p?.caret?.right ?? 0),
          },
        }));
      },
      onCollaboratorLeave: (p) => {
        const uid = Number(p?.userId ?? 0);
        if (uid <= 0) return;
        addTrace(`IN DOC_COLLABORATOR_LEAVE user=${uid}`);
        setCollaborators((prev) => {
          const next = { ...prev };
          delete next[uid];
          return next;
        });
      },
      onRenderFinished: (p) => {
        addTrace(`IN DOC_RENDER_FINISHED rev=${p.revision ?? "?"}`);
        if (typeof p.svg === "string") setSvg(p.svg);
        if (typeof p.revision === "number") setRevision(p.revision);
      },
      onLockChanged: (p) => {
        addTrace(`IN LOCK_CHANGED lock=${p.lockUserId ?? "none"}`);
        setLock(p.lockUserId ?? null);
      },
      onError: (p) => {
        const code = p?.code ?? "unknown";
        addTrace(`IN ERROR ${code}`);
        if (code === "locked_by_other") {
          showToast("Документ уже заблокирован другим пользователем", "warn");
        } else {
          showToast(`WS error: ${code}`, "error");
        }
      },
    });

    wsRef.current = conn;
    setWsState(conn);
    try {
      await conn.ready;
      if (seq !== loadSeqRef.current) {
        conn.close();
        return;
      }
      setWsEnabled(true);
      addTrace(`WS connected ${s.wsUrl}`);
    } catch {
      if (seq !== loadSeqRef.current) return;
      setWsEnabled(false);
      addTrace(`WS connect failed ${s.wsUrl}`);
      showToast("WS недоступен, активен только HTTP save", "warn");
    }
  }, [addTrace, closeWs, setCode, setLock, setRevision, setSnapshot, setSvg, showToast, token, user.id]);

  useEffect(() => {
    if (bootRef.current) return;
    bootRef.current = true;

    void (async () => {
      const [list, userStats, settingsResult] = await Promise.all([
        api.listDocuments(token, "personal"),
        api.getStats(token),
        api.getSettings(token).catch(() => null),
      ]);
      setDocs(list.documents);
      setStats(userStats.stats);
      setSidebarWidth(Number(settingsResult?.settings?.sidebar_width ?? 240));
      setPreviewSplit(Number(settingsResult?.settings?.preview_split ?? 0.5));
      setTraceHeight(Number(settingsResult?.settings?.trace_height ?? 112));
      setSettingsReady(true);

      const qp = new URLSearchParams(window.location.search);
      const requestedDocId = Number(qp.get("doc") ?? 0);
      const firstDocId = requestedDocId > 0 ? requestedDocId : (list.documents[0]?.id ?? 1);
      setDocId(firstDocId);
      await loadDocument(firstDocId);
    })();

    return () => {
      closeWs();
      if (toastTimer.current) window.clearTimeout(toastTimer.current);
      if (settingsSaveTimer.current) window.clearTimeout(settingsSaveTimer.current);
    };
  }, [closeWs, loadDocument, setDocId, setDocs, token]);

  useEffect(() => {
    if (!settingsReady) return;
    if (settingsSaveTimer.current) window.clearTimeout(settingsSaveTimer.current);
    settingsSaveTimer.current = window.setTimeout(() => {
      void api.updateSettings(token, {
        editor_font_size: 13,
        preview_split: previewSplit,
        sidebar_width: sidebarWidth,
        trace_height: traceHeight,
      });
    }, 400);
    return () => {
      if (settingsSaveTimer.current) window.clearTimeout(settingsSaveTimer.current);
    };
  }, [previewSplit, settingsReady, sidebarWidth, token, traceHeight]);

  useEffect(() => {
    const onMove = (e: MouseEvent) => {
      const drag = dragRef.current;
      if (!drag) return;
      if (drag.kind === "sidebar") {
        const next = Math.max(180, Math.min(420, drag.base + (e.clientX - drag.startX)));
        setSidebarWidth(Math.round(next));
        return;
      }
      if (drag.kind === "preview") {
        const px = drag.base + (e.clientX - drag.startX);
        const width = window.innerWidth - sidebarWidth - 36;
        const ratio = width > 0 ? px / width : 0.5;
        setPreviewSplit(Math.max(0.2, Math.min(0.8, ratio)));
        return;
      }
      const next = Math.max(80, Math.min(280, drag.base - (e.clientY - drag.startY)));
      setTraceHeight(Math.round(next));
    };
    const onUp = () => {
      dragRef.current = null;
    };
    window.addEventListener("mousemove", onMove);
    window.addEventListener("mouseup", onUp);
    return () => {
      window.removeEventListener("mousemove", onMove);
      window.removeEventListener("mouseup", onUp);
    };
  }, [sidebarWidth]);

  const saveNow = useCallback(async (nextCode: string) => {
    if (!canEditRef.current) {
      addTrace("HTTP SAVE blocked (locked_by_other)");
      showToast("Сохранение заблокировано: lock у другого пользователя", "warn");
      return;
    }

    try {
      const r = await api.saveRevision(token, docId, nextCode);
      if (r.ok) {
        setSvg(r.svg);
        setRevision(r.revision);
        lastSentCodeRef.current = nextCode;
        addTrace(`HTTP SAVE /revisions rev=${r.revision}`);
      }
    } catch {
      addTrace("HTTP SAVE rejected");
      showToast("Сохранение отклонено (проверьте lock)", "warn");
    }
  }, [addTrace, docId, setRevision, setSvg, showToast, token]);

  const onChange = useCallback((next: string, caretLeft: number, caretRight: number) => {
    const prev = codeRef.current;
    setCode(next);
    codeRef.current = next;
    lastSentCodeRef.current = next;

    if (wsRef.current && wsRef.current.isOpen() && canEditRef.current) {
      wsRef.current.sendReplaceRange(0, prev.length, next, caretLeft, caretRight);
      addTrace(`OUT DOC_EDIT replace(0..${prev.length})`);
    }
  }, [addTrace, setCode]);

  const toastClass =
    toast?.tone === "error"
      ? "border-red-300 bg-red-50 text-red-700"
      : toast?.tone === "warn"
      ? "border-amber-300 bg-amber-50 text-amber-700"
      : "border-accent-300 bg-accent-50 text-accent-700";

  const editorPct = `${Math.round(previewSplit * 100)}%`;

  return (
    <div className="h-screen p-3">
      <Panel className="flex h-full flex-col overflow-hidden">
        <TopBar
          docId={docId}
          revision={revision}
          lockUserId={lockUserId}
          meId={user.id}
          userName={user.name}
          wsEnabled={wsEnabled}
          onLogout={() => logout()}
          onShare={async () => {
            const published = await api.publishDocument(token, docId);
            const sharedId = published.document.id;
            const sharedUrl = `${window.location.origin}${window.location.pathname}?doc=${sharedId}`;
            try {
              await navigator.clipboard.writeText(sharedUrl);
              addTrace(`SHARE copied ${sharedUrl}`);
              showToast("Ссылка скопирована", "ok");
            } catch {
              addTrace(`SHARE ready ${sharedUrl}`);
              showToast(sharedUrl, "warn");
            }
          }}
          onNewDocument={async () => {
            const created = await api.createDocument(token, "@startuml\n@enduml");
            const nextId = created.document.id;
            const list = await api.listDocuments(token, "personal");
            setDocs(list.documents);
            setDocId(nextId);
            const nextUrl = `${window.location.pathname}?doc=${nextId}`;
            window.history.replaceState(null, "", nextUrl);
            await loadDocument(nextId);
            showToast(`Создан документ #${nextId}`, "ok");
          }}
          onRandomQuiz={async () => {
            const q = await api.startQuiz(token);
            setQuizId(q.quiz.id);
            setCode(q.beforeDoc.code);
            showToast(`Quiz #${q.quiz.id} загружен`, "ok");
          }}
          onRender={() => {
            if (wsState?.isOpen() && canEdit) {
              wsState.requestRender();
              addTrace("OUT DOC_RENDER_REQUEST");
            }
          }}
          onSave={() => saveNow(code)}
        />

        <div className="min-h-0 flex-1">
          <div className="grid h-full min-h-0" style={{ gridTemplateColumns: `${sidebarWidth}px 6px minmax(0,1fr)` }}>
            <DocumentSidebar
              docs={docs}
              activeId={docId}
              onPick={async (id) => {
                if (id === docId) return;
                setDocId(id);
                const nextUrl = `${window.location.pathname}?doc=${id}`;
                window.history.replaceState(null, "", nextUrl);
                await loadDocument(id);
              }}
            />

            <div
              className="cursor-col-resize border-l border-r border-black/10 bg-black/5 hover:bg-black/10"
              onMouseDown={(e) => {
                e.preventDefault();
                dragRef.current = { kind: "sidebar", startX: e.clientX, base: sidebarWidth };
              }}
            />

            <div className="flex min-h-0 flex-col">
              <div className="grid min-h-0 flex-1" style={{ gridTemplateColumns: `minmax(0,${editorPct}) 6px minmax(0,1fr)` }}>
                <Editor code={code} onChange={onChange} readOnly={!canEdit} />
                <div
                  className="cursor-col-resize border-l border-r border-black/10 bg-black/5 hover:bg-black/10"
                  onMouseDown={(e) => {
                    e.preventDefault();
                    dragRef.current = {
                      kind: "preview",
                      startX: e.clientX,
                      base: previewSplit * (window.innerWidth - sidebarWidth - 36),
                    };
                  }}
                />
                <Preview svg={svg} />
              </div>

              <div
                className="h-1 cursor-row-resize border-t border-black/10 bg-black/10 hover:bg-black/20"
                onMouseDown={(e) => {
                  e.preventDefault();
                  dragRef.current = { kind: "trace", startY: e.clientY, base: traceHeight };
                }}
              />

              <div className="overflow-auto border-t border-black/10 bg-black/95 px-3 py-2 font-mono text-[11px] text-green-300" style={{ height: traceHeight }}>
                {trace.length === 0 ? <div>WS trace is empty</div> : trace.map((line, i) => <div key={`${line}-${i}`}>{line}</div>)}
              </div>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-4 border-t border-black/10 px-4 py-2 text-xs text-black/60">
          <span>stats revisions: {stats?.revisions ?? 0}</span>
          <span>stats attempts: {stats?.attempts ?? 0}</span>
          <button
            className="rounded bg-black/5 px-2 py-1 hover:bg-black/10"
            onClick={async () => {
              if (!quizId) return;
              const result = await api.submitQuiz(token, quizId, code);
              showToast(`Quiz score: ${result.score}`, result.score === 100 ? "ok" : "warn");
            }}
          >
            Submit quiz
          </button>
          {!wsEnabled && <span className="text-amber-700">WS offline: collab disabled, HTTP save active</span>}
          {!canEdit && <span className="text-red-600">Документ заблокирован другим пользователем</span>}
          <div className="ml-auto flex items-center gap-2">
            {Object.values(collaborators)
              .filter((c) => c.userId !== user.id)
              .map((c) => (
                <span key={c.userId} className="rounded-full border border-black/10 px-2 py-1" style={{ background: `${c.color}22`, color: c.color }}>
                  {c.name} [{c.caretLeft}:{c.caretRight}]
                </span>
              ))}
          </div>
        </div>
      </Panel>

      {toast && (
        <div className={`pointer-events-none fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-xl border px-4 py-2 text-sm shadow-panel ${toastClass}`}>
          {toast.message}
        </div>
      )}
    </div>
  );
}
