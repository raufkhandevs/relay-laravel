declare namespace App {
namespace Data {
export type MessageData = {
id: number,
ticket_id: number,
body: string,
author: App.Data.UserData,
created_at: string,
};
export type TicketData = {
id: number,
subject: string,
status: App.Enums.TicketStatus,
customer: App.Data.UserData,
assigned_agent: App.Data.UserData | null,
last_message_at: string | null,
created_at: string,
};
export type UserData = {
id: number,
name: string,
email: string,
role: App.Enums.UserRole,
};
}
namespace Enums {
export type TicketStatus = 'open' | 'pending' | 'resolved' | 'closed';
export type UserRole = 'customer' | 'agent';
}
}
