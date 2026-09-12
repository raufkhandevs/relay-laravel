import { Head, usePage } from '@inertiajs/react';
import {
    type DragEvent,
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';
import MessageController from '@/actions/App/Http/Controllers/Api/MessageController';
import { AttachmentView } from '@/components/attachment-view';
import { Button } from '@/components/ui/button';
import { MessageComposer } from '@/components/message-composer';
import { TypingBubble } from '@/components/typing-bubble';
import { useTicketChannel } from '@/hooks/use-ticket-channel';
import { validateAttachment } from '@/lib/attachments';
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
    file: File | null;
    status: 'sending' | 'failed';
    progress: number;
    error: string | null;
};

type SendResult =
    | { ok: true; message: App.Data.MessageData }
    | { ok: false; error: string };

// Hide the bubble this long after the last whisper. Never wait for a
// "stopped typing" message: the sender can close the laptop or lose signal
// mid-word, and this is what keeps the bubble from getting stuck forever.
const TYPING_EXPIRY_MS = 3000;

/**
 * XMLHttpRequest, not fetch: fetch has no way to observe upload progress (only
 * download), and a phone photo over a slow link is exactly the case where sending
 * with no feedback reads as a hang. `upload.onprogress` is XHR-only.
 */
function postMessage(
    url: string,
    formData: FormData,
    onProgress: (percent: number) => void,
): Promise<SendResult> {
    return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.withCredentials = true;
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve({ ok: true, message: JSON.parse(xhr.responseText) });
                return;
            }

            resolve({ ok: false, error: readErrorMessage(xhr) });
        };

        xhr.onerror = () =>
            resolve({
                ok: false,
                error: 'Network error. Check your connection.',
            });

        xhr.send(formData);
    });
}

function readErrorMessage(xhr: XMLHttpRequest): string {
    // The web server rejects an oversized upload before PHP runs, so unlike every
    // other error here, a 413 carries no JSON body to read.
    if (xhr.status === 413) {
        return 'That file is too large. The limit is 10 MB.';
    }

    try {
        const data = JSON.parse(xhr.responseText);
        const fileError: string | undefined = data?.errors?.file?.[0];
        const bodyError: string | undefined = data?.errors?.body?.[0];

        return (
            fileError ??
            bodyError ??
            data?.message ??
            `Send failed: ${xhr.status}`
        );
    } catch {
        return `Send failed: ${xhr.status}`;
    }
}

