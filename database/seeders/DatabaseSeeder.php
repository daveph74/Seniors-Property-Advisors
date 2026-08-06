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
        $this->call([UserSeeder::class, ContentSeeder::class, SampleContentSeeder::class]);
    }
}
