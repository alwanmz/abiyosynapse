<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\TelegramContact;
use App\Models\TelegramConversationState;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\TelegramTicketIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class TelegramTicketIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Ensure telegram bot system user exists
        User::firstOrCreate(
            ['username' => 'telegram-bot'],
            [
                'name' => 'Telegram Bot',
                'email' => 'telegram-bot@system.local',
                'password' => bcrypt('secret'),
                'is_system' => true,
            ]
        );
    }

    public function test_two_images_with_captions_creates_ticket_with_clean_title_and_combined_description(): void
    {
        $client = Client::create([
            'kode' => 'RSU-TELE',
            'nama' => 'RSU Telegram Test',
            'is_active' => true,
        ]);

        $chatId = 123456789;

        TelegramContact::create([
            'chat_id' => $chatId,
            'client_id' => $client->id,
            'telegram_user_id' => 999,
            'telegram_first_name' => 'Budi',
            'telegram_last_name' => 'Staf',
            'verified_at' => now(),
        ]);

        $this->mock(TelegramService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true]);
            $mock->shouldReceive('downloadFile')
                ->andReturn(['contents' => 'fake-image-bytes', 'file_path' => 'photos/test.jpg']);
        });

        $service = app(TelegramTicketIntakeService::class);

        // 1. User starts ticket flow
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => $chatId],
                'text' => '/newticket',
            ],
        ]);

        // 2. User selects category Bug
        $service->handleUpdate([
            'callback_query' => [
                'id' => 'cb1',
                'message' => ['chat' => ['id' => $chatId]],
                'data' => 'cat:bug',
            ],
        ]);

        // 3. User sends Photo 1 with caption (line 1 of description)
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => $chatId],
                'caption' => "Gagal cetak resep RME pasien rawat jalan\nDetail error saat simpan resep",
                'photo' => [
                    ['file_id' => 'photo_1_small', 'file_size' => 100],
                    ['file_id' => 'photo_1_large', 'file_size' => 500],
                ],
            ],
        ]);

        // 4. User sends Photo 2 with second caption
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => $chatId],
                'caption' => 'Foto bukti tambahan layar error 500',
                'photo' => [
                    ['file_id' => 'photo_2_small', 'file_size' => 120],
                    ['file_id' => 'photo_2_large', 'file_size' => 600],
                ],
            ],
        ]);

        // 5. User finishes flow (/selesai)
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => $chatId],
                'text' => '/selesai',
            ],
        ]);

        $ticket = Ticket::where('client_id', $client->id)->first();
        $this->assertNotNull($ticket);

        // Title should be a clean single-line summary (first line of description)
        $this->assertEquals('Gagal cetak resep RME pasien rawat jalan', $ticket->title);

        // Description should retain full text of all image captions
        $this->assertStringContainsString("Gagal cetak resep RME pasien rawat jalan\nDetail error saat simpan resep", $ticket->description);
        $this->assertStringContainsString('Foto bukti tambahan layar error 500', $ticket->description);

        // Both photo attachments should be stored
        $this->assertCount(2, $ticket->attachments);
    }

    public function test_short_first_line_caption_combines_with_second_line_for_title(): void
    {
        $client = Client::create([
            'kode' => 'RSU-TELE2',
            'nama' => 'RSU Telegram Test 2',
            'is_active' => true,
        ]);

        $chatId = 987654321;

        TelegramContact::create([
            'chat_id' => $chatId,
            'client_id' => $client->id,
            'telegram_user_id' => 888,
            'telegram_first_name' => 'Siti',
            'verified_at' => now(),
        ]);

        $this->mock(TelegramService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true]);
            $mock->shouldReceive('downloadFile')
                ->andReturn(['contents' => 'fake-image-bytes-2', 'file_path' => 'photos/test2.jpg']);
        });

        $service = app(TelegramTicketIntakeService::class);

        // 1. User starts ticket flow
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => $chatId],
                'text' => '/newticket',
            ],
        ]);

        // 2. Category selection
        $service->handleUpdate([
            'callback_query' => [
                'id' => 'cb2',
                'message' => ['chat' => ['id' => $chatId]],
                'data' => 'cat:bug',
            ],
        ]);

        // 3. User sends Photo 1 with short caption "Foto 1"
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => $chatId],
                'caption' => 'Foto 1',
                'photo' => [
                    ['file_id' => 'p1', 'file_size' => 100],
                ],
            ],
        ]);

        // 4. User sends Photo 2 with detailed caption
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => $chatId],
                'caption' => 'Gagal simpan bridging SATUSEHAT error code 500',
                'photo' => [
                    ['file_id' => 'p2', 'file_size' => 120],
                ],
            ],
        ]);

        // 5. Finish flow
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => $chatId],
                'text' => '/selesai',
            ],
        ]);

        $ticket = Ticket::where('client_id', $client->id)->first();
        $this->assertNotNull($ticket);

        // Title combines short first line ("Foto 1") with the second line cleanly
        $this->assertEquals('Foto 1 - Gagal simpan bridging SATUSEHAT error code 500', $ticket->title);

        // Full description contains both lines
        $this->assertStringContainsString("Foto 1\n\nGagal simpan bridging SATUSEHAT error code 500", $ticket->description);
        $this->assertCount(2, $ticket->attachments);
    }

    public function test_separate_title_input_and_image_caption_description(): void
    {
        $client = Client::create([
            'kode' => 'RSU-TELE3',
            'nama' => 'RSU Telegram Test 3',
            'is_active' => true,
        ]);

        $chatId = 555666777;

        TelegramContact::create([
            'chat_id' => $chatId,
            'client_id' => $client->id,
            'telegram_user_id' => 777,
            'telegram_first_name' => 'Ahmad',
            'verified_at' => now(),
        ]);

        $this->mock(TelegramService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMessage')->andReturn(['ok' => true]);
            $mock->shouldReceive('downloadFile')
                ->andReturn(['contents' => 'fake-img', 'file_path' => 'photos/test3.jpg']);
        });

        $service = app(TelegramTicketIntakeService::class);

        // 1. /newticket
        $service->handleUpdate([
            'message' => ['chat' => ['id' => $chatId], 'text' => '/newticket'],
        ]);

        // 2. Select Bug
        $service->handleUpdate([
            'callback_query' => ['id' => 'cb3', 'message' => ['chat' => ['id' => $chatId]], 'data' => 'cat:bug'],
        ]);

        // 3. User enters title text when prompted for issue
        $service->handleUpdate([
            'message' => ['chat' => ['id' => $chatId], 'text' => 'Testing aja ini mah (judul)'],
        ]);

        // 4. User sends image with description caption at attachment stage
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => $chatId],
                'caption' => 'ini juga contoh aja (deskripsi)',
                'photo' => [['file_id' => 'p3', 'file_size' => 100]],
            ],
        ]);

        // 5. Finish flow
        $service->handleUpdate([
            'message' => ['chat' => ['id' => $chatId], 'text' => '/selesai'],
        ]);

        $ticket = Ticket::where('client_id', $client->id)->first();
        $this->assertNotNull($ticket);

        // Title should strictly be the title text
        $this->assertEquals('Testing aja ini mah (judul)', $ticket->title);

        // Description should strictly be the caption description without duplicating title text
        $this->assertEquals('ini juga contoh aja (deskripsi)', $ticket->description);
        $this->assertCount(1, $ticket->attachments);
    }
}
