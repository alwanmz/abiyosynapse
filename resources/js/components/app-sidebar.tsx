import { CompanySwitcher } from '@/components/company-switcher';
import { LanguageSwitcher } from '@/components/language-switcher';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, manageUsers } from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRightLeft,
    Banknote,
    BookOpen,
    Boxes,
    Building2,
    Calculator,
    ChartArea,
    ClipboardCheck,
    ClipboardList,
    Factory,
    FileSpreadsheet,
    FileScan,
    FileWarning,
    Gauge,
    Wrench,
    HandCoins,
    History,
    Landmark,
    Layers,
    ListChecks,
    ListTree,
    Package,
    PackageCheck,
    PackageOpen,
    Receipt,
    ReceiptText,
    Route,
    ShieldCheck,
    Sparkles,
    ShoppingBag,
    ShoppingCart,
    Truck,
    Undo2,
    Users,
    Wallet,
    Warehouse as WarehouseIcon,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    // Hidden for now — re-enable when public repo / docs are ready.
    // {
    //     title: 'Repository',
    //     href: 'https://github.com/...',
    //     icon: Folder,
    // },
];

export function AppSidebar() {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;
    const userRole = auth.role?.name;

    const overviewNavItems: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: ChartArea,
        },
    ];

    const masterNavItems: NavItem[] = [
        {
            title: t('nav.users'),
            permission: 'users.view',
            href: manageUsers(),
            icon: Users,
        },
        {
            title: t('nav.roles'),
            permission: 'roles.view',
            href: '/roles',
            icon: ShieldCheck,
        },
        {
            title: t('nav.companies'),
            permission: 'companies.view',
            href: '/companies',
            icon: Building2,
        },
    ];

    const masterDataNavItems: NavItem[] = [
        {
            title: t('master:account.title'),
            permission: 'accounts.view',
            href: '/master/accounts',
            icon: Landmark,
        },
        {
            title: t('master:product.title'),
            permission: 'master-data.view',
            href: '/master/products',
            icon: Package,
        },
        {
            title: t('master:uom.title'),
            permission: 'master-data.view',
            href: '/master/unit-of-measures',
            icon: Boxes,
        },
        {
            title: t('master:warehouse.title'),
            permission: 'master-data.view',
            href: '/master/warehouses',
            icon: WarehouseIcon,
        },
        {
            title: t('master:tax_code.title'),
            permission: 'master-data.view',
            href: '/master/tax-codes',
            icon: Receipt,
        },
        {
            title: t('master:supplier.title'),
            permission: 'master-data.view',
            href: '/master/suppliers',
            icon: Truck,
        },
        {
            title: t('master:customer.title'),
            permission: 'master-data.view',
            href: '/master/customers',
            icon: Users,
        },
        {
            title: t('master:currency.title'),
            permission: 'currencies.view',
            href: '/master/currencies',
            icon: Banknote,
        },
    ];

    const inventoryNavItems: NavItem[] = [
        {
            title: t('inventory:stock_overview.title'),
            permission: 'inventory.view',
            href: '/inventory/stock-overview',
            icon: Layers,
        },
        {
            title: t('inventory:stock_opname.title'),
            permission: 'inventory.view',
            href: '/inventory/stock-opnames',
            icon: ClipboardList,
        },
    ];

    const manufacturingNavItems: NavItem[] = [
        {
            title: t('manufacturing:production_order.title'),
            permission: 'manufacturing.view',
            href: '/manufacturing/production-orders',
            icon: Factory,
        },
        {
            title: t('manufacturing:mrp.title'),
            permission: 'manufacturing.view',
            href: '/manufacturing/mrp',
            icon: Calculator,
        },
        {
            title: t('manufacturing:bom.title'),
            permission: 'manufacturing.view',
            href: '/manufacturing/boms',
            icon: ListTree,
        },
        {
            title: t('manufacturing:routing.title'),
            permission: 'manufacturing.view',
            href: '/manufacturing/routings',
            icon: Route,
        },
        {
            title: t('manufacturing:work_center.title'),
            permission: 'manufacturing.view',
            href: '/manufacturing/work-centers',
            icon: WarehouseIcon,
        },
    ];

    const qualityNavItems: NavItem[] = [
        {
            title: t('quality:inspection.title'),
            permission: 'quality.view',
            href: '/quality/inspections',
            icon: ClipboardCheck,
        },
        {
            title: t('quality:ncr.title'),
            permission: 'quality.view',
            href: '/quality/ncrs',
            icon: FileWarning,
        },
    ];

    const maintenanceNavItems: NavItem[] = [
        { title: t('maintenance:nav.equipment'), permission: 'maintenance.view', href: '/maintenance/equipment', icon: Wrench },
        { title: t('maintenance:nav.work_orders'), permission: 'maintenance.view', href: '/maintenance/work-orders', icon: Factory },
        { title: t('maintenance:nav.schedules'), permission: 'maintenance.view', href: '/maintenance/schedules', icon: ClipboardList },
        { title: t('maintenance:nav.readings'), permission: 'maintenance.view', href: '/maintenance/readings', icon: Gauge },
    ];

    const purchasingNavItems: NavItem[] = [
        {
            title: t('purchasing:purchase_request.title'),
            permission: 'purchasing.view',
            href: '/purchasing/purchase-requests',
            icon: ShoppingCart,
        },
        {
            title: t('purchasing:purchase_order.title'),
            permission: 'purchasing.view',
            href: '/purchasing/purchase-orders',
            icon: ClipboardList,
        },
        {
            title: t('purchasing:goods_receipt.title'),
            permission: 'purchasing.view',
            href: '/purchasing/goods-receipts',
            icon: PackageCheck,
        },
        {
            title: t('purchasing:supplier_invoice.title'),
            permission: 'purchasing.view',
            href: '/purchasing/supplier-invoices',
            icon: ReceiptText,
        },
    ];

    const salesNavItems: NavItem[] = [
        {
            title: t('sales:sales_order.title'),
            permission: 'sales.view',
            href: '/sales/sales-orders',
            icon: ShoppingBag,
        },
        {
            title: t('sales:delivery_order.title'),
            permission: 'sales.view',
            href: '/sales/delivery-orders',
            icon: PackageOpen,
        },
        {
            title: t('sales:sales_invoice.title'),
            permission: 'sales.view',
            href: '/sales/sales-invoices',
            icon: FileSpreadsheet,
        },
        {
            title: t('sales:sales_return.title'),
            permission: 'sales.view',
            href: '/sales/sales-returns',
            icon: Undo2,
        },
    ];

    const cashBankNavItems: NavItem[] = [
        {
            title: t('cash-bank:bank_account.title'),
            permission: 'cash-bank.view',
            href: '/cash-bank/bank-accounts',
            icon: Wallet,
        },
        {
            title: t('cash-bank:cash_transaction.title'),
            permission: 'cash-bank.view',
            href: '/cash-bank/cash-transactions',
            icon: ArrowRightLeft,
        },
        {
            title: t('cash-bank:bank_reconciliation.title'),
            permission: 'cash-bank.view',
            href: '/cash-bank/bank-reconciliations',
            icon: ListChecks,
        },
    ];

    const arNavItems: NavItem[] = [
        {
            title: t('ar:ar_receipt.title'),
            permission: 'ar.view',
            href: '/ar/ar-receipts',
            icon: HandCoins,
        },
    ];

    const apNavItems: NavItem[] = [
        {
            title: t('ap:ap_payment.title'),
            permission: 'ap.view',
            href: '/ap/ap-payments',
            icon: Banknote,
        },
    ];

    const fixedAssetNavItems: NavItem[] = [
        {
            title: t('fixed-assets:asset.title'),
            permission: 'fixed-assets.view',
            href: '/fixed-assets',
            icon: Landmark,
        },
    ];

    const reportsNavItems: NavItem[] = [
        {
            title: t('reports:nav.reports'),
            permission: 'reports.view',
            href: '/reports/gl',
            icon: BookOpen,
        },
    ];

    const aiNavItems: NavItem[] = [
        {
            title: t('ai:nav.copilot'),
            permission: 'ai.use',
            href: '/ai/copilot',
            icon: Sparkles,
        },
        {
            title: t('ai:nav.documents'),
            permission: 'ai.documents.view',
            href: '/ai/documents',
            icon: FileScan,
        },
    ];

    const auditNavItems: NavItem[] = [
        {
            title: t('audit:nav.audit_trail'),
            permission: 'audit.view',
            href: '/audit-logs',
            icon: History,
        },
    ];

    const isSuperAdmin = userRole === 'super_admin' || userRole === 'admin';
    const permissions = auth.role?.permissions?.map((permission) => permission.name) ?? [];
    const canView = (permission?: string) =>
        !permission || isSuperAdmin || permissions.includes(permission);
    const filterByPermission = (items: NavItem[]) => items.filter((item) => canView(item.permission));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto min-h-12 items-center py-2"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <CompanySwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={overviewNavItems} label={t('nav.group_overview')} />
                <NavMain items={filterByPermission(masterDataNavItems)} label={t('master:nav.master_data')} />
                <NavMain items={filterByPermission(inventoryNavItems)} label={t('inventory:nav.inventory')} />
                <NavMain items={filterByPermission(manufacturingNavItems)} label={t('manufacturing:nav.manufacturing')} />
                <NavMain items={filterByPermission(qualityNavItems)} label={t('quality:nav.quality')} />
                <NavMain items={filterByPermission(maintenanceNavItems)} label={t('maintenance:nav.group')} />
                <NavMain items={filterByPermission(purchasingNavItems)} label={t('purchasing:nav.purchasing')} />
                <NavMain items={filterByPermission(salesNavItems)} label={t('sales:nav.sales')} />
                <NavMain items={filterByPermission(cashBankNavItems)} label={t('cash-bank:nav.cash_bank')} />
                <NavMain items={filterByPermission(arNavItems)} label={t('ar:nav.ar')} />
                <NavMain items={filterByPermission(apNavItems)} label={t('ap:nav.ap')} />
                <NavMain items={filterByPermission(fixedAssetNavItems)} label={t('fixed-assets:nav.fixed_assets')} />
                <NavMain items={filterByPermission(reportsNavItems)} label={t('reports:nav.reports')} />
                <NavMain items={filterByPermission(aiNavItems)} label={t('ai:nav.group')} />
                <NavMain items={filterByPermission(auditNavItems)} label={t('audit:nav.audit_trail')} />
                <NavMain items={filterByPermission(masterNavItems)} label={t('nav.group_master')} />
            </SidebarContent>

            <SidebarFooter>
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <LanguageSwitcher />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
