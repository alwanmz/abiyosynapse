import { type SharedData } from '@/types';
import { home } from '@/routes';
import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Sparkles } from 'lucide-react';
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

    const brandName = name || 'AbiyoSynapse';

    return (
        <div className="flex min-h-svh bg-teal-50/60 dark:bg-[#081a16]">
            {/* Left Side: Form */}
            <div className="flex flex-1 flex-col items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-2 font-medium"
                            >
                                <div className="mb-2 flex h-14 w-14 items-center justify-center overflow-hidden rounded-xl bg-teal-600 text-white shadow-lg shadow-teal-600/30 ring-1 ring-teal-700/20 dark:bg-white dark:text-teal-700">
                                    <LayoutDashboard size={26} strokeWidth={2.25} />
                                </div>
                                <span className="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">
                                    {brandName}
                                </span>
                            </Link>

                            <div className="space-y-1 text-center">
                                <h1 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                                    {title}
                                </h1>
                                <p className="text-sm text-zinc-500 dark:text-zinc-400">
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
                <div className="absolute inset-0 h-full w-full overflow-hidden bg-gradient-to-br from-teal-600 via-teal-700 to-teal-950">
                    {/* Subtle pattern overlay */}
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.12),transparent_55%)]" />
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(45,212,191,0.18),transparent_50%)]" />

                    <div className="absolute inset-0 flex flex-col items-center justify-center p-12 text-white">
                        <div className="max-w-md text-center">
                            <div className="mb-6 inline-flex items-center rounded-full bg-white/15 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider backdrop-blur-sm ring-1 ring-white/20">
                                <Sparkles className="mr-2 inline-block h-3 w-3 text-amber-300" />
                                Didukung Kecerdasan Buatan
                            </div>
                            <h2 className="mb-4 text-4xl font-bold tracking-tight">
                                Kelola Akuntansi & Bisnis Anda Lebih Cepat.
                            </h2>
                            <p className="text-lg text-teal-100/90">
                                Bergabung dengan {brandName} untuk mengelola pembukuan, inventori,
                                dan laporan keuangan perusahaan Anda dengan mudah & akurat.
                            </p>
                        </div>
                    </div>

                    {/* Abstract Shapes */}
                    <div className="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-emerald-400/25 blur-[110px]" />
                    <div className="absolute -top-24 -right-24 h-80 w-80 rounded-full bg-teal-300/20 blur-[110px]" />
                </div>
            </div>
        </div>
    );
}
