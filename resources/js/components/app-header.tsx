import { Link, usePage } from '@inertiajs/react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { index as ticketsIndex } from '@/routes/tickets';

export function AppHeader() {
    const { auth } = usePage().props;

    return (
        <header className="border-rule bg-surface border-b">
            <div className="mx-auto flex h-16 max-w-4xl items-center justify-between px-6">
                <Link
                    href={ticketsIndex()}
                    className="text-ink text-base font-semibold tracking-tight"
                >
                    Relay
                </Link>

                {auth.user && (
                    <DropdownMenu>
                        <DropdownMenuTrigger className="text-ink focus-visible:ring-ring rounded-full text-sm font-medium outline-none focus-visible:ring-2">
                            {auth.user.name}
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </header>
    );
}
