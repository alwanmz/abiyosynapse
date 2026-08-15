import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { home } from '@/routes';
import { Link, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    const { name } = usePage<SharedData>().props;

    const brandName = name || 'Nexumi ERP';

    return (
        <div className="flex min-h-svh bg-nx-navy-50 dark:bg-background">
            {/* Left Side: Form */}
            <div className="flex flex-1 flex-col items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-2 font-medium"
                            >
                                <div className="mb-2 flex h-14 w-14 items-center justify-center overflow-hidden rounded-xl border border-nx-navy-200 bg-card p-3 shadow-sm">
                                    <AppLogoIcon className="size-full" />
                                </div>
                                <span className="font-display text-xl font-extrabold tracking-tight text-nx-navy-800 dark:text-foreground">
                                    {brandName}
                                </span>
                            </Link>

                            <div className="space-y-1 text-center">
                                <h1 className="font-display text-2xl font-bold tracking-tight text-nx-navy-800 dark:text-foreground">
                                    {title}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        </div>
                        {children}
                    </div>
                </div>
            </div>

            {/* Right Side: Visual (Only visible on large screens) */}
            <div className="relative hidden w-0 flex-1 lg:block">
                <div className="absolute inset-0 h-full w-full overflow-hidden bg-gradient-to-br from-nx-navy-600 via-nx-navy-700 to-nx-navy-900">
                    {/* Subtle pattern overlay */}
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.12),transparent_55%)]" />
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(20,184,196,0.18),transparent_50%)]" />

                    <div className="absolute inset-0 flex flex-col items-center justify-center p-12 text-white">
                        <div className="max-w-md text-center">
                            <div className="mb-6 inline-flex items-center rounded-full bg-white/15 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider backdrop-blur-sm ring-1 ring-white/20">
                                <Sparkles className="mr-2 inline-block h-3 w-3 text-nx-cyan-300" />
                                Didukung Kecerdasan Buatan
                            </div>
                            <h2 className="mb-4 font-display text-4xl font-bold tracking-tight">
                                Kelola Akuntansi & Bisnis Anda Lebih Cepat.
                            </h2>
                            <p className="text-lg text-nx-cyan-100/90">
                                Bergabung dengan {brandName} untuk mengelola pembukuan, inventori,
                                dan laporan keuangan perusahaan Anda dengan mudah & akurat.
                            </p>
                        </div>
                    </div>

                    {/* Abstract Shapes */}
                    <div className="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-nx-cyan-500/20 blur-[110px]" />
                    <div className="absolute -top-24 -right-24 h-80 w-80 rounded-full bg-nx-navy-300/25 blur-[110px]" />
                </div>
            </div>
        </div>
    );
}
