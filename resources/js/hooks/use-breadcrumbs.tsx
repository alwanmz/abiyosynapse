import { type BreadcrumbItem } from '@/types';
import {
    createContext,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from 'react';

const BreadcrumbsContext = createContext<{
    breadcrumbs: BreadcrumbItem[];
    setBreadcrumbs: (items: BreadcrumbItem[]) => void;
} | null>(null);

export function BreadcrumbsProvider({ children }: { children: ReactNode }) {
    const [breadcrumbs, setBreadcrumbs] = useState<BreadcrumbItem[]>([]);

    return (
        <BreadcrumbsContext.Provider value={{ breadcrumbs, setBreadcrumbs }}>
            {children}
        </BreadcrumbsContext.Provider>
    );
}

export function useBreadcrumbsValue() {
    const ctx = useContext(BreadcrumbsContext);
    if (!ctx) {
        throw new Error('useBreadcrumbsValue must be used within a BreadcrumbsProvider');
    }
    return ctx.breadcrumbs;
}

// Pages call this once to declare their breadcrumb trail. Declaring it via
// context (rather than a prop passed at the AppLayout wrap site) lets the
// header/sidebar shell stay mounted across Inertia page navigations instead
// of remounting on every click.
export function useBreadcrumbs(items: BreadcrumbItem[]) {
    const ctx = useContext(BreadcrumbsContext);
    if (!ctx) {
        throw new Error('useBreadcrumbs must be used within a BreadcrumbsProvider');
    }

    const key = JSON.stringify(items);
    useEffect(() => {
        ctx.setBreadcrumbs(items);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);
}
