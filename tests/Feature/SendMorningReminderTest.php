<?php

namespace Tests\Feature;

use App\Mail\MorningReminderMail;
use App\Models\Client;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendMorningReminderTest extends TestCase
{
    use RefreshDatabase;

    private function staffUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'programmer'], ['display_name' => 'Programmer']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function makeTicket(User $assignee, string $number, string $status = 'inprogress'): Ticket
    {
        $client = Client::create([
            'kode' => 'CLT-'.uniqid(),
            'nama' => 'Klien Test',
            'is_active' => true,
        ]);

        return Ticket::create([
            'client_id' => $client->id,
            'reporter_id' => $assignee->id,
            'assigned_to' => $assignee->id,
            'title' => "Tiket {$number}",
            'ticket_number' => $number,
            'priority' => 'high',
            'status' => $status,
            'request_type' => 'gratis',
        ]);
    }

    public function test_staff_with_open_tickets_receives_email_with_correct_count(): void
    {
        Mail::fake();

        $user = $this->staffUser();
        $this->makeTicket($user, 'TCK-1', 'inprogress');
        $this->makeTicket($user, 'TCK-2', 'todo');
        $this->makeTicket($user, 'TCK-3', 'done'); // selesai — tidak dihitung

        $this->artisan('reminder:send-morning')->assertSuccessful();

        Mail::assertSent(MorningReminderMail::class, function (MorningReminderMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->openTickets->count() === 2;
        });
    }

    public function test_admin_does_not_receive_morning_reminder(): void
    {
        Mail::fake();

        $this->adminUser();

        $this->artisan('reminder:send-morning')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_staff_without_open_tickets_still_receives_email(): void
    {
        Mail::fake();

        $user = $this->staffUser();

        $this->artisan('reminder:send-morning')->assertSuccessful();

        Mail::assertSent(MorningReminderMail::class, function (MorningReminderMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->openTickets->isEmpty();
        });
    }
}
