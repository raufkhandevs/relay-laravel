import { Head, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import MessageController from '@/actions/App/Http/Controllers/Api/MessageController';
import { Button } from '@/components/ui/button';
import { MessageComposer } from '@/components/message-composer';
import { useTicketChannel } from '@/hooks/use-ticket-channel';
import { formatTimestamp } from '@/lib/format';
import { TICKET_STATUS } from '@/lib/ticket-status';
import { cn } from '@/lib/utils';

type Props = {
    ticket: App.Data.TicketData;
    messages: App.Data.MessageData[];
};

type PendingMessage = {
    clientId: string;
    body: string;
    status: 'sending' | 'failed';
};

export default function Show({ ticket, messages: initial }: Props) {
    const { auth } = usePage().props;
    const [messages, setMessages] = useState(initial);
    const [pending, setPending] = useState<PendingMessage[]>([]);

    // Without this the newest message lands below the fold and sending looks like it
    // silently failed. Only follow when the reader is already at the bottom, so someone
    // scrolled up reading history is not yanked back down every time a message arrives.
    const listRef = useRef<HTMLOListElement>(null);
    const followingRef = useRef(true);

    const trackFollowing = useCallback(() => {
        const list = listRef.current;
        if (!list) {
            return;
        }
        const distanceFromBottom =
            list.scrollHeight - list.scrollTop - list.clientHeight;
        followingRef.current = distanceFromBottom < 80;
    }, []);

    useEffect(() => {
        const list = listRef.current;
        if (list && followingRef.current) {
            list.scrollTop = list.scrollHeight;
        }
    }, [messages, pending]);

    const append = useCallback(
        (incoming: App.Data.MessageData) => {
            setMessages((current) =>
                current.some((m) => m.id === incoming.id)
                    ? current
                    : [...current, incoming],
            );

            // I sent this and it just arrived over the broadcast before my own
            // POST resolved (both happen on the same round trip, so in
            // practice this rarely fires before the success handler below
            // already cleared it). Drop the matching optimistic entry so it
            // is not shown twice.
            if (incoming.author.id === auth.user.id) {
                setPending((current) =>
                    current.filter(
                        (p) =>
                            !(
                                p.status === 'sending' &&
                                p.body === incoming.body
                            ),
                    ),
                );
            }
        },
        [auth.user.id],
    );

    useTicketChannel(ticket.id, append);

    const send = useCallback(
        (body: string, clientId: string = crypto.randomUUID()) => {
            setPending((current) => [
                ...current.filter((p) => p.clientId !== clientId),
                { clientId, body, status: 'sending' },
            ]);

            fetch(MessageController.store(ticket.id).url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ body, idempotency_key: clientId }),
            })
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error(`Send failed: ${response.status}`);
                    }

                    const message: App.Data.MessageData = await response.json();

                    setPending((current) =>
                        current.filter((p) => p.clientId !== clientId),
                    );
                    append(message);
                })
                .catch(() => {
                    setPending((current) =>
                        current.map((p) =>
                            p.clientId === clientId
                                ? { ...p, status: 'failed' }
                                : p,
                        ),
                    );
                });
        },
        [append, ticket.id],
    );

    const retry = useCallback(
        (clientId: string) => {
            const entry = pending.find((p) => p.clientId === clientId);

            if (entry) {
                send(entry.body, clientId);
            }
        },
        [pending, send],
    );

    const status = TICKET_STATUS[ticket.status];

    return (
        <div className="mx-auto flex h-[calc(100vh-4rem)] max-w-3xl flex-col">
            <Head title={ticket.subject} />

            <header className="border-rule flex items-start gap-4 border-b px-6 py-6">
                <span
                    aria-hidden
                    className={cn(
                        'mt-1 h-8 w-[3px] shrink-0',
                        status.edgeClass,
                    )}
                />
                <div>
                    <h1 className="text-ink text-xl font-semibold">
                        {ticket.subject}
                    </h1>
                    <p className="text-muted-foreground mt-1 flex items-center gap-3 text-sm">
                        <span className="font-mono text-xs">
                            TKT-{ticket.id}
                        </span>
                        <span>{status.label}</span>
                    </p>
                </div>
            </header>

            <ol
                ref={listRef}
                onScroll={trackFollowing}
                className="flex flex-1 flex-col gap-4 overflow-y-auto px-6 py-6"
            >
                {messages.map((message) => {
                    const mine = message.author.id === auth.user.id;

                    return (
                        <li
                            key={message.id}
                            className={cn(
                                'flex flex-col gap-1',
                                mine ? 'items-end' : 'items-start',
                            )}
                        >
                            <div
                                className={cn(
                                    'max-w-lg rounded-lg px-4 py-3 text-sm whitespace-pre-wrap',
                                    mine
                                        ? 'bg-surface-own text-ink'
                                        : 'border-rule bg-surface text-ink border',
                                )}
                            >
                                {message.body}
                            </div>
                            <p className="text-muted-foreground px-1 text-xs">
                                {message.author.name}{' '}
                                <span className="font-mono">
                                    {formatTimestamp(message.created_at)}
                                </span>
                            </p>
                        </li>
                    );
                })}

                {pending.map((entry) => (
                    <li
                        key={entry.clientId}
                        className="flex flex-col items-end gap-1"
                    >
                        <div className="bg-surface-own text-ink max-w-lg rounded-lg px-4 py-3 text-sm whitespace-pre-wrap opacity-60">
                            {entry.body}
                        </div>
                        <p className="text-muted-foreground flex items-center gap-2 px-1 text-xs">
                            {entry.status === 'sending' ? (
                                'Sending…'
                            ) : (
                                <>
                                    <span className="text-destructive">
                                        Failed to send
                                    </span>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-accent h-auto p-0 underline-offset-2 hover:underline"
                                        onClick={() => retry(entry.clientId)}
                                    >
                                        Retry
                                    </Button>
                                </>
                            )}
                        </p>
                    </li>
                ))}
            </ol>

            <MessageComposer onSend={send} />
        </div>
    );
}
