import React, { useEffect, useMemo, useRef, useState } from "react";

type SharedView = { zoom: number; nx: number; ny: number };
type Cursor = { userId: number; name: string; color: string; x: number; y: number; visible: boolean };

const MIN_ZOOM = 0.2;
const MAX_ZOOM = 6;

function clamp(v: number, min: number, max: number) {
  return Math.max(min, Math.min(max, v));
}

function getSvgSize(host: HTMLElement): { width: number; height: number } | null {
  const svg = host.querySelector("svg");
  if (!svg) return null;
  const vb = svg.viewBox?.baseVal;
  if (vb && vb.width > 0 && vb.height > 0) {
    return { width: vb.width, height: vb.height };
  }
  const rect = svg.getBoundingClientRect();
  if (rect.width > 0 && rect.height > 0) {
    return { width: rect.width, height: rect.height };
  }
  return null;
}

type Props = {
  svg: string;
  view?: SharedView;
  cursors?: Cursor[];
  onViewChange?: (next: SharedView) => void;
  onCursor?: (cursor: { x: number; y: number; visible: boolean }) => void;
};

export function Preview({ svg, view, cursors, onViewChange, onCursor }: Props) {
  const safeView = view ?? { zoom: 1, nx: 0, ny: 0 };
  const safeCursors = cursors ?? [];
  const stageRef = useRef<HTMLDivElement | null>(null);
  const svgHostRef = useRef<HTMLDivElement | null>(null);
  const dragRef = useRef<{ startX: number; startY: number; baseX: number; baseY: number } | null>(null);
  const [fitTick, setFitTick] = useState(0);

  const px = useMemo(() => {
    const el = stageRef.current;
    const w = el?.clientWidth ?? 1;
    const h = el?.clientHeight ?? 1;
    return { x: safeView.nx * w, y: safeView.ny * h };
  }, [safeView.nx, safeView.ny, safeView.zoom]);

  const pushFromPx = (zoom: number, x: number, y: number) => {
    const el = stageRef.current;
    if (!el) return;
    const w = Math.max(1, el.clientWidth);
    const h = Math.max(1, el.clientHeight);
    onViewChange?.({ zoom: clamp(zoom, MIN_ZOOM, MAX_ZOOM), nx: x / w, ny: y / h });
  };

  const zoomAround = (clientX: number, clientY: number, factor: number) => {
    const el = stageRef.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const currentX = safeView.nx * rect.width;
    const currentY = safeView.ny * rect.height;
    const nextZoom = clamp(safeView.zoom * factor, MIN_ZOOM, MAX_ZOOM);
    const worldX = (clientX - rect.left - currentX) / safeView.zoom;
    const worldY = (clientY - rect.top - currentY) / safeView.zoom;
    const nextX = clientX - rect.left - worldX * nextZoom;
    const nextY = clientY - rect.top - worldY * nextZoom;
    pushFromPx(nextZoom, nextX, nextY);
  };

  const fitToScreen = () => {
    const stage = stageRef.current;
    const host = svgHostRef.current;
    if (!stage || !host) return;
    const size = getSvgSize(host);
    if (!size) return;
    const margin = 24;
    const availW = Math.max(60, stage.clientWidth - margin * 2);
    const availH = Math.max(60, stage.clientHeight - margin * 2);
    const nextZoom = clamp(Math.min(availW / size.width, availH / size.height), MIN_ZOOM, MAX_ZOOM);
    const nextX = (stage.clientWidth - size.width * nextZoom) / 2;
    const nextY = (stage.clientHeight - size.height * nextZoom) / 2;
    pushFromPx(nextZoom, nextX, nextY);
  };

  useEffect(() => {
    if (!svg) return;
    const t = window.setTimeout(() => setFitTick((v) => v + 1), 0);
    return () => window.clearTimeout(t);
  }, [svg]);

  useEffect(() => {
    if (fitTick <= 0) return;
    fitToScreen();
  }, [fitTick]);

  return (
    <div className="flex h-full min-h-0 flex-col">
      <div className="border-b border-black/10 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-black/60">Preview (SVG)</div>
      <div className="relative min-h-0 flex-1 overflow-hidden bg-white/70">
        <div className="absolute right-2 top-2 z-20 flex items-center gap-1 rounded-xl border border-black/10 bg-white/90 p-1 shadow-sm">
          <button className="rounded px-2 py-1 text-sm hover:bg-black/5" onClick={() => {
            const el = stageRef.current;
            if (!el) return;
            const rect = el.getBoundingClientRect();
            zoomAround(rect.left + rect.width / 2, rect.top + rect.height / 2, 1.15);
          }}>+</button>
          <button className="rounded px-2 py-1 text-sm hover:bg-black/5" onClick={() => {
            const el = stageRef.current;
            if (!el) return;
            const rect = el.getBoundingClientRect();
            zoomAround(rect.left + rect.width / 2, rect.top + rect.height / 2, 1 / 1.15);
          }}>-</button>
          <button className="rounded px-2 py-1 text-xs hover:bg-black/5" onClick={fitToScreen}>Fit</button>
        </div>

        <div
          ref={stageRef}
          className="h-full w-full cursor-grab touch-none"
          onWheel={(e) => {
            e.preventDefault();
            zoomAround(e.clientX, e.clientY, e.deltaY < 0 ? 1.12 : 1 / 1.12);
          }}
          onPointerDown={(e) => {
            e.preventDefault();
            const el = stageRef.current;
            if (!el) return;
            const rect = el.getBoundingClientRect();
            dragRef.current = {
              startX: e.clientX,
              startY: e.clientY,
              baseX: safeView.nx * rect.width,
              baseY: safeView.ny * rect.height,
            };
            e.currentTarget.setPointerCapture(e.pointerId);
          }}
          onPointerMove={(e) => {
            const el = stageRef.current;
            if (!el) return;
            const rect = el.getBoundingClientRect();
            const nx = clamp((e.clientX - rect.left) / Math.max(1, rect.width), 0, 1);
            const ny = clamp((e.clientY - rect.top) / Math.max(1, rect.height), 0, 1);
            onCursor?.({ x: nx, y: ny, visible: true });

            const drag = dragRef.current;
            if (!drag) return;
            const nextX = drag.baseX + (e.clientX - drag.startX);
            const nextY = drag.baseY + (e.clientY - drag.startY);
            pushFromPx(safeView.zoom, nextX, nextY);
          }}
          onPointerUp={(e) => {
            dragRef.current = null;
            e.currentTarget.releasePointerCapture(e.pointerId);
          }}
          onPointerLeave={() => onCursor?.({ x: 0, y: 0, visible: false })}
        >
          <div
            ref={svgHostRef}
            className="absolute left-0 top-0"
            style={{
              transform: `translate(${px.x}px, ${px.y}px) scale(${safeView.zoom})`,
              transformOrigin: "left top",
            }}
            dangerouslySetInnerHTML={{ __html: svg || "<div style='color:#888;padding:12px'>no render</div>" }}
          />

          {safeCursors.filter((c) => c.visible).map((c) => (
            <div key={c.userId} className="pointer-events-none absolute z-10" style={{ left: `${c.x * 100}%`, top: `${c.y * 100}%` }}>
              <div className="h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow" style={{ background: c.color }} />
              <div className="ml-2 mt-1 rounded bg-black/80 px-1.5 py-0.5 text-[10px] text-white">{c.name}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
