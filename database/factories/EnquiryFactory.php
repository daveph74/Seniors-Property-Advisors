<?php

namespace Database\Factories;

use App\Enquiries\FindMyAgentOptions;
use App\Models\Enquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0400 111 222',
            'suburb' => 'Glen Iris',
            'message' => 'Helping my mother think about selling.',
            'consented' => true,
            'page_slug' => '/contact',
            'source' => Enquiry::CONTACT_FORM,
        ];
    }

    public function contactForm(): static
    {
        return $this->state(fn () => ['source' => Enquiry::CONTACT_FORM, 'details' => null]);
    }

    /**
     * Keys, not labels — the same shape the form sends, so a factory row and a real submission are
     * indistinguishable to everything downstream.
     */
    public function findMyAgent(array $details = []): static
    {
        return $this->state(fn () => [
            'source' => Enquiry::FIND_MY_AGENT,
            'suburb' => 'Mosman',
            'details' => array_replace([
                'property_type' => array_key_first(FindMyAgentOptions::PROPERTY_TYPES),
                'timeline' => array_key_first(FindMyAgentOptions::TIMELINES),
                'best_time' => array_key_first(FindMyAgentOptions::BEST_TIMES),
                'location' => [
                    'place_id' => 'ChIJ_fma_example',
                    'suburb' => 'Mosman',
                    'state' => 'NSW',
                    'postcode' => '2088',
                    'description' => 'Mosman NSW 2088',
                    'free_text' => false,
                ],
            ], $details),
        ]);
    }

    public function unread(): static
    {
        return $this->state(fn () => ['read_at' => null]);
    }

    public function dealtWith(): static
    {
        return $this->state(fn () => ['status' => Enquiry::DEALT_WITH, 'status_changed_at' => now()]);
    }
}
