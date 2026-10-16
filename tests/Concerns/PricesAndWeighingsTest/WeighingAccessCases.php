<?php

namespace Tests\Concerns\PricesAndWeighingsTest;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

trait WeighingAccessCases
{
    public function test_cashier_cannot_create_or_post_weighings_or_see_drafts(): void
    {
        $record = $this->draft();
        $this->actingAs($this->cashier)->post(route('weighings.store'), $this->payload())->assertForbidden();
        $this->post(route('weighings.post', $record), $this->posting($record))->assertForbidden();
        $this->post(route('trips.store'), [])->assertForbidden();
        $this->get(route('weighings.show', $record))->assertForbidden();
        $this->get(route('weighings.index'))->assertInertia(fn (Assert $page) => $page->has('weighings.data', 0));
        $record = $this->postTicket($record);
        $this->actingAs($this->cashier)->get(route('weighings.show', $record))->assertOk();
    }

    public function test_driver_cannot_view_other_trips_tickets_or_backdate(): void
    {
        $record = $this->draft();
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('trips.show', $this->trip))->assertForbidden();
        $this->get(route('weighings.show', $record))->assertForbidden();
        $this->get(route('weighings.index'))->assertInertia(fn (Assert $page) => $page->has('weighings.data', 0));
        $this->get(route('legacy-test.references', 'trips'))->assertJsonPath('total', 0);
        $this->actingAs($this->driver)->post(route('weighings.store'), $this->payload())->assertSessionHasErrors('weighed_at');
        $this->post(route('prices.store'), [])->assertForbidden();
    }

    public function test_date_filters_allow_either_boundary_and_reject_reversed_range(): void
    {
        $this->draft();
        $this->get(route('weighings.index', ['to' => '2026-10-09']))->assertInertia(fn (Assert $page) => $page->has('weighings.data', 1));
        $this->get(route('weighings.index', ['from' => '2026-10-10']))->assertInertia(fn (Assert $page) => $page->has('weighings.data', 0));
        $this->get(route('weighings.index', ['from' => '2026-10-10', 'to' => '2026-10-09']))->assertSessionHasErrors('to');
    }

    public function test_draft_creator_is_required_and_inactive_customer_blocks_posting(): void
    {
        $record = $this->draft([], $this->driver);
        $this->actingAs($this->manager)->patch(route('weighings.update', $record), $this->payload(['version' => 1]))->assertForbidden();
        $this->delete(route('weighings.destroy', $record), ['version' => 1])->assertForbidden();
        $this->customer->update(['is_active' => false]);
        $this->actingAs($this->driver)->post(route('weighings.post', $record), $this->posting($record))->assertSessionHasErrors('customer_id');
        $this->assertSame('draft', $record->fresh()->status);
        $this->assertDatabaseCount('sort_lots', 0);
    }
}
