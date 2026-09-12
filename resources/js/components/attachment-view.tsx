import { Paperclip } from 'lucide-react';
import AttachmentController from '@/actions/App/Http/Controllers/Api/AttachmentController';
import { formatBytes } from '@/lib/format';

type Props = {
    attachment: App.Data.AttachmentData;
};

/**
 * Always links through `/api/attachments/{id}`, never a storage URL: that endpoint
 * runs `TicketPolicy` and only then redirects to a presigned URL (decision 0010).
 * An `<img>` pointed at it works for the same reason a browser tab does: the
 * request follows the redirect on its own, so the resolved, short-lived URL never
 * has to appear in this component or the DOM it renders.
 *
 * `rel="noopener"`, deliberately not the more familiar `"noreferrer"`: Sanctum's
 * `EnsureFrontendRequestsAreStateful` decides whether to authenticate a request by
 * cookie based on its `Referer` header (falling back to `Origin`, which a plain GET
 * navigation does not send). Stripping the referrer would make every click here
 * arrive with neither, so the API sees an unauthenticated request and 401s before
 * `TicketPolicy` ever runs. `noopener` alone still keeps the opened tab from
 * reaching back into this page via `window.opener`.
 */
export function AttachmentView({ attachment }: Props) {
    const href = AttachmentController.show(attachment.id).url;

    if (attachment.mime.startsWith('image/')) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noopener"
                className="border-rule block w-48 max-w-full overflow-hidden rounded-md border"
            >
                {/* A fixed ratio, not the image's own dimensions (unknown until it
                    loads): the box is reserved before the first byte arrives, so
                    nothing on the page shifts once it does. */}
                <img
                    src={href}
                    alt={attachment.original_name}
                    loading="lazy"
                    className="aspect-[4/3] w-full object-cover"
                />
            </a>
        );
    }

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener"
            className="border-rule hover:bg-ground/60 flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
        >
            <Paperclip
                aria-hidden
                className="text-muted-foreground size-4 shrink-0"
            />
            <span className="truncate">{attachment.original_name}</span>
            <span className="text-muted-foreground shrink-0 text-xs">
                {formatBytes(attachment.size_bytes)}
            </span>
        </a>
    );
}