export default function Show({ ticket, messages: initial }: Props) {
    const { auth } = usePage().props;
    const [messages, setMessages] = useState(initial);
    const [pending, setPending] = useState<PendingMessage[]>([]);
    const [file, setFile] = useState<File | null>(null);
    const [fileError, setFileError] = useState<string | null>(null);
    const [dragActive, setDragActive] = useState(false);
    const dragDepth = useRef(0);
    const [typingName, setTypingName] = useState<string | null>(null);
    const typingExpiryRef = useRef<ReturnType<typeof setTimeout> | null>(null);

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
    }, [messages, pending, typingName]);

    // Cleared and restarted on every whisper, so the bubble only disappears
    // once TYPING_EXPIRY_MS has passed with no further whisper.
    const handleTypingReceived = useCallback(
        (name: string) => {
            if (name === auth.user.name) {
                return;
            }

            setTypingName(name);

            if (typingExpiryRef.current) {
                clearTimeout(typingExpiryRef.current);
            }
            typingExpiryRef.current = setTimeout(() => {
                setTypingName(null);
            }, TYPING_EXPIRY_MS);
        },
        [auth.user.name],
    );

    useEffect(() => {
        return () => {
            if (typingExpiryRef.current) {
                clearTimeout(typingExpiryRef.current);
            }
        };
    }, []);

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

    const { whisperTyping } = useTicketChannel(
        ticket.id,
        append,
        handleTypingReceived,
    );

    const sendTyping = useCallback(() => {
        whisperTyping(auth.user.name);
    }, [whisperTyping, auth.user.name]);

    const send = useCallback(
        (
            body: string,
            attachment: File | null,
            clientId: string = crypto.randomUUID(),
        ) => {
            // A file is allowed with no typed text, but the server's "body" is
            // A photo often needs no caption, so the API accepts a message with a body,
            // a file, or both. Nothing is invented here: an empty body is sent empty.
            const submittedBody = body;

            setPending((current) => [
                ...current.filter((p) => p.clientId !== clientId),
                {
                    clientId,
                    body: submittedBody,
                    file: attachment,
                    status: 'sending',
                    progress: 0,
                    error: null,
                },
            ]);

            const formData = new FormData();
            formData.append('body', submittedBody);
            formData.append('idempotency_key', clientId);
            if (attachment) {
                formData.append('file', attachment);
            }

            // postMessage() always resolves (never rejects: XHR failures are
            // reported as an { ok: false } result), so there is no rejection
            // to hand a .catch to.
            void postMessage(
                MessageController.store(ticket.id).url,
                formData,
                (progress) => {
                    setPending((current) =>
                        current.map((p) =>
                            p.clientId === clientId ? { ...p, progress } : p,
                        ),
                    );
                },
            ).then((result) => {
                if (result.ok) {
                    setPending((current) =>
                        current.filter((p) => p.clientId !== clientId),
                    );
                    append(result.message);
                    return;
                }

                setPending((current) =>
                    current.map((p) =>
                        p.clientId === clientId
                            ? { ...p, status: 'failed', error: result.error }
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
                send(entry.body, entry.file, clientId);
            }
        },
        [pending, send],
    );

    const selectFile = useCallback((selected: File | null) => {
        setFile(selected);
        setFileError(selected ? validateAttachment(selected) : null);
    }, []);

    const handleSend = useCallback(
        (body: string) => {
            send(body, file);
            setFile(null);
            setFileError(null);
        },
        [send, file],
    );

    const onDragOver = (event: DragEvent<HTMLDivElement>) => {
        if (event.dataTransfer.types.includes('Files')) {
            event.preventDefault();
        }
    };

    const onDragEnter = (event: DragEvent<HTMLDivElement>) => {
        if (!event.dataTransfer.types.includes('Files')) {
            return;
        }
        event.preventDefault();
        dragDepth.current += 1;
        setDragActive(true);
    };

    const onDragLeave = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        dragDepth.current = Math.max(0, dragDepth.current - 1);
        if (dragDepth.current === 0) {
            setDragActive(false);
        }
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        dragDepth.current = 0;
        setDragActive(false);
        const dropped = event.dataTransfer.files?.[0];
        if (dropped) {
            selectFile(dropped);
        }
    };

    const status = TICKET_STATUS[ticket.status];

    return (
        <div
            className="relative mx-auto flex h-[calc(100vh-4rem)] max-w-3xl flex-col"
            onDragOver={onDragOver}
            onDragEnter={onDragEnter}
            onDragLeave={onDragLeave}
            onDrop={onDrop}
        >
            <Head title={ticket.subject} />

            {dragActive && (
                <div className="border-accent bg-ground/90 pointer-events-none absolute inset-0 z-10 m-3 flex items-center justify-center rounded-lg border-2 border-dashed">
                    <p className="text-accent text-sm font-medium">
                        Drop to attach
                    </p>
                </div>
            )}

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
                    const hasBody = message.body.trim().length > 0;

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
                                    'flex max-w-lg flex-col gap-2 rounded-lg px-4 py-3 text-sm whitespace-pre-wrap',
                                    mine
                                        ? 'bg-surface-own text-ink'
                                        : 'border-rule bg-surface text-ink border',
                                )}
                            >
                                {hasBody && <p>{message.body}</p>}
                                {message.attachments.map((attachment) => (
                                    <AttachmentView
                                        key={attachment.id}
                                        attachment={attachment}
                                    />
                                ))}
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
                        <div className="bg-surface-own text-ink flex max-w-lg flex-col gap-2 rounded-lg px-4 py-3 text-sm whitespace-pre-wrap opacity-60">
                            {entry.body.trim().length > 0 && (
                                <p>{entry.body}</p>
                            )}
                            {entry.file && (
                                <p className="text-xs">
                                    {entry.file.name}
                                    {entry.status === 'sending' &&
                                        ` - ${entry.progress}%`}
                                </p>
                            )}
                        </div>
                        <p className="text-muted-foreground flex items-center gap-2 px-1 text-xs">
                            {entry.status === 'sending' ? (
                                'Sending…'
                            ) : (
                                <>
                                    <span className="text-destructive">
                                        {entry.error ?? 'Failed to send'}
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

                {typingName && <TypingBubble />}
            </ol>

            <MessageComposer
                file={file}
                fileError={fileError}
                onFileChange={selectFile}
                onSend={handleSend}
                onTyping={sendTyping}
            />
        </div>
    );
}
