/**
 * Mirrors the limits enforced server-side in `StoreMessageRequest`. This copy is a
 * convenience only, so a user finds out before waiting on an upload: the request
 * above is what actually decides, judging the file's real content rather than
 * its name or its claimed type.
 */
export const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

export const ALLOWED_ATTACHMENT_MIMES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/heic',
    'application/pdf',
    'text/plain',
] as const;

export function isAttachmentTypeAllowed(mime: string): boolean {
    return (ALLOWED_ATTACHMENT_MIMES as readonly string[]).includes(mime);
}

/**
 * A readable reason a file will not be accepted, or null when it looks fine.
 * A browser reports a file's type from its name, not its bytes, so this can
 * still let through what the server ultimately refuses (a renamed extension);
 * that gap is why send failures are surfaced too, not just this check.
 */
export function validateAttachment(file: File): string | null {
    if (file.size > MAX_ATTACHMENT_BYTES) {
        return `${file.name} is over the 10 MB limit.`;
    }

    if (!isAttachmentTypeAllowed(file.type)) {
        return `${file.name} isn't a supported file type.`;
    }

    return null;
}
