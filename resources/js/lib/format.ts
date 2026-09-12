const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
];

const relativeFormatter = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

/**
 * "3 hours ago", "2 days ago", falling back to "just now" for anything under
 * a minute. Native Intl, no date library.
 */
export function formatRelativeTime(iso: string): string {
    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;

    for (const [unit, unitSeconds] of RELATIVE_UNITS) {
        if (Math.abs(seconds) >= unitSeconds) {
            return relativeFormatter.format(
                Math.round(seconds / unitSeconds),
                unit,
            );
        }
    }

    return 'just now';
}

const timeFormatter = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
});

const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

/**
 * A message timestamp: bare time for today, date and time otherwise.
 */
export function formatTimestamp(iso: string): string {
    const date = new Date(iso);
    const today = new Date();
    const isToday = date.toDateString() === today.toDateString();

    return (isToday ? timeFormatter : dateTimeFormatter).format(date);
}

const BYTE_UNITS = ['B', 'KB', 'MB', 'GB'];

/**
 * "128 B", "3.4 MB": a file size for a chip or an attachment link.
 */
export function formatBytes(bytes: number): string {
    let value = bytes;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < BYTE_UNITS.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    const precision = unitIndex === 0 || value >= 10 ? 0 : 1;

    return `${value.toFixed(precision)} ${BYTE_UNITS[unitIndex]}`;
}
