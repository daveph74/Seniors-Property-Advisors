<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /* SampleContentSeeder is not in Tests\TestCase, which seeds ContentSeeder alone —
           articles and questions in the seed every test runs would break the counts they assert. */
        /* MediaSeeder before ContentSeeder: the pages name /media/ addresses, and a page that
           renders before the rows exist shows broken images. It needs the storage service up. */
        $this->call([UserSeeder::class, MediaSeeder::class, ContentSeeder::class, SampleContentSeeder::class]);
    }
}
