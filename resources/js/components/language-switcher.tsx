import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { SUPPORTED_LOCALES, type SupportedLocale } from '@/i18n';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function setLocaleCookie(locale: SupportedLocale) {
    const maxAge = 60 * 60 * 24 * 365; // 1 year
    document.cookie = `locale=${locale}; path=/; max-age=${maxAge}; samesite=lax`;
}

export function LanguageSwitcher() {
    const { t, i18n } = useTranslation();
    const { locale } = usePage<SharedData>().props;
    const current = (locale as SupportedLocale) ?? i18n.language;

    const handleChange = (next: SupportedLocale) => {
        if (next === current) {
            return;
        }

        setLocaleCookie(next);
        i18n.changeLanguage(next);

        // Reload via Inertia so backend-rendered strings (flash messages,
        // validation errors) pick up the new locale too, not just the
        // frontend-owned strings that i18next already swapped in place.
        router.reload();
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="sm" tooltip={t('language.switcher')}>
                            <Languages className="size-4 shrink-0" />
                            <span className="truncate">{t(`language.${current}`)}</span>
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-48">
                        {SUPPORTED_LOCALES.map((code) => (
                            <DropdownMenuItem key={code} onClick={() => handleChange(code)}>
                                {t(`language.${code}`)}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
