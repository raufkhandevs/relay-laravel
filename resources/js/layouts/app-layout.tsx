import { AppHeader } from '@/components/app-header';
import type { AppLayoutProps } from '@/types';

export default function AppLayout({ children }: AppLayoutProps) {
    return (
        <div className="bg-background flex min-h-screen flex-col">
            <AppHeader />
            <main className="flex-1">{children}</main>
        </div>
    );
}
