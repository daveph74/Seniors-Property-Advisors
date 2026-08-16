<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Enquiry;
use Tests\TestCase;

/**
 * Personal data with an end date, and an answer for somebody who asks to be forgotten.
 *
 * The rule these all share: reporting is the default and destroying takes `--force`, because every
 * one of these commands removes something nobody can get back.
 */
class RetentionTest extends TestCase
{
    private function enquiry(array $overrides = []): Enquiry
    {
        $at = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $enquiry = Enquiry::factory()->create($overrides);

        /* `created_at` is not fillable, so it is set after the insert — the same dance
           `CmsEnquiryTest` does for the same reason. */
        $enquiry->forceFill(['created_at' => $at])->save();

        return $enquiry;
    }

    public function test_an_old_enquiry_is_reported_before_it_is_removed(): void
    {
        $this->enquiry(['created_at' => now()->subMonths(30)]);

        $this->artisan('enquiries:purge --months=24')
            ->expectsOutputToContain('Would remove')
            ->assertSuccessful();

        $this->assertSame(1, Enquiry::count(), 'a report must not delete anything');
    }

    public function test_forcing_it_removes_only_what_is_past_the_line(): void
    {
        $old = $this->enquiry(['created_at' => now()->subMonths(30), 'name' => 'Long ago']);
        $recent = $this->enquiry(['created_at' => now()->subMonths(3), 'name' => 'Recent']);

        $this->artisan('enquiries:purge --months=24 --force')->assertSuccessful();

        $this->assertNull(Enquiry::find($old->id));
        $this->assertNotNull(Enquiry::find($recent->id));
    }

    public function test_the_retention_period_is_the_business_decision_it_looks_like(): void
    {
        $this->enquiry(['created_at' => now()->subMonths(9)]);

        $this->artisan('enquiries:purge --months=12 --force')->assertSuccessful();
        $this->assertSame(1, Enquiry::count());

        $this->artisan('enquiries:purge --months=6 --force')->assertSuccessful();
        $this->assertSame(0, Enquiry::count());
    }

    public function test_purging_records_that_one_went_and_not_whose_it_was(): void
    {
        $enquiry = $this->enquiry(['created_at' => now()->subMonths(30), 'name' => 'Janet Reid']);
        $reference = $enquiry->reference();

        $this->artisan('enquiries:purge --months=24 --force')->assertSuccessful();

        $this->assertDatabaseHas('activity_log', ['subject_type' => 'Enquiry', 'action' => 'deleted']);
        /* The same rule the delete route follows: erasing somebody while minting a permanent copy of
           their name is not erasing them. */
        $this->assertDatabaseMissing('activity_log', ['subject_label' => 'Janet Reid']);
        $this->assertDatabaseHas('activity_log', ['subject_label' => $reference.' (retention)']);
    }

    public function test_an_erasure_request_takes_every_enquiry_that_person_sent(): void
    {
        $this->enquiry(['email' => 'janet@example.com', 'name' => 'Janet']);
        $this->enquiry(['email' => 'JANET@example.com', 'name' => 'Janet again']);
        $this->enquiry(['email' => 'someone@example.com', 'name' => 'Somebody else']);

        $this->artisan('enquiries:erase janet@example.com')->assertSuccessful();
        $this->assertSame(3, Enquiry::count(), 'a report must not delete anything');

        $this->artisan('enquiries:erase janet@example.com --force')->assertSuccessful();

        /* Both of hers, whatever case she typed it in, and nobody else's. */
        $this->assertSame(1, Enquiry::count());
        $this->assertSame('someone@example.com', Enquiry::sole()->email);
    }

    public function test_an_erasure_needs_an_address_that_could_be_one(): void
    {
        $this->enquiry();

        $this->artisan('enquiries:erase not-an-address --force')->assertFailed();

        $this->assertSame(1, Enquiry::count());
    }

    public function test_the_audit_log_stops_holding_a_former_employee_forever(): void
    {
        Activity::create([
            'action' => 'edited', 'subject_type' => 'Page', 'subject_id' => 1,
            'subject_label' => 'Home', 'by_id' => null, 'by_name' => 'Somebody who left',
        ])->forceFill(['created_at' => now()->subMonths(30)])->save();

        $recent = Activity::create([
            'action' => 'edited', 'subject_type' => 'Page', 'subject_id' => 1,
            'subject_label' => 'Home', 'by_id' => null, 'by_name' => 'Still here',
        ]);

        $this->artisan('activity:prune --months=24')->assertSuccessful();
        $this->assertDatabaseHas('activity_log', ['by_name' => 'Somebody who left']);

        $this->artisan('activity:prune --months=24 --force')->assertSuccessful();

        $this->assertDatabaseMissing('activity_log', ['by_name' => 'Somebody who left']);
        $this->assertNotNull(Activity::find($recent->id));
    }
}
