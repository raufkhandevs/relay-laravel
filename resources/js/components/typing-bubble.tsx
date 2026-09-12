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
export function TypingBubble() {
    return (
        <li className="flex flex-col items-start gap-1">
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
