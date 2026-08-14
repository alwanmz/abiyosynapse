import { useSyncExternalStore } from 'react';

// Default = SEMUA kartu EXPANDED. Yang dilacak justru kartu yang di-RINGKAS
// (collapsed) — itu cuma opsi. Tidak ada yang dipersist, jadi tiap halaman
// dibuka/di-mount ulang otomatis kembali expanded semua.
let collapsed: Set<number> = new Set();
const listeners = new Set<() => void>();

function emit(): void {
    listeners.forEach((listener) => listener());
}

function subscribe(callback: () => void): () => void {
    listeners.add(callback);
    return () => listeners.delete(callback);
}

export function toggleCard(id: number): void {
    collapsed = new Set(collapsed);
    if (collapsed.has(id)) {
        collapsed.delete(id);
    } else {
        collapsed.add(id);
    }
    emit();
}

/** expand=true → perluas semua (kosongkan collapsed); expand=false → ringkas semua. */
export function setAllExpanded(ids: number[], expand: boolean): void {
    collapsed = expand ? new Set() : new Set(ids);
    emit();
}

export function useCardExpanded(id: number): boolean {
    return useSyncExternalStore(
        subscribe,
        () => !collapsed.has(id),
        () => true, // default: expanded
    );
}

export function useCollapsedCount(): number {
    return useSyncExternalStore(
        subscribe,
        () => collapsed.size,
        () => 0,
    );
}
