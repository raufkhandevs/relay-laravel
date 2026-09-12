import { Paperclip, X } from 'lucide-react';
import { type ChangeEvent, type KeyboardEvent, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { ALLOWED_ATTACHMENT_MIMES } from '@/lib/attachments';
import { formatBytes } from '@/lib/format';

type Props = {
    file: File | null;
    fileError: string | null;
    onFileChange: (file: File | null) => void;
    onSend: (body: string) => void;
};

const ACCEPT = ALLOWED_ATTACHMENT_MIMES.join(',');

export function MessageComposer({
    file,
    fileError,
    onFileChange,
    onSend,
}: Props) {
    const [body, setBody] = useState('');
    const fileInputRef = useRef<HTMLInputElement>(null);
    const trimmed = body.trim();
    const canSend = (trimmed.length > 0 || file !== null) && !fileError;

    const submit = () => {
        if (!canSend) {
            return;
        }

        onSend(trimmed);
        setBody('');
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }
    };

    const onFileInputChange = (event: ChangeEvent<HTMLInputElement>) => {
        onFileChange(event.target.files?.[0] ?? null);
    };

    return (
        <div className="border-rule bg-surface flex flex-col gap-2 border-t p-4">
            {file && (
                <div className="flex items-start gap-2">
                    <div className="border-rule bg-ground flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm">
                        <span className="max-w-48 truncate">{file.name}</span>
                        <span className="text-muted-foreground text-xs">
                            {formatBytes(file.size)}
                        </span>
                        <button
                            type="button"
                            aria-label="Remove attachment"
                            onClick={() => {
                                onFileChange(null);
                                if (fileInputRef.current) {
                                    fileInputRef.current.value = '';
                                }
                            }}
                            className="text-muted-foreground hover:text-ink"
                        >
                            <X className="size-3.5" />
                        </button>
                    </div>
                    {fileError && (
                        <p className="text-destructive self-center text-xs">
                            {fileError}
                        </p>
                    )}
                </div>
            )}

            <div className="flex items-end gap-3">
                <input
                    ref={fileInputRef}
                    type="file"
                    accept={ACCEPT}
                    onChange={onFileInputChange}
                    className="hidden"
                    aria-label="Attach a file"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Attach a file"
                    onClick={() => fileInputRef.current?.click()}
                >
                    <Paperclip />
                </Button>
                <textarea
                    value={body}
                    onChange={(event) => setBody(event.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder="Write a reply. Enter to send, Shift+Enter for a new line."
                    rows={2}
                    className="border-rule bg-background text-ink placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-16 flex-1 resize-none rounded-md border px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                />
                <Button onClick={submit} disabled={!canSend}>
                    Send
                </Button>
            </div>
        </div>
    );
}
