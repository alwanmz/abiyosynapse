import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { GuidebookCategory, GuidebookSummary } from '@/types/guidebook';
import { Head, router } from '@inertiajs/react';
import { BookOpen, Eye, Pin, PinOff, Plus, Search, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { GuidebookFormDialog } from './components/guidebook-form-dialog';
import { CONTENT_TYPES, contentTypeMeta } from './constants/content-types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Guidebook', href: '/guidebooks' }];

interface Props {
    guidebooks: GuidebookSummary[];
    categories: GuidebookCategory[];
    filters: {
        search: string | null;
        category_id: string | null;
        content_type: string | null;
    };
    can: { manage: boolean };
}

export default function GuidebooksIndex({ guidebooks, categories, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const applyFilter = useCallback(
        (overrides: Record<string, string | undefined>) => {
            const next: Record<string, string> = {};
            const merged = {
                search: filters.search ?? undefined,
                category_id: filters.category_id ?? undefined,
                content_type: filters.content_type ?? undefined,
                ...overrides,
            };

            Object.entries(merged).forEach(([key, value]) => {
                if (value) next[key] = value;
            });

            router.get('/guidebooks', next, { preserveState: true, replace: true });
        },
        [filters.search, filters.category_id, filters.content_type],
    );

    // Pencarian didebounce agar tidak menembak server tiap ketikan.
    useEffect(() => {
        if (search === (filters.search ?? '')) return;

        const timer = setTimeout(() => {
            applyFilter({ search: search || undefined });
        }, 350);

        return () => clearTimeout(timer);
    }, [search, filters.search, applyFilter]);

    const pinned = guidebooks.filter((item) => item.is_pinned);
    const rest = guidebooks.filter((item) => !item.is_pinned);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Guidebook" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Guidebook</h1>
                        <p className="text-muted-foreground">
                            Pusat SOP, panduan kerja, video tutorial, dan knowledge base tim.
                        </p>
                    </div>
                    {can.manage && (
                        <Button onClick={() => setFormOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah
                        </Button>
                    )}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari guidebook…"
                            className="pl-8"
                        />
                    </div>
                    <Select
                        value={filters.category_id ?? 'all'}
                        onValueChange={(value) =>
                            applyFilter({ category_id: value === 'all' ? undefined : value })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Semua kategori" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua kategori</SelectItem>
                            {categories.map((category) => (
                                <SelectItem key={category.id} value={String(category.id)}>
                                    {category.name} ({category.guidebooks_count ?? 0})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.content_type ?? 'all'}
                        onValueChange={(value) =>
                            applyFilter({ content_type: value === 'all' ? undefined : value })
                        }
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Semua tipe" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua tipe</SelectItem>
                            {CONTENT_TYPES.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {guidebooks.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                            <BookOpen className="mb-3 h-12 w-12 opacity-20" />
                            <p className="font-medium">Belum ada guidebook.</p>
                            <p className="mt-1 text-sm">
                                Mulai dokumentasikan SOP dan panduan kerja tim di sini.
                            </p>
                            {can.manage && (
                                <Button className="mt-4" onClick={() => setFormOpen(true)}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah Guidebook
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {pinned.length > 0 && (
                            <section className="flex flex-col gap-3">
                                <h2 className="flex items-center gap-1.5 text-sm font-semibold text-muted-foreground">
                                    <Pin className="h-4 w-4" />
                                    Disematkan
                                </h2>
                                <GuidebookGrid
                                    guidebooks={pinned}
                                    canManage={can.manage}
                                    onDelete={setDeletingId}
                                />
                            </section>
                        )}

                        {rest.length > 0 && (
                            <section className="flex flex-col gap-3">
                                {pinned.length > 0 && (
                                    <h2 className="text-sm font-semibold text-muted-foreground">
                                        Semua Guidebook
                                    </h2>
                                )}
                                <GuidebookGrid
                                    guidebooks={rest}
                                    canManage={can.manage}
                                    onDelete={setDeletingId}
                                />
                            </section>
                        )}
                    </>
                )}
            </div>

            <GuidebookFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                categories={categories}
            />

            <ConfirmDialog
                open={deletingId !== null}
                onOpenChange={(o) => !o && setDeletingId(null)}
                title="Hapus guidebook?"
                description="Guidebook ini beserta berkas yang menyertainya akan dihapus permanen."
                confirmLabel="Ya, Hapus"
                onConfirm={() => {
                    if (deletingId === null) return;
                    router.delete(`/guidebooks/${deletingId}`, {
                        preserveScroll: true,
                        onFinish: () => setDeletingId(null),
                    });
                }}
            />
        </AppLayout>
    );
}

function GuidebookGrid({
    guidebooks,
    canManage,
    onDelete,
}: {
    guidebooks: GuidebookSummary[];
    canManage: boolean;
    onDelete: (id: number) => void;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {guidebooks.map((guidebook) => {
                const meta = contentTypeMeta(guidebook.content_type);
                const Icon = meta.icon;

                return (
                    <Card
                        key={guidebook.id}
                        className="group cursor-pointer transition-shadow hover:shadow-md"
                        onClick={() => router.visit(`/guidebooks/${guidebook.id}`)}
                    >
                        <CardContent className="p-4">
                            <div className="mb-2 flex items-start justify-between gap-2">
                                <span className="line-clamp-2 text-sm font-semibold leading-snug">
                                    {guidebook.title}
                                </span>
                                {canManage && (
                                    <div className="flex shrink-0 items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <button
                                            type="button"
                                            title={
                                                guidebook.is_pinned ? 'Lepas sematan' : 'Sematkan'
                                            }
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                router.post(
                                                    `/guidebooks/${guidebook.id}/pin`,
                                                    {},
                                                    { preserveScroll: true },
                                                );
                                            }}
                                        >
                                            {guidebook.is_pinned ? (
                                                <PinOff className="h-4 w-4 text-muted-foreground hover:text-foreground" />
                                            ) : (
                                                <Pin className="h-4 w-4 text-muted-foreground hover:text-foreground" />
                                            )}
                                        </button>
                                        <button
                                            type="button"
                                            title="Hapus guidebook"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onDelete(guidebook.id);
                                            }}
                                        >
                                            <Trash2 className="h-4 w-4 text-destructive/60 hover:text-destructive" />
                                        </button>
                                    </div>
                                )}
                            </div>

                            {guidebook.description && (
                                <p className="mb-3 line-clamp-2 text-xs text-muted-foreground">
                                    {guidebook.description}
                                </p>
                            )}

                            <div className="flex flex-wrap items-center gap-1.5">
                                <Badge
                                    variant="secondary"
                                    className={cn('gap-1 text-xs', meta.badgeClass)}
                                >
                                    <Icon className="h-3 w-3" />
                                    {meta.label}
                                </Badge>
                                {guidebook.category && (
                                    <Badge variant="outline" className="text-xs">
                                        {guidebook.category.name}
                                    </Badge>
                                )}
                            </div>

                            <div className="mt-3 flex items-center gap-3 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <Eye className="h-3.5 w-3.5" />
                                    {guidebook.view_count}
                                </span>
                                {guidebook.content_type === 'checklist' && (
                                    <span>{guidebook.checklist_count} langkah</span>
                                )}
                                {guidebook.creator && <span>oleh {guidebook.creator.name}</span>}
                            </div>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
