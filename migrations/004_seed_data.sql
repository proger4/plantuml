-- Migration 004: seed baseline users/docs and import docs/ catalog content
PRAGMA foreign_keys = ON;

INSERT INTO users(id, name, password_hash) VALUES
  (1, 'ivan', '$2y$12$6v5XjPjqjFVbrWVCO/taSuzjjjqu2NblMULDBsA/H5VQgr313YDdK'),
  (2, 'vladimir', '$2y$12$6v5XjPjqjFVbrWVCO/taSuzjjjqu2NblMULDBsA/H5VQgr313YDdK'),
  (3, 'anna', '$2y$12$6v5XjPjqjFVbrWVCO/taSuzjjjqu2NblMULDBsA/H5VQgr313YDdK')
ON CONFLICT(id) DO UPDATE SET name=excluded.name, password_hash=excluded.password_hash;

INSERT INTO documents(id, author_id, unique_slug, is_public, status, current_revision, code, is_deleted)
VALUES (1, 1, 'seed-doc', 1, 'valid', 1, '@startuml
title PlantUML Studio Seed
actor User
User -> Studio: edit diagram
Studio -> Renderer: render SVG
Renderer --> Studio: svg payload
@enduml', 0)
ON CONFLICT(id) DO UPDATE SET author_id=excluded.author_id, unique_slug=excluded.unique_slug, is_public=excluded.is_public, status=excluded.status, current_revision=excluded.current_revision, code=excluded.code, is_deleted=excluded.is_deleted;

INSERT INTO revisions(id, document_id, revision, ts_created, code, is_valid, ts_rendered, svg_path)
VALUES (1, 1, 1, datetime('now'), '@startuml
title PlantUML Studio Seed
actor User
User -> Studio: edit diagram
Studio -> Renderer: render SVG
Renderer --> Studio: svg payload
@enduml', 1, datetime('now'), 'var/renders/doc_1_rev_1.svg')
ON CONFLICT(id) DO UPDATE SET document_id=excluded.document_id, revision=excluded.revision, code=excluded.code, is_valid=excluded.is_valid, ts_rendered=excluded.ts_rendered, svg_path=excluded.svg_path;

INSERT INTO sessions(document_id, locked_by_user_id, lock_ts) VALUES (1, NULL, NULL) ON CONFLICT(document_id) DO NOTHING;
INSERT INTO quizzes(id, formulation, before_document_id, required_document_id) VALUES (1, 'Измени title на PlantUML Studio Ready.', 1, 1) ON CONFLICT(id) DO UPDATE SET formulation=excluded.formulation, before_document_id=excluded.before_document_id, required_document_id=excluded.required_document_id;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/api.puml', 'puml', '7e7c88a3ae3a946b2553f64287cd08a7abf97c137c98639fb9db137f894c77b7', '@startuml
title Sheet 4/8 — HTTP API Surface (Controllers -> UseCases)

skinparam componentStyle rectangle

package "Symfony Controllers" {
  [AuthController] as HC1
  [MeController] as HC2
  [DocumentController] as HC3
  [SessionController] as HC4
  [QuizController] as HC5
}

package "UseCases" {
  [LoginUser] as U1
  [LogoutUser] as U1b
  [CreateDocument] as U2a
  [DeleteDocument] as U2b
  [ToggleFavorite] as U2c
  [PublishDocument] as U3
  [SaveRevision] as U6
  [JoinSession] as U4
  [TakeRandomQuiz] as U7
  [SubmitQuiz] as U7b
  [ListPersonalDocs] as U8a
  [GetStats] as U8b
}

HC1 --> U1
HC1 --> U1b
HC2 --> U8a
HC2 --> U8b
HC3 --> U2a
HC3 --> U2b
HC3 --> U2c
HC3 --> U3
HC3 --> U6
HC4 --> U4
HC5 --> U7
HC5 --> U7b

note right of HC3
Symfony attributes (пример):
#[Route(''/api/documents'', methods:[''POST''])]
#[IsGranted(''DOC_CREATE'')]
#[Route(''/api/documents/{id}'', methods:[''PUT''])]
#[IsGranted(''DOC_EDIT'', subject:''document'')]
Use Voters for access rules.
end note

note bottom
Numbered endpoints (compact):

A1  POST   /api/auth/login
A2  POST   /api/auth/logout

A3  GET    /api/me/settings
A4  PUT    /api/me/settings

A5  GET    /api/me/documents?filter=personal|favorites|public
A6  GET    /api/me/stats

A7  POST   /api/documents                { code, isPublic? }
A8  GET    /api/documents/{id}
A9  PUT    /api/documents/{id}           { code, isPublic? }  (metadata only; editing is WS)
A10 DELETE /api/documents/{id}
A11 POST   /api/documents/{id}/favorite
A12 DELETE /api/documents/{id}/favorite
A13 POST   /api/documents/{id}/publish   { isPublic:true, slug? }

A14 POST   /api/documents/{id}/revisions { code } -> Revision + render meta

A15 POST   /api/sessions                 { documentId } -> sessionId + wsUrl

A16 POST   /api/quizzes/random
A17 POST   /api/quizzes/{id}/submit      { tryoutRevisionId | code }
end note

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/context.puml', 'puml', '176719411f0d850f9ed6d49cc31c79f6556616640daab52b97231a8006abe9a5', '@startuml
title Sheet 1/8 — System Context + Sheets Map

skinparam componentStyle rectangle

actor "User" as user

rectangle "Browser\nReact SPA" as spa
rectangle "Symfony 8 API\n(HTTP Controllers)" as api
rectangle "WS Gateway\nRatchet Server" as ws
database "SQLite\n(Doctrine ORM)" as db
rectangle "PlantUML Renderer\n(PlantUML Server / local service)" as renderer

user --> spa : UI\nLogin / Studio / Quiz
spa --> api : HTTPS (REST)\nJSON
spa --> ws : WSS (WebSocket)\ncollaboration

api --> db : Doctrine ORM
api --> renderer : Render requests\n(SVG)
ws --> api : Call Application layer\n(or shared services)
ws --> db : Session/Revision reads (optional)\nprefer via ports
ws --> renderer : render debounce (optional)\nprefer via API/Messenger

note right of spa
Zustand stores:
- authStore
- settingsStore
- studioStore
- sessionStore
- quizStore
end note

package "Sheets Map (1..8)" as sheets {
  rectangle "1 Context" as S1
  rectangle "2 Domain" as S2
  rectangle "3 UseCases" as S3
  rectangle "4 HTTP API" as S4
  rectangle "5 WS Collab" as S5
  rectangle "6 Workflow+Render" as S6
  rectangle "7 Quiz" as S7
  rectangle "8 Frontend UX" as S8

  S1 --> S4 : REST boundary
  S1 --> S5 : WS boundary
  S4 --> S3 : Controllers -> Interactors
  S5 --> S3 : WS -> Interactors
  S3 --> S2 : Uses entities/policies
  S3 --> S6 : Render + state transitions
  S3 --> S7 : Quiz selection/submit
  S8 --> S4 : HTTP calls
  S8 --> S5 : WS events
}

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/contracts-naive-vars.puml', 'puml', 'ab60622f40fad069b699385592fb115571a99b29ae79796d5a1611b8e879f08c', '@startuml
title contracts-naive-vars.puml — Naive Vars / Contracts (HTTP + WS)

skinparam classAttributeIconSize 0

'' nv1: code
class Code <<nv1>> {
  +text: string
}

'' nv2: caretPosition (single index) + selection range (better)
class CaretPosition <<nv2>> {
  +pos: int
}
class CaretRange {
  +left: int
  +right: int
}

'' nv3: change
enum ChangeType {
  insert
  replace
}
class TextChange <<nv3>> {
  +type: ChangeType
  +range: CaretRange
  +text: string
}

'' nv4: action
enum ActionName {
  acquire_lock
  release_lock
  render_request
  save_revision
  publish
  toggle_favorite
  delete_document
  create_document
  join_session
  leave_session
  quiz_start
  quiz_submit
  login
  logout
}
class Action <<nv4>> {
  +name: ActionName
}

'' nv5: state (UI-centric + doc workflow state)
enum UiState <<nv5>> {
  login_idle
  login_submitting
  studio_no_doc
  studio_loading
  studio_viewing
  studio_locked
  studio_editing
  studio_rendering
  studio_render_ready
  studio_render_error
  quiz_idle
  quiz_editing
  quiz_submitting
  quiz_result
}

'' nv6: transition
class Transition <<nv6>> {
  +stateBefore: UiState
  +stateAfter: UiState
  +action: ActionName
}

'' nv7: event (WS envelope)
enum DeliveryMark {
  ui_to_backend
  backend_to_ui_personal
  backend_to_ui_broadcast
  backend_internal
}

class WsEnvelope <<nv7>> {
  +event: string
  +payload: any
  +correlationId: string?
  +docId: int?
  +sessionId: int?
  +ts: string?
  +mark: DeliveryMark
}

'' HTTP contracts (minimal)
class HttpRequest {
  +headers.Authorization: "Bearer <token>"
  +body: json
}
class HttpResponse {
  +status: int
  +body: json
}

class Api_Login_Request {
  +name: string
  +password: string
}
class Api_Login_Response {
  +token: string
  +user: {id:int,name:string}
}

class Api_GetDocument_Response {
  +document: {id:int, code:string, current_revision:int, status:string, is_public:int}
}

class Api_SaveRevision_Request {
  +code: string
}
class Api_SaveRevision_Response {
  +ok: bool
  +docId: int
  +revisionId: int
  +revision: int
  +isValid: bool
  +svgPath: string
  +svg: string
}

class Api_JoinSession_Request {
  +documentId: int
}
class Api_JoinSession_Response {
  +ok: bool
  +sessionId: int
  +wsUrl: string
  +docId: int
}

WsEnvelope o-- TextChange
TextChange o-- CaretRange
Transition --> UiState
Transition --> ActionName

HttpRequest --> Api_Login_Request
HttpResponse --> Api_Login_Response
HttpResponse --> Api_GetDocument_Response
HttpRequest --> Api_SaveRevision_Request
HttpResponse --> Api_SaveRevision_Response
HttpRequest --> Api_JoinSession_Request
HttpResponse --> Api_JoinSession_Response

note right of TextChange
Range indices are UTF-8 codepoint indices in backend.
Frontend MUST be consistent with this choice.
end note

note bottom of WsEnvelope
Recommended: include correlationId for ACK matching.
mark determines delivery semantics.
end note

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/domain.puml', 'puml', '9c9a7b11aa0da1770a0a9074dd5abeb153fd9cac8235e1d95602092441c41068', '@startuml
title Sheet 2/8 — Domain Model (DDD-light)

skinparam classAttributeIconSize 0

class User {
  +id: int
  +name: string
  +passwordHash: string
}

enum DocumentStatus {
  empty
  valid
  invalid
  deleted
}

enum ChangeType {
  insert
  replace
}

enum RestrictionCategory {
  not_implemented
  auth_required
  not_owner
  locked_by_other
  deleted
  invalid_transition
  forbidden
}

class Document {
  +id: int
  +authorId: int
  +uniqueSlug: string
  +isPublic: bool
  +status: DocumentStatus
  +currentRevision: int
  +code: text
}

class Revision {
  +id: int
  +documentId: int
  +revision: int
  +tsCreated: datetime
  +code: text
  +isValid: bool
  +tsRendered: datetime?
  +svgPath: string?
}

class Session {
  +id: int
  +documentId: int
  +lockedByUserId: int?
  +lockTs: datetime?
}

class Quiz {
  +id: int
  +formulation: text
  +beforeDocumentId: int
  +requiredDocumentId: int
}

class Attempt {
  +id: int
  +userId: int
  +quizId: int
  +tryoutRevisionId: int
  +tsCreated: datetime
  +score: int
}

class CaretRange <<value object>> {
  +left: int
  +right: int
}

class TextChange <<value object>> {
  +type: ChangeType
  +range: CaretRange
  +text: string
}

User "1" --> "many" Document : author
Document "1" --> "many" Revision
Document "1" --> "0..1" Session : active collab
Quiz --> Document : before
Quiz --> Document : required
Attempt --> Quiz
Attempt --> Revision : tryout

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/enums-all.puml', 'puml', 'fe941f1378501f5750346903fa3afb205eca4116a9e53be17c9cd30373aae02d', '@startuml
title enums-all.puml — All enums (Domain + UI + Protocol)

'' Domain
enum DocumentStatus {
  empty
  valid
  invalid
  deleted
}

enum ChangeType {
  insert
  replace
}

enum RestrictionCategory {
  not_implemented
  auth_required
  not_owner
  locked_by_other
  deleted
  invalid_transition
  forbidden
}

'' Workflow transitions (backend) — recommended explicit naming
enum WorkflowAction {
  render_ok
  render_failed
  delete
  publish
  unpublish
}

'' UI states
enum UiState {
  login_idle
  login_submitting
  studio_no_doc
  studio_loading
  studio_viewing
  studio_locked
  studio_editing
  studio_rendering
  studio_render_ready
  studio_render_error
  quiz_idle
  quiz_editing
  quiz_submitting
  quiz_result
}

'' UI actions (atomic intent)
enum UiAction {
  login_submit
  logout_click
  open_document
  create_document
  delete_document
  toggle_favorite
  publish
  join_session
  acquire_lock
  release_lock
  type_edit
  save_revision
  render_request
  quiz_start
  quiz_submit
}

'' WS delivery marking
enum DeliveryMark {
  ui_to_backend
  backend_to_ui_personal
  backend_to_ui_broadcast
  backend_internal
}

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/events-protocol.puml', 'puml', '648c443a3603998cff354d58bfc48a098e063f4a47317fa9d0a09ed502b5047f', '@startuml
title events-protocol.puml — WS/Domain events (names + payloads + marks)

skinparam classAttributeIconSize 0

enum DeliveryMark {
  ui_to_backend
  backend_to_ui_personal
  backend_to_ui_broadcast
  backend_internal
}

class WsEnvelope {
  +event: WsEventName
  +payload: any
  +correlationId: string?
  +docId: int?
  +sessionId: int?
  +ts: string?
  +mark: DeliveryMark
}

enum WsEventName {
  AUTH
  AUTH_ACK
  JOIN
  LEAVE

  DOC_SNAPSHOT

  DOC_COLLABORATOR_JOIN
  DOC_COLLABORATOR_LEAVE
  DOC_COLLABORATOR_ACTION

  DOC_EDIT
  DOC_EDIT_ACK
  DOC_EDIT_APPLIED

  DOC_RENDER_REQUEST
  DOC_RENDER_FINISHED

  LOCK_CHANGED

  ERROR
}

'' Common payload blocks
class Payload_Error {
  +code: string
  +message: string
  +restrictionCategory: string?
}

class Payload_Auth {
  +token: string
}
class Payload_AuthAck {
  +userId: int
}

class Payload_Join {
  +documentId: int
}
class Payload_Snapshot {
  +docId: int
  +code: string
  +revision: int
  +lockUserId: int?
}

class CaretRange {
    +left:int
    +right:int
}
enum ChangeType {
    insert
    replace
}
class Payload_Edit {
  +docId: int
  +baseRevision: int?  '' optional optimistic check
  +change: {type:ChangeType, range:CaretRange, text:string}
  +caret: CaretRange
}

class Payload_EditAck {
  +ok: bool
  +docId: int
  +revision: int
  +caret: CaretRange
  +error?: Payload_Error
}

class Payload_EditApplied {
  +docId: int
  +userId: int
  +revision: int
  +change: any
}

class Payload_Action {
  +action: "acquire_lock"|"release_lock"
}
class Payload_LockChanged {
  +docId: int
  +lockUserId: int?
}

class Payload_RenderRequest {
  +docId: int?
}
class Payload_RenderFinished {
  +docId: int
  +revision: int
  +isValid: bool
  +svgPath: string
  +svg?: string
}

WsEnvelope --> WsEventName
WsEnvelope o-- Payload_Error

WsEnvelope o-- Payload_Auth
WsEnvelope o-- Payload_AuthAck
WsEnvelope o-- Payload_Join
WsEnvelope o-- Payload_Snapshot

WsEnvelope o-- Payload_Edit
WsEnvelope o-- Payload_EditAck
WsEnvelope o-- Payload_EditApplied

WsEnvelope o-- Payload_Action
WsEnvelope o-- Payload_LockChanged

WsEnvelope o-- Payload_RenderRequest
WsEnvelope o-- Payload_RenderFinished

note right of WsEventName
Delivery marks (recommended):

UI -> Backend (mark=ui_to_backend):
- AUTH, JOIN, LEAVE
- DOC_COLLABORATOR_ACTION
- DOC_EDIT
- DOC_RENDER_REQUEST

Backend -> UI personal:
- AUTH_ACK
- DOC_SNAPSHOT
- DOC_EDIT_ACK
- ERROR

Backend -> UI broadcast:
- DOC_COLLABORATOR_JOIN/LEAVE
- DOC_EDIT_APPLIED
- DOC_RENDER_FINISHED
- LOCK_CHANGED
end note

note bottom
Domain/internal events (backend_internal) that may exist behind WS:
- DocumentEdited
- RenderRequested
- RenderFinished
- LockAcquired/Released
These can be implemented via Symfony EventDispatcher/Messenger later.
end note

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/uiquiz.puml', 'puml', '2b2462bae5f19812ee2a6925d2496c09d6679a954faa646558d12bba546335c4', '@startuml
title Sheet 7/8 — Quiz Flow (Random test + Submit)

actor "User" as U
participant "React Studio" as UI
participant "QuizController" as QC
participant "TakeRandomQuiz" as UR
participant "SubmitQuiz" as US
participant "RendererGateway" as RG
database "SQLite" as DB

== Start random quiz ==
U -> UI : Click "+"
UI -> QC : A16 POST /api/quizzes/random
QC -> UR : execute(userId)
UR -> DB : select random Quiz\n(+ beforeDocument)
QC --> UI : {quizId, formulation,\n beforeDoc:{id,code}}

== Solve ==
U -> UI : edit code (WS/Local)\nthen Submit
UI -> QC : A17 POST /api/quizzes/{id}/submit\n{code | tryoutRevisionId}
QC -> US : execute(...)
US -> RG : renderSvg(tryoutCode)
US -> DB : load requiredDocument.code\nrenderSvg(requiredCode)
US -> DB : insert Attempt(score,...)
QC --> UI : {score, isPass, diffHint?, requiredSvg? (optional)}
@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/usecases.puml', 'puml', '2fd61d9360eca6230208fa480317a3a04e92205355afc6866bbb1522260b06e1', '@startuml
title Sheet 3/8 — Application Layer (UseCases + Ports)

skinparam componentStyle rectangle

package "Application (Interactors)" {
  [LoginUser] as U1
  [LogoutUser] as U1b
  [CreateDocument] as U2a
  [DeleteDocument] as U2b
  [ToggleFavorite] as U2c
  [PublishDocument] as U3
  [JoinSession] as U4
  [ApplyEdit] as U5
  [SaveRevision] as U6
  [TakeRandomQuiz] as U7
  [ListPersonalDocs] as U8a
  [GetStats] as U8b
  [SubmitQuiz] as U7b
}

package "Core Bricks" {
  [SessionManager] as C1
  [DocumentApplier] as C2
  [RestrictionInterface] as C3
}

package "Ports (Interfaces)" {
  [DocumentRepository] as PDoc
  [RevisionRepository] as PRev
  [SessionRepository] as PSess
  [QuizRepository] as PQuiz
  [EventBus] as PBus
  [RendererGateway] as PRend
}

U4 --> C1
U5 --> C1
U5 --> C2
U2a --> PDoc
U2b --> PDoc
U3 --> PDoc
U6 --> PRev
U4 --> PSess
U5 --> PSess
U7 --> PQuiz
U7b --> PQuiz
U7b --> PRend
U5 --> PBus
U6 --> PBus
U3 --> PBus

C3 ..> U2b : policy checks
C3 ..> U3 : policy checks
C3 ..> U5 : policy checks

note right of PBus
Implementation options:
- Symfony EventDispatcher (sync)
- Symfony Messenger (async render/jobs)
end note

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/ux.puml', 'puml', '3b268ea89c38adaba534524e8bf5d3e4214184f335fbc1ef6107d844e9a4bb4c', '@startuml
title Sheet 8/8 — Frontend UX + Zustand State (Atomic Paths)

skinparam componentStyle rectangle

package "Screens" {
  [LoginScreen] as SLogin
  [StudioScreen] as SStudio
}

package "Studio Layout" {
  [TopBar\n(details+actions)] as Top
  [Sidebar\n(personal/public/favs)] as Side
  [EditorPane\n(code + caret)] as Editor
  [PreviewPane\n(svg)] as Preview
}

package "Zustand Stores" {
  [authStore] as Auth
  [settingsStore] as Set
  [studioStore] as Studio
  [sessionStore] as Sess
  [quizStore] as Quiz
}

SLogin --> Auth : login/logout
SStudio --> Set : load/save settings
SStudio --> Studio : open doc, local edits, render state
SStudio --> Sess : ws connect/join, presence, lock
SStudio --> Quiz : quiz mode, submit

SStudio --> Top
SStudio --> Side
SStudio --> Editor
SStudio --> Preview

note right of Studio
Atomic actions (deterministic):
1) openDocument(id)
   - GET /api/documents/{id}
   - POST /api/sessions {documentId}
   - WS JOIN {docId}

2) type/erase/selection
   - compute change: {type, range, text}
   - update local optimistic buffer
   - WS DOC_EDIT {change, caret}

