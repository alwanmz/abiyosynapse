import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Link } from '@inertiajs/react';
import {
    IconChevronLeft,
    IconChevronRight,
    IconChevronsLeft,
    IconChevronsRight,
} from '@tabler/icons-react';
import { useTranslation } from 'react-i18next';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationData {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface PaginationProps {
    pagination: PaginationData;
}

export function Pagination({ pagination }: PaginationProps) {
    const { t } = useTranslation();
    const { current_page, last_page, per_page, total, from, to, links } =
        pagination;

    if (total === 0) return null;

    const firstLink = links[0];
    const lastLink = links[links.length - 1];
    const prevLink = links.find((link) => link.label === '&laquo; Previous');
    const nextLink = links.find((link) => link.label === 'Next &raquo;');

    const pageLinks = links.filter(
        (link) =>
            link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;',
    );

    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            {/* Results info */}
            <div className="text-sm text-muted-foreground">
                {t('pagination.showing', { from: from || 0, to: to || 0, total })}
            </div>

            {/* Pagination controls */}
            <div className="flex items-center gap-2">
                {/* Per page selector */}
                <div className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        {t('pagination.per_page')}
                    </span>
                    <Select
                        value={per_page.toString()}
                        onValueChange={(value) => {
                            const url = new URL(window.location.href);
                            url.searchParams.set('per_page', value);
                            url.searchParams.delete('page');
                            window.location.href = url.toString();
                        }}
                    >
                        <SelectTrigger className="h-8 w-16">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[10, 25, 50, 100].map((num) => (
                                <SelectItem key={num} value={num.toString()}>
                                    {num}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center gap-1">
                    {/* First page */}
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        disabled={current_page === 1 || !firstLink.url}
                        asChild={!!firstLink.url && current_page !== 1}
                    >
                        {firstLink.url && current_page !== 1 ? (
                            <Link href={firstLink.url} preserveState>
                                <IconChevronsLeft className="h-4 w-4" />
                            </Link>
                        ) : (
                            <span>
                                <IconChevronsLeft className="h-4 w-4" />
                            </span>
                        )}
                    </Button>

                    {/* Previous page */}
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        disabled={!prevLink?.url}
                        asChild={!!prevLink?.url}
                    >
                        {prevLink?.url ? (
                            <Link href={prevLink.url} preserveState>
                                <IconChevronLeft className="h-4 w-4" />
                            </Link>
                        ) : (
                            <span>
                                <IconChevronLeft className="h-4 w-4" />
                            </span>
                        )}
                    </Button>

                    {/* Page numbers */}
                    <div className="hidden items-center gap-1 sm:flex">
                        {pageLinks.map((link, index) => {
                            if (link.label === '...') {
                                return (
                                    <span
                                        key={`ellipsis-${index}`}
                                        className="px-2 text-sm text-muted-foreground"
                                    >
                                        ...
                                    </span>
                                );
                            }

                            return (
                                <Button
                                    key={link.label}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    className="h-8 min-w-8 px-2"
                                    disabled={link.active || !link.url}
                                    asChild={!!link.url && !link.active}
                                >
                                    {link.url && !link.active ? (
                                        <Link href={link.url} preserveState>
                                            {link.label}
                                        </Link>
                                    ) : (
                                        <span>{link.label}</span>
                                    )}
                                </Button>
                            );
                        })}
                    </div>

                    {/* Current page indicator (mobile) */}
                    <div className="flex items-center sm:hidden">
                        <span className="text-sm text-muted-foreground">
                            {t('pagination.page_of', { current: current_page, last: last_page })}
                        </span>
                    </div>

                    {/* Next page */}
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        disabled={!nextLink?.url}
                        asChild={!!nextLink?.url}
                    >
                        {nextLink?.url ? (
                            <Link href={nextLink.url} preserveState>
                                <IconChevronRight className="h-4 w-4" />
                            </Link>
                        ) : (
                            <span>
                                <IconChevronRight className="h-4 w-4" />
                            </span>
                        )}
                    </Button>

                    {/* Last page */}
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        disabled={current_page === last_page || !lastLink.url}
                        asChild={!!lastLink.url && current_page !== last_page}
                    >
                        {lastLink.url && current_page !== last_page ? (
                            <Link href={lastLink.url} preserveState>
                                <IconChevronsRight className="h-4 w-4" />
                            </Link>
                        ) : (
                            <span>
                                <IconChevronsRight className="h-4 w-4" />
                            </span>
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
}
