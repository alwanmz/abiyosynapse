import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import company from '@/routes/company';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';

export function CompanySwitcher() {
    const { currentCompany, companies } = usePage<SharedData>().props;

    if (!currentCompany) {
        return null;
    }

    const handleSwitch = (companyId: number) => {
        if (companyId === currentCompany.id) {
            return;
        }
        router.post(
            company.switch.url(),
            { company_id: companyId },
            { preserveScroll: true },
        );
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="lg">
                            <Building2 className="size-4 shrink-0" />
                            <span className="truncate font-medium">{currentCompany.name}</span>
                            <ChevronsUpDown className="ml-auto size-4 shrink-0" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-64">
                        <DropdownMenuLabel>Perusahaan</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {companies.map((company) => (
                            <DropdownMenuItem key={company.id} onClick={() => handleSwitch(company.id)}>
                                {company.id === currentCompany.id && <Check className="mr-2 size-4" />}
                                {company.name}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
