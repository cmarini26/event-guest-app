<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Clean up any previous seed run
        User::whereIn('email', ['demo@example.com', 'jane@example.com'])->delete();
        Event::whereIn('slug', ['summer-rooftop-party', 'q4-team-offsite', 'new-years-eve-dinner', 'janes-private-event'])->delete();

        // Primary demo user — use this to explore the app (is_admin grants admin panel access)
        $demo = User::factory()->create([
            'name'     => 'Demo User',
            'email'    => 'demo@example.com',
            'password' => Hash::make('password'),
            'plan'     => 'free',
            'is_admin' => true,
        ]);

        // Published event with a full mix of RSVP statuses
        $summer = Event::factory()->create([
            'user_id'               => $demo->id,
            'name'                  => 'Summer Rooftop Party',
            'slug'                  => 'summer-rooftop-party',
            'description'           => 'An evening of drinks, music, and great company on the rooftop.',
            'starts_at'             => now()->addDays(30)->setTime(18, 0),
            'ends_at'               => now()->addDays(30)->setTime(22, 0),
            'timezone'              => 'America/New_York',
            'venue_name'            => 'The Rooftop at 5th',
            'venue_address'         => '123 5th Avenue, New York, NY 10001',
            'status'                => 'published',
            'rsvp_deadline'         => now()->addDays(25)->setTime(23, 59),
            'allow_plus_ones'       => true,
            'max_plus_ones_per_guest' => 1,
            'collect_dietary'       => true,
            'collect_accessibility' => true,
            'collect_seating'       => false,
            'require_phone'         => false,
            'max_guests'            => null,
        ]);

        $this->seedGuests($summer, [
            ['first_name' => 'Alice',   'last_name' => 'Chen',     'email' => 'alice@example.com',   'status' => 'attending', 'dietary' => 'Vegetarian', 'invited' => true, 'plus_one' => 'Bob Chen'],
            ['first_name' => 'Carlos',  'last_name' => 'Rivera',   'email' => 'carlos@example.com',  'status' => 'attending', 'dietary' => null,          'invited' => true],
            ['first_name' => 'Diana',   'last_name' => 'Park',     'email' => 'diana@example.com',   'status' => 'attending', 'dietary' => 'Gluten-free', 'invited' => true],
            ['first_name' => 'Ethan',   'last_name' => 'Moore',    'email' => 'ethan@example.com',   'status' => 'attending', 'dietary' => null,          'invited' => true],
            ['first_name' => 'Fatima',  'last_name' => 'Hassan',   'email' => 'fatima@example.com',  'status' => 'attending', 'dietary' => 'Halal',       'invited' => true, 'plus_one' => 'Yusuf Hassan'],
            ['first_name' => 'George',  'last_name' => 'Williams', 'email' => 'george@example.com',  'status' => 'declined',  'dietary' => null,          'invited' => true],
            ['first_name' => 'Hannah',  'last_name' => 'Lee',      'email' => 'hannah@example.com',  'status' => 'declined',  'dietary' => null,          'invited' => true],
            ['first_name' => 'Ivan',    'last_name' => 'Petrov',   'email' => 'ivan@example.com',    'status' => 'pending',   'dietary' => null,          'invited' => true],
            ['first_name' => 'Julia',   'last_name' => 'Santos',   'email' => 'julia@example.com',   'status' => 'pending',   'dietary' => null,          'invited' => true],
            ['first_name' => 'Kevin',   'last_name' => 'Brown',    'email' => 'kevin@example.com',   'status' => 'waitlisted','dietary' => null,          'invited' => true],
            ['first_name' => 'Lily',    'last_name' => 'Taylor',   'email' => 'lily@example.com',    'status' => 'pending',   'dietary' => null,          'invited' => false],
            ['first_name' => 'Marcus',  'last_name' => 'Johnson',  'email' => null,                   'status' => 'pending',   'dietary' => null,          'invited' => false],
        ]);

        // Draft event — not yet published
        Event::factory()->create([
            'user_id'     => $demo->id,
            'name'        => 'Q4 Team Offsite',
            'slug'        => 'q4-team-offsite',
            'description' => 'Annual planning offsite for the whole team.',
            'starts_at'   => now()->addDays(60)->setTime(9, 0),
            'ends_at'     => now()->addDays(61)->setTime(17, 0),
            'timezone'    => 'America/Chicago',
            'venue_name'  => 'The Lake House',
            'status'      => 'draft',
        ]);

        // Archived event — past event
        $archived = Event::factory()->create([
            'user_id'     => $demo->id,
            'name'        => 'New Year\'s Eve Dinner',
            'slug'        => 'new-years-eve-dinner',
            'starts_at'   => now()->subDays(180)->setTime(19, 0),
            'ends_at'     => now()->subDays(180)->setTime(23, 59),
            'timezone'    => 'America/New_York',
            'venue_name'  => 'Private Residence',
            'status'      => 'archived',
            'collect_dietary' => true,
        ]);

        $this->seedGuests($archived, [
            ['first_name' => 'Sophie', 'last_name' => 'Martin',  'email' => 'sophie@example.com', 'status' => 'attending', 'dietary' => 'Vegan',  'invited' => true],
            ['first_name' => 'Tom',    'last_name' => 'Wilson',  'email' => 'tom@example.com',    'status' => 'attending', 'dietary' => null,      'invited' => true],
            ['first_name' => 'Uma',    'last_name' => 'Patel',   'email' => 'uma@example.com',    'status' => 'declined',  'dietary' => null,      'invited' => true],
        ]);

        // Second user — for testing data isolation
        $other = User::factory()->create([
            'name'     => 'Jane Host',
            'email'    => 'jane@example.com',
            'password' => Hash::make('password'),
            'plan'     => 'free',
        ]);

        Event::factory()->create([
            'user_id' => $other->id,
            'name'    => 'Jane\'s Private Event',
            'slug'    => 'janes-private-event',
            'status'  => 'published',
        ]);
    }

    private function seedGuests(Event $event, array $guests): void
    {
        foreach ($guests as $g) {
            $responded = in_array($g['status'], ['attending', 'declined', 'waitlisted']);
            $guest = Guest::factory()->create([
                'event_id'          => $event->id,
                'first_name'        => $g['first_name'],
                'last_name'         => $g['last_name'],
                'email'             => $g['email'],
                'rsvp_status'       => $g['status'],
                'dietary_preference'=> $g['dietary'] ?? null,
                'responded_at'      => $responded ? now()->subDays(rand(1, 10)) : null,
                'invited_at'        => $g['invited'] ? now()->subDays(rand(3, 14)) : null,
            ]);

            if (isset($g['plus_one']) && $g['status'] === 'attending') {
                $guest->plusOnes()->create(['name' => $g['plus_one'], 'dietary_preference' => null]);
            }
        }
    }
}