3) saveRevision
   - POST /api/documents/{id}/revisions {code}

4) renderDebounce
   - WS DOC_RENDER_REQUEST {code} (or rely on A14 result)

5) quizStart (plus)
   - POST /api/quizzes/random
   - enter quizMode(beforeCode)

6) quizSubmit
   - POST /api/quizzes/{id}/submit {code}
end note

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/websocket.puml', 'puml', '3dfe9d9572fe4eeb66cc0b3161abcd62569d640fd25c27d938f81fc513e838d3', '@startuml
title Sheet 5/8 — WS Collaboration Protocol (Events + Delivery)

actor "Client A" as A
actor "Client B" as B
participant "Ratchet WS Server\n(WsGateway)" as W
participant "SessionManager" as SM
participant "ApplyEdit UseCase" as U5
participant "DocumentApplier" as DA

== Connect & Join ==
A -> W : AUTH {sessionCookie | bearer}
A -> W : JOIN {documentId}
W -> SM : join(documentId, userId)
W --> A : DOC_COLLABORATOR_JOIN (personal ack)
W --> B : DOC_COLLABORATOR_JOIN (broadcast)

== Acquire edit lock (simple, deterministic MVP) ==
A -> W : DOC_COLLABORATOR_ACTION {action:''acquire_lock''}
W -> SM : acquireLock(documentId, userId)
W --> A : LOCK_ACQUIRED (personal)
W --> B : LOCK_CHANGED (broadcast)

