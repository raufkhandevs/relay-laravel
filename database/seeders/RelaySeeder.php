<?php

namespace Database\Seeders;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class RelaySeeder extends Seeder
{
    public function run(): void
    {
        $agent = User::factory()->create([
            'name' => 'Marcus Webb',
            'email' => 'agent@relay.test',
            'password' => bcrypt('password'),
            'role' => UserRole::Agent,
        ]);

        $customer = User::factory()->create([
            'name' => 'Priya Raman',
            'email' => 'priya@relay.test',
            'password' => bcrypt('password'),
            'role' => UserRole::Customer,
        ]);

        $ticket = Ticket::factory()->for($customer, 'customer')->create([
            'subject' => "Can't export invoices to CSV",
            'status' => TicketStatus::Open,
            'assigned_agent_id' => $agent->id,
        ]);

        $exchange = [
            [$customer, 'The CSV export button spins for a while and then nothing downloads. I have tried Chrome and Safari.'],
            [$agent, 'Thanks Priya. Which date range did you have selected when it failed? That narrows it down a lot.'],
            [$customer, 'Jan 1 to today. It worked fine last month.'],
        ];

        foreach ($exchange as [$author, $body]) {
            Message::factory()->for($ticket)->for($author, 'author')->create(['body' => $body]);
        }

        Ticket::factory()->count(4)->create(['assigned_agent_id' => $agent->id]);
    }
}
