/**
 * Three dots in a bubble shaped like an incoming message (see
 * docs/decisions/0009-one-design-system-two-densities.md). Rendered as a
 * sibling `<li>` in the thread's own list so the existing follow-the-bottom
 * scroll behaviour carries it into view like any other message.
 *
 * The dot animation lives in resources/css/app.css as `.typing-dot`, staggered
 * here with `animationDelay`, and is held still under
 * `prefers-reduced-motion: reduce`.
 */
export function TypingBubble({ name }: { name: string }) {
    return (
        <li className="flex flex-col items-start gap-1">
            {/*
             * The dots are decoration and are hidden from assistive tech. Without this
             * line a screen reader user gets no indication at all that someone is
             * typing, which is the whole information the bubble carries. Polite, so it
             * waits for a gap rather than interrupting, and it announces once because
             * the component unmounts when typing stops.
             */}
            <span aria-live="polite" className="sr-only">
                {name} is typing
            </span>
            <div className="border-rule bg-surface animate-in fade-in-0 flex w-fit items-center gap-1.5 rounded-lg border px-4 py-3 duration-300">
                <span
                    aria-hidden
                    className="typing-dot bg-muted-foreground size-1.5 rounded-full"
                    style={{ animationDelay: '0ms' }}
                />
                <span
                    aria-hidden
                    className="typing-dot bg-muted-foreground size-1.5 rounded-full"
                    style={{ animationDelay: '150ms' }}
                />
                <span
                    aria-hidden
                    className="typing-dot bg-muted-foreground size-1.5 rounded-full"
                    style={{ animationDelay: '300ms' }}
                />
            </div>
        </li>
    );
}
