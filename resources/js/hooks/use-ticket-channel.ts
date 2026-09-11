import { useEcho } from '@laravel/echo-react';

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
 */
export function useTicketChannel(
    ticketId: number,
    onMessage: (message: App.Data.MessageData) => void,
) {
    useEcho<App.Data.MessageData>(
        `ticket.${ticketId}`,
        '.message.created',
        onMessage,
        [ticketId, onMessage],
    );
}
