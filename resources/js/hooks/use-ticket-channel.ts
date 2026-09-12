import { useEcho } from '@laravel/echo-react';
import { useCallback, useEffect } from 'react';

/**
 * Subscribes to `private-ticket.{ticketId}` and invokes `onMessage` for every
 * `message.created` broadcast. `useEcho` (from `@laravel/echo-react`, which is
 * how this project's install:broadcasting wired the client) manages its own
 * cleanup: on unmount or when `ticketId`/`onMessage` change it stops listening
 * and leaves the channel, so navigating between tickets never accumulates
 * subscriptions.
 *
 * The leading dot on `.message.created` is not a typo: it tells Echo the
 * event name is the literal broadcast name from `broadcastAs()` rather than a
 * fully qualified PHP class name. Omitting it is the single most common
 * reason a Laravel broadcast appears to do nothing.
 *
 * Typing rides the same channel as a whisper: `channel.whisper()` and
 * `.listenForWhisper()` go client -> Reverb -> client and never touch
 * Laravel, so there is no event to give `useEcho`'s own `listen()` (that path
 * is for broadcasts Laravel sent). Both sides are wired directly against the
 * channel instance this hook already holds open, ref-counted by
 * `useEcho`, so this adds no second subscription.
 */
export function useTicketChannel(
    ticketId: number,
    onMessage: (message: App.Data.MessageData) => void,
    onTyping: (name: string) => void,
) {
    const { channel } = useEcho<App.Data.MessageData>(
        `ticket.${ticketId}`,
        '.message.created',
        onMessage,
        [ticketId, onMessage],
    );

    useEffect(() => {
        const ch = channel();
        if (!ch) {
            return;
        }

        const listener = ({ name }: { name: string }) => onTyping(name);
        ch.listenForWhisper('typing', listener);

        return () => {
            ch.stopListeningForWhisper('typing', listener);
        };
    }, [channel, onTyping]);

    const whisperTyping = useCallback(
        (name: string) => {
            channel()?.whisper('typing', { name });
        },
        [channel],
    );

    return { whisperTyping };
}
