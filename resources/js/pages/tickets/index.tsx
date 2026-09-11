import { Link } from '@inertiajs/react';

type Props = {
    tickets: { data: App.Data.TicketData[] };
};

export default function Index({ tickets }: Props) {
    if (tickets.data.length === 0) {
        return (
            <div className="mx-auto max-w-2xl p-6">
                <p className="text-muted-foreground text-sm">
                    Nothing open. Start a ticket and we will reply within the
                    hour.
                </p>
            </div>
        );
    }

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-2 p-6">
            <h1 className="text-lg font-semibold">My tickets</h1>
            <ul className="divide-y rounded-lg border">
                {tickets.data.map((ticket) => (
                    <li key={ticket.id}>
                        <Link
                            href={`/tickets/${ticket.id}`}
                            className="hover:bg-muted flex items-center gap-3 p-3"
                        >
                            <span className="font-mono text-xs uppercase">
                                {ticket.status}
                            </span>
                            <span className="text-sm font-medium">
                                {ticket.subject}
                            </span>
                            <span className="text-muted-foreground ml-auto font-mono text-xs">
                                TKT-{ticket.id}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
