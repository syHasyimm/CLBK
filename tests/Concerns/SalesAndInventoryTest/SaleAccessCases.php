<?php

namespace Tests\Concerns\SalesAndInventoryTest;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

trait SaleAccessCases
{
    public function test_driver_posts_own_assigned_sale_at_server_time_but_cannot_reverse_or_edit_manager_draft(): void
    {
        $this->dispatch();
        $draft = $this->draft('factory', [], $this->driver);
        $this->travelTo(Carbon::parse('2026-10-09 03:02:00', 'UTC'));
        $sale = $this->postSale($draft, $this->driver);
        $this->assertSame('2026-10-09 03:02:00', $sale->sold_at->format('Y-m-d H:i:s'));
        $this->post(route('factory-sales.reverse', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'x']))->assertForbidden();
        $this->actingAs($this->manager)->post(route('factory-sales.reverse', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'Koreksi']))->assertSessionHasNoErrors();
        $replacement = $this->draft('factory', ['replaces_sale_id' => $sale->id, 'correction_reason' => 'Perbaikan', 'sold_at' => '2026-10-09T10:02:00']);
        $this->actingAs($this->driver)->get(route('factory-sales.edit', ['sale' => $replacement->id]))->assertForbidden();
    }

    public function test_inactive_approver_and_changed_driver_role_are_rechecked(): void
    {
        $this->dispatch();
        $sale = $this->draft('factory', ['accepted_kg' => '890'], $this->driver);
        $approver = User::factory()->manager()->create();
        $this->actingAs($approver)->post(route('factory-sales.approve', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'Diperiksa']))->assertSessionHasNoErrors();
        $sale = $sale->fresh();
        $approver->update(['is_active' => false]);
        $this->actingAs($this->driver)->post(route('factory-sales.post', ['sale' => $sale->id]), $this->key(['version' => $sale->version]))->assertSessionHasErrors('operation');
        $this->actingAs($this->manager)->post(route('factory-sales.approve', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'Diperiksa ulang']))->assertSessionHasNoErrors();
        $sale = $sale->fresh();
        $managerDraft = $this->draft('sort');
        $this->driver->update(['role' => UserRole::Cashier]);
        $this->actingAs($this->manager)->post(route('factory-sales.post', ['sale' => $sale->id]), $this->key(['version' => $sale->version]))->assertForbidden(); // Manager cannot post another creator's draft.
        $this->assertSame('draft', $sale->fresh()->status);
        $this->post(route('sort-sales.post', ['sale' => $managerDraft->id]), $this->key(['version' => $managerDraft->version, 'preview_hash' => str_repeat('a', 64)]))->assertSessionHasErrors('operation');
    }

    public function test_current_draft_edit_delete_and_sales_date_filters(): void
    {
        $this->dispatch();
        $sale = $this->draft('factory');
        $this->patch(route('factory-sales.update', ['sale' => $sale->id]), $this->payload('factory', ['version' => $sale->version, 'invoice_number' => $sale->invoice_number, 'sell_price_snapshot_idr' => 3400, 'deduction_idr' => 1000, 'deduction_reason' => 'Administrasi']))->assertSessionHasNoErrors();
        $this->assertSame(3149000, $sale->fresh()->revenue_idr);
        $this->delete(route('factory-sales.destroy', ['sale' => $sale->id]), ['version' => $sale->version])->assertSessionHasErrors('operation');
        $this->get(route('factory-sales.index', ['to' => '2026-10-09']))->assertInertia(fn (Assert $p) => $p->has('sales.data', 1));
        $this->get(route('factory-sales.index', ['from' => '2026-10-10']))->assertInertia(fn (Assert $p) => $p->has('sales.data', 0));
        $this->get(route('factory-sales.index', ['from' => '2026-10-10', 'to' => '2026-10-09']))->assertSessionHasErrors('to');
        $this->delete(route('factory-sales.destroy', ['sale' => $sale->id]), ['version' => $sale->fresh()->version])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('factory_sales', ['id' => $sale->id]);
        $new = $this->draft('factory');
        $this->assertNotSame($sale->number, $new->number);
    }

    public function test_creator_can_delete_draft_with_private_proof_and_audit_keeps_reference(): void
    {
        Storage::fake('local');
        $this->dispatch();
        $sale = $this->draft('factory');
        $file = UploadedFile::fake()->createWithContent('nota.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->post(route('factory-sales.proofs', ['sale' => $sale->id]), ['proof' => $file])->assertSessionHasNoErrors();
        $proof = DB::table('attachments')->first();
        $this->delete(route('factory-sales.destroy', ['sale' => $sale->id]), ['version' => $sale->version])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attachments', 0);
        $this->assertDatabaseMissing('factory_sales', ['id' => $sale->id]);
        Storage::disk('local')->assertMissing($proof->storage_path);
        $audit = DB::table('audit_logs')->where('entity_type', 'factory_sales')->where('action', 'delete-draft')->first();
        $this->assertSame('nota.pdf', json_decode($audit->before_json, true, flags: JSON_THROW_ON_ERROR)['proofs'][0]['original_name']);
    }
}
