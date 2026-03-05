import type { WsEnvelope } from "../../types/ws";

export type WsHandlers = {
  onSnapshot: (p: any) => void;
  onLockAcquired: (p: any) => void;
  onLockReleased: (p: any) => void;
  onEditAck: (p: any) => void;
  onEditApplied: (p: any) => void;
  onCollaboratorJoin: (p: any) => void;
  onCollaboratorLeave: (p: any) => void;
  onRenderFinished: (p: any) => void;
  onLockChanged: (p: any) => void;
  onViewState: (p: any) => void;
  onError: (p: any) => void;
};

export function connectCollab(wsUrl: string, token: string, documentId: number, handlers: WsHandlers) {
  const ws = new WebSocket(wsUrl);
  const queue: string[] = [];
  let open = false;
  let closed = false;
  let resolveReady: (() => void) | null = null;
  let rejectReady: ((reason?: unknown) => void) | null = null;
  const ready = new Promise<void>((resolve, reject) => {
    resolveReady = resolve;
    rejectReady = reject;
  });

  const sendRaw = (payload: string) => {
    if (closed) return;
    if (ws.readyState === WebSocket.OPEN) {
      ws.send(payload);
      return;
    }
    if (ws.readyState === WebSocket.CONNECTING) {
      queue.push(payload);
    }
  };

  ws.onopen = () => {
    open = true;
    sendRaw(JSON.stringify({ event: "AUTH", payload: { token } }));
    sendRaw(JSON.stringify({ event: "JOIN", payload: { documentId } }));
    while (queue.length > 0) {
      const payload = queue.shift();
      if (!payload) break;
      ws.send(payload);
    }
    resolveReady?.();
  };

  ws.onmessage = (ev) => {
    const msg = JSON.parse(ev.data) as WsEnvelope;
    switch (msg.event) {
      case "DOC_SNAPSHOT":
        handlers.onSnapshot(msg.payload);
        break;
      case "LOCK_ACQUIRED":
        handlers.onLockAcquired(msg.payload);
        break;
      case "LOCK_RELEASED":
        handlers.onLockReleased(msg.payload);
        break;
      case "DOC_EDIT_APPLIED":
        handlers.onEditApplied(msg.payload);
        break;
      case "DOC_COLLABORATOR_JOIN":
        handlers.onCollaboratorJoin(msg.payload);
        break;
      case "DOC_COLLABORATOR_LEAVE":
        handlers.onCollaboratorLeave(msg.payload);
        break;
      case "DOC_EDIT_ACK":
        handlers.onEditAck(msg.payload);
        break;
      case "DOC_RENDER_FINISHED":
        handlers.onRenderFinished(msg.payload);
        break;
      case "LOCK_CHANGED":
        handlers.onLockChanged(msg.payload);
        break;
      case "DOC_VIEW_STATE":
        handlers.onViewState(msg.payload);
        break;
      case "ERROR":
        handlers.onError(msg.payload);
        break;
    }
  };

  ws.onerror = () => {
    if (!open) {
      rejectReady?.(new Error("ws_connect_failed"));
    }
  };

  ws.onclose = () => {
    closed = true;
    if (!open) {
      rejectReady?.(new Error("ws_closed_before_open"));
    }
  };

  return {
    ws,
    ready,
    isOpen() {
      return ws.readyState === WebSocket.OPEN;
    },
    acquireLock() {
      sendRaw(JSON.stringify({ event: "DOC_COLLABORATOR_ACTION", payload: { action: "acquire_lock" } }));
    },
    releaseLock() {
      sendRaw(JSON.stringify({ event: "DOC_COLLABORATOR_ACTION", payload: { action: "release_lock" } }));
    },
    /**
     * MVP edit strategy (deliberately naive):
     * - send full replace of whole document each time.
     *
     * TODO (strict, you asked for this):
     * - compute minimal change from textarea events:
     *   - selectionStart/End
     *   - inserted text
     *   - backspace/delete mapping to replace with empty text
     * - send ChangeType insert/replace with precise range.
     */
    sendReplaceRange(left: number, right: number, text: string, caretLeft: number, caretRight: number) {
      sendRaw(
        JSON.stringify({
          event: "DOC_EDIT",
          payload: {
            change: { type: "replace", range: { left, right }, text },
            caret: { left: caretLeft, right: caretRight },
          },
        })
      );
    },
    requestRender() {
      sendRaw(JSON.stringify({ event: "DOC_RENDER_REQUEST", payload: {} }));
    },
    sendViewState(payload: { view?: { zoom: number; nx: number; ny: number }; cursor?: { x: number; y: number; visible: boolean } }) {
      sendRaw(JSON.stringify({ event: "DOC_VIEW_STATE", payload }));
    },
    close() {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ event: "LEAVE", payload: {} }));
      }
      ws.close();
      closed = true;
    },
  };
}
