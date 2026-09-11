import { useCallback, useState } from 'react';
import { useTicketChannel } from '@/hooks/use-ticket-channel';

type Props = {
    ticket: App.Data.TicketData;
    messages: App.Data.MessageData[];
};

export default function Show({ ticket, messages: initial }: Props) {
    const [messages, setMessages] = useState(initial);

    const append = useCallback((incoming: App.Data.MessageData) => {
        setMessages((current) =>
            current.some((m) => m.id === incoming.id)
                ? current
                : [...current, incoming],
        );
    }, []);

    useTicketChannel(ticket.id, append);

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-4 p-6">
            <header className="flex items-baseline gap-3">
                <h1 className="text-lg font-semibold">{ticket.subject}</h1>
                <span className="text-muted-foreground font-mono text-xs">
                    TKT-{ticket.id}
                </span>
            </header>

            <ol className="flex flex-col gap-3">
                {messages.map((message) => (
                    <li key={message.id} className="rounded-lg border p-3">
                        <p className="text-sm">{message.body}</p>
                        <p className="text-muted-foreground mt-1 font-mono text-xs">
                            {message.author.name}
                        </p>
                    </li>
                ))}
            </ol>
        </div>
    );
}
