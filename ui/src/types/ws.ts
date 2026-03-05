export type WsEnvelope<T = any> = { event: string; payload: T };

export type WsEvents =
  | { event: "HELLO"; payload: any }
  | { event: "AUTH_ACK"; payload: { userId: number; name?: string; color?: string } }
  | { event: "DOC_SNAPSHOT"; payload: { docId: number; code: string; revision: number; lockUserId: number | null; collaborators?: any[] } }
  | { event: "LOCK_ACQUIRED"; payload: { docId: number; userId: number } }
  | { event: "LOCK_RELEASED"; payload: { docId: number; userId: number } }
  | { event: "DOC_COLLABORATOR_JOIN"; payload: any }
  | { event: "DOC_COLLABORATOR_LEAVE"; payload: any }
  | { event: "DOC_EDIT_ACK"; payload: any }
  | { event: "DOC_EDIT_APPLIED"; payload: any }
  | { event: "LOCK_CHANGED"; payload: { docId: number; lockUserId: number | null } }
  | { event: "LOCK_ACQUIRED"; payload: { docId: number; userId: number } }
  | { event: "DOC_RENDER_FINISHED"; payload: { svg: string; svgPath: string; revision: number } }
  | { event: "ERROR"; payload: { code: string; message: string } };
