/**
 * Status is a 3px coloured left edge, never a pill: decision 0009. One entry
 * per status, used everywhere a ticket's status needs a colour or a label.
 */
export const TICKET_STATUS: Record<
    App.Enums.TicketStatus,
    { label: string; edgeClass: string }
> = {
    open: { label: 'Open', edgeClass: 'bg-status-open' },
    pending: { label: 'Pending', edgeClass: 'bg-status-pending' },
    resolved: { label: 'Resolved', edgeClass: 'bg-status-resolved' },
    closed: { label: 'Closed', edgeClass: 'bg-status-closed' },
};
