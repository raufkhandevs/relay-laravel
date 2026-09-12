import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="bg-ground flex min-h-svh flex-col items-center justify-center gap-8 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="text-ink text-lg font-semibold tracking-tight"
                        >
                            Relay
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-ink text-xl font-medium">
                                {title}
                            </h1>
                            <p className="text-muted-foreground text-center text-sm">
                                {description}
                            </p>
                        </div>
                    </div>
                    <div className="border-rule bg-surface rounded-lg border p-8 shadow-sm">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