== Edit (nv3: change + caret) ==
A -> W : DOC_EDIT {docId, baseRevision, change:{type,range,text}, caret:{left,right}}
W -> U5 : execute(change)
U5 -> DA : apply(code, change)
U5 -> SM : touchPresence()
W --> A : DOC_EDIT_ACK {newRevision, caretNormalized}
W --> B : DOC_EDIT_APPLIED {newRevision, change, authorId}

== Leave ==
A -> W : LEAVE
W -> SM : leave()
W --> B : DOC_COLLABORATOR_LEAVE (broadcast)

note right of W
Delivery rules:

UI -> Backend:
- AUTH, JOIN, LEAVE
- DOC_COLLABORATOR_ACTION (acquire/release)
- DOC_EDIT
- DOC_RENDER_REQUEST (optional)

Backend -> UI (broadcast):
- DOC_COLLABORATOR_JOIN/LEAVE
- LOCK_CHANGED
- DOC_EDIT_APPLIED
- DOC_RENDER_FINISHED

Backend -> UI (personal):
- *_ACK, errors, initial snapshot
end note

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('docs/workflow.puml', 'puml', '7a879818fc36f488c0fd144106134d7fa91b50b6fb96604e5082db46da9b902b', '@startuml
title Sheet 6/8 — Document Workflow + Rendering

[*] --> empty

state empty
state invalid
state valid
state deleted

empty --> invalid : render_failed
empty --> valid   : render_ok

invalid --> valid : render_ok
valid --> invalid : render_failed

empty --> deleted : delete
invalid --> deleted : delete
valid --> deleted : delete

note right of valid
DOC_RENDER_FINISHED:
- isValid=true
- svgPath set
- tsRendered set
end note

note left of invalid
DOC_RENDER_FINISHED:
- isValid=false
- svgPath may be null
- store error details (optional)
end note

@enduml', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('PRODUCT.md', 'markdown', '5fe9ce8186e492ccfd5be64add6df308f92254fab7bdc4f05cb345b6f4446d80', '# **PlantUML Studio** - веб-сервис для **редактирования PlantUML-диаграмм** с:

1. **совместной работой (collaboration)** в реальном времени через WebSocket
2. **автоматическим рендером** диаграмм в SVG
3. **режимом тестирования знаний PlantUML** (random quiz), где пользователь решает задачи, редактируя PlantUML код

Цель: быстрый “минималистичный IDE-опыт” в браузере без тяжёлой инфраструктуры.

О PlantUML как о языке диаграмм: официальная документация. ([https://plantuml.com/](https://plantuml.com/))

---

## Что пользователь может делать (основные сценарии)

### 1) Войти и выйти (u1)

Пользователь открывает ссылку на Studio, логинится и получает доступ к личным документам и коллаборации.

* **Вход** выдаёт токен (или сессия/кука в будущем), который используется для HTTP и WS.
* **Выход** очищает авторизацию на клиенте.

---

### 2) Управлять документами (u2)

Пользователь работает с PlantUML-документами как с “диаграммами”:

* **Создать документ** (например через “+ → New doc”)
* **Удалить документ**
* **Добавить в избранное (Favorite)** — чтобы быстро находить
* **Смотреть списки**:

    * Personal (мои)
    * Favorites (избранные)
    * Public (публичные, доступные по ссылке/в общем списке)

---

### 3) Публиковать (u3)

Пользователь может сделать документ **публичным**:

* появляется **slug/URL**, который можно отправить другим
* документ становится доступным для просмотра тем, у кого нет прав владельца (по правилам продукта)

---

### 4) Подключиться к коллаборации (u4)

Когда пользователь открывает документ, он:

1. загружает документ по HTTP
2. создаёт/получает **session** по HTTP
3. подключается к WebSocket и получает **снимок документа (DOC_SNAPSHOT)**

Это режим “в одном документе можно работать нескольким людям”.

---

### 5) Совместно редактировать в реальном времени (u5)

Во время коллаборации есть **простая модель контроля редактирования** (минималистичный MVP):

* один активный редактор держит **lock**
* остальные видят изменения и могут запросить lock

Редактирование идёт через WebSocket события:

* получение/освобождение lock
* отправка изменений текста как `TextChange` (insert/replace + range + caret)
* рассылка применённых изменений всем участникам сессии

---

### 6) Сохранять ревизии и получать SVG-превью (u6)

Студия постоянно поддерживает **историю ревизий**:

* после ввода текста (debounce) или по кнопке “Save” создаётся **Revision**
* документ **рендерится в SVG**
* пользователь видит обновлённый preview справа

Это даёт:

* “уверенность” (всё сохраняется)
* историю (можно потом сравнивать/возвращаться)

---

### 7) Проходить тесты знаний PlantUML (u7)

Кнопка “+” даёт режим **Random Quiz**:

* система выбирает случайный тест
* выдаёт формулировку и стартовый PlantUML код (`beforeDoc`)
* пользователь редактирует код, чтобы выполнить задание

---

### 8) Смотреть личные списки и статистику (u8)

Продукт показывает:

* списки документов по фильтрам (personal/favorites/public)
* статистику:

    * сколько сделано правок/ревизий
    * сколько попыток тестов
    * последняя активность (в будущем)

---

## Как выглядит продукт

### Экран 1: Login

* поля логина/пароля
* кнопка “Sign in”
* состояния: idle → submitting → success/error

### Экран 2: Studio (главный)

**Основной layout:**

* TopBar: название документа, действия (Publish / Favorite / Save / Lock)
* Sidebar: списки документов (Personal / Favorites / Public)
* Центральная область 50/50:

    * слева — Editor (PlantUML text)
    * справа — Preview (SVG)
* split draggable (в будущем), настройки (в будущем)

**Плюс-меню (“+”):**

* New document
* Random quiz

---

## “Что должен уметь продукт”

**PlantUML Studio** — это браузерная студия, где пользователь может **создавать и редактировать PlantUML-диаграммы**, видеть **живое SVG-превью**, **совместно работать** над одним документом через WebSocket-сессию и периодически проверять себя в **рандомных тестах** по PlantUML, сохраняя результаты.

---

## Что использует сейчас под капотом:

* **HTTP API**: аутентификация, загрузка/управление документами, сохранение ревизий, запуск/сдача тестов
* **WebSocket**: присутствие/lock, правки в реальном времени, уведомления о рендере
* **SQLite**: документы, ревизии, сессии, тесты, история попыток
* **Docker**: образ с сервисом для рендеринга .puml в svg', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;

INSERT INTO docs_catalog(path, kind, sha256, content, ts_imported) VALUES ('README.md', 'markdown', '52662685b17700f1c363c5f948408558738578644d231defca0f3323e5f969b0', '# PlantUML Studio

Минималистичная студия для редактирования PlantUML-диаграмм с live-preview и WebSocket-коллаборацией.

## Что в репозитории

- `src/` — доменная и прикладная логика (UseCases, репозитории, коллаборация).
- `index.php` — HTTP entrypoint для API.
- `ws-server.php` — WebSocket сервер для совместного редактирования.
- `ui/` — фронтенд на React + Vite.
- `migrations/` и `bin/migrate.php` — инициализация SQLite.
- `docs/` — UML/PlantUML схемы.
- `old/` — предыдущий прототип (оставлен как архив).

## Быстрый старт (локально, без Docker)

Требования:
- PHP `>= 8.2`
- Composer
- Node.js `>= 18`
- npm

1. Установить PHP-зависимости:
```bash
composer install
```

2. Поднять SQLite схему:
```bash
php bin/migrate.php
```

3. Запустить HTTP API:
```bash
php -S 127.0.0.1:8000 index.php
```

4. В отдельном терминале запустить WebSocket сервер:
```bash
php ws-server.php
```

5. В отдельном терминале запустить UI:
```bash
cd ui
npm install
npm run dev
```

UI по умолчанию: `http://127.0.0.1:5173`

## Статус Docker

`docker/` и `docker-compose.yaml` зарезервированы под будущую контейнеризацию.
Когда конфигурация будет заполнена, в README будет добавлен отдельный раздел с Docker-запуском.
', datetime('now'))
ON CONFLICT(path) DO UPDATE SET kind=excluded.kind, sha256=excluded.sha256, content=excluded.content, ts_imported=excluded.ts_imported;
