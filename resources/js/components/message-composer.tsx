import { type KeyboardEvent, useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    onSend: (body: string) => void;
};

export function MessageComposer({ onSend }: Props) {
    const [body, setBody] = useState('');
    const trimmed = body.trim();

    const submit = () => {
        if (!trimmed) {
            return;
        }

        onSend(trimmed);
        setBody('');
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }
    };

    return (
        <div className="border-rule bg-surface flex items-end gap-3 border-t p-4">
            <textarea
                value={body}
                onChange={(event) => setBody(event.target.value)}
                onKeyDown={onKeyDown}
                placeholder="Write a reply. Enter to send, Shift+Enter for a new line."
                rows={2}
                className="border-rule bg-background text-ink placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-16 flex-1 resize-none rounded-md border px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
            />
            <Button onClick={submit} disabled={!trimmed}>
                Send
            </Button>
        </div>
    );
}
