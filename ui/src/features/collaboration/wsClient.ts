import type { WsEnvelope } from "../../types/ws";

export type WsHandlers = {
  onSnapshot: (p: any) => void;
  onEditApplied: (p: any) => void;
  onRenderFinished: (p: any) => void;
  onLockChanged: (p: any) => void;
  onError: (p: any) => void;
};

export function connectCollab(wsUrl: string, token: string, documentId: number, handlers: WsHandlers) {
  const ws = new WebSocket(wsUrl);

  ws.onopen = () => {
    ws.send(JSON.stringify({ event: "AUTH", payload: { token } }));
    ws.send(JSON.stringify({ event: "JOIN", payload: { documentId } }));
  };

  ws.onmessage = (ev) => {
    const msg = JSON.parse(ev.data) as WsEnvelope;
    switch (msg.event) {
      case "DOC_SNAPSHOT":
        handlers.onSnapshot(msg.payload);
        break;
      case "DOC_EDIT_APPLIED":
        handlers.onEditApplied(msg.payload);
        break;
      case "DOC_RENDER_FINISHED":
        handlers.onRenderFinished(msg.payload);
        break;
      case "LOCK_CHANGED":
        handlers.onLockChanged(msg.payload);
        break;
      case "ERROR":
        handlers.onError(msg.payload);
        break;
    }
  };

  return {
    ws,
    acquireLock() {
      ws.send(JSON.stringify({ event: "DOC_COLLABORATOR_ACTION", payload: { action: "acquire_lock" } }));
    },
    releaseLock() {
      ws.send(JSON.stringify({ event: "DOC_COLLABORATOR_ACTION", payload: { action: "release_lock" } }));
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
    sendFullReplace(code: string, caretLeft: number, caretRight: number) {
      ws.send(
        JSON.stringify({
          event: "DOC_EDIT",
          payload: {
            change: { type: "replace", range: { left: 0, right: code.length }, text: code },
            caret: { left: caretLeft, right: caretRight },
          },
        })
      );
    },
    requestRender() {
      ws.send(JSON.stringify({ event: "DOC_RENDER_REQUEST", payload: {} }));
    },
    close() {
      ws.send(JSON.stringify({ event: "LEAVE", payload: {} }));
      ws.close();
    },
  };
}
