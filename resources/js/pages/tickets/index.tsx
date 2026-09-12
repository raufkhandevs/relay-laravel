import { Head, Link } from '@inertiajs/react';
import { formatRelativeTime } from '@/lib/format';
import { TICKET_STATUS } from '@/lib/ticket-status';
import { show } from '@/routes/tickets';

type Props = {
    tickets: { data: App.Data.TicketData[] };
};

export default function Index({ tickets }: Props) {
    return (
        <div className="mx-auto max-w-4xl px-6 py-10">
            <Head title="My tickets" />

            <h1 className="text-ink mb-6 text-2xl font-semibold">My tickets</h1>

            {tickets.data.length === 0 ? (
                <p className="text-muted-foreground max-w-md text-sm">
                    Nothing open. Start a ticket and we will reply within the
                    hour.
                </p>
            ) : (
                <ul className="flex flex-col gap-3">
                    {tickets.data.map((ticket) => {
                        const status = TICKET_STATUS[ticket.status];

                        return (
                            <li
                                key={ticket.id}
                                className="border-rule bg-surface flex overflow-hidden rounded-lg border"
                            >
                                <span
                                    aria-hidden
                                    className={`w-[3px] shrink-0 ${status.edgeClass}`}
                                />
                                <Link
                                    href={show(ticket.id)}
                                    className="hover:bg-muted/60 flex flex-1 items-center gap-5 py-5 pr-5 pl-4"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="text-ink truncate text-base font-medium">
                                            {ticket.subject}
                                        </p>
                                        <p className="text-muted-foreground mt-1 font-mono text-xs">
                                            TKT-{ticket.id}
                                        </p>
                                    </div>
                                    <span className="text-muted-foreground shrink-0 text-sm">
                                        {status.label}
                                    </span>
                                    <span className="text-muted-foreground w-28 shrink-0 text-right font-mono text-xs">
                                        {formatRelativeTime(
                                            ticket.last_message_at ??
                                                ticket.created_at,
                                        )}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
