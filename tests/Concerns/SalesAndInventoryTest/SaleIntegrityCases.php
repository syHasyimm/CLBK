<?php

namespace Tests\Concerns\SalesAndInventoryTest;

use App\Domain\Inventory\Stock;
use App\Models\SortLot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait SaleIntegrityCases
{
    public function test_factory_reversal_blocks_active_sort_sale_then_reverses_on_current_date(): void
    {
        $factory = $this->factoryPosted();
        $sort = $this->postSale($this->draft('sort'));
        $this->post(route('factory-sales.reverse', ['sale' => $factory->id]), $this->key(['version' => $factory->version, 'reason' => 'Salah nota']))->assertSessionHasErrors('operation');
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $this->post(route('sort-sales.reverse', ['sale' => $sort->id]), $this->key(['version' => $sort->version, 'reason' => 'Koreksi turunan']))->assertSessionHasNoErrors();
        $this->post(route('factory-sales.reverse', ['sale' => $factory->id]), $this->key(['version' => $factory->version, 'reason' => 'Salah nota']))->assertSessionHasNoErrors();
        $this->assertSame(3210000, (int) DB::table('profit_entries')->where('business_date', '2026-10-09')->where('category', 'revenue')->sum('amount_delta_idr'));
        $this->assertSame(-3210000, (int) DB::table('profit_entries')->where('business_date', '2026-10-10')->where('category', 'revenue')->sum('amount_delta_idr'));
        $this->assertSame(0, app(Stock::class)->balance(SortLot::firstOrFail())['grams']);
        $this->assertSame('voided', SortLot::firstOrFail()->status);
    }

    public function test_post_and_reverse_replays_once_and_changed_payload_rejected(): void
    {
        $this->weigh();
        $this->dispatch();
        $sale = $this->draft('factory');
        $data = $this->key(['version' => $sale->version]);
        $this->post(route('factory-sales.post', ['sale' => $sale->id]), $data)->assertSessionHasNoErrors();
        $this->post(route('factory-sales.post', ['sale' => $sale->id]), $data)->assertSessionHasNoErrors();
        $this->post(route('factory-sales.post', ['sale' => $sale->id]), array_replace($data, ['version' => 99]))->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('factory_returns', 1);
        $this->assertDatabaseCount('profit_entries', 3);
        $reverse = $this->key(['version' => $sale->fresh()->version, 'reason' => 'Koreksi']);
        $this->post(route('factory-sales.reverse', ['sale' => $sale->id]), $reverse)->assertSessionHasNoErrors();
        $this->post(route('factory-sales.reverse', ['sale' => $sale->id]), $reverse)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('profit_entries', 6);
        $this->assertDatabaseCount('sort_stock_movements', 2);
    }

    public function test_posted_sales_items_and_profit_ledger_are_immutable_and_foreign_keys_restrict(): void
    {
        $factory = $this->factoryPosted();
        $sort = $this->postSale($this->draft('sort'));
        foreach ([fn () => $factory->update(['accepted_grams' => 1]), fn () => $sort->items()->update(['weight_grams' => 1]), fn () => DB::table('profit_entries')->delete(), fn () => DB::table('sort_stock_movements')->update(['quantity_delta_grams' => 1]), fn () => $factory->delete(), fn () => $this->factory->delete()] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected constraint');
            } catch (QueryException $e) {
                $this->assertContains((int) $e->errorInfo[1], [1644, 1451]);
            }
        }
        $this->assertForeignKeysAreValid();
    }

    public function test_private_proofs_validate_content_size_and_authorized_download(): void
    {
        Storage::fake('local');
        $this->dispatch();
        $draft = $this->draft('factory');
        $this->post(route('factory-sales.proofs', ['sale' => $draft->id]), ['proof' => UploadedFile::fake()->create('wrong.pdf', 1, 'text/plain')])->assertSessionHasErrors('proof');
        $this->post(route('factory-sales.proofs', ['sale' => $draft->id]), ['proof' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('proof');
        $file = UploadedFile::fake()->createWithContent('nota.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->post(route('factory-sales.proofs', ['sale' => $draft->id]), ['proof' => $file])->assertSessionHasNoErrors();
        $attachment = DB::table('attachments')->first();
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->get(route('sale-proofs.download', $attachment->id))->assertDownload('nota.pdf');
        $this->actingAs(User::factory()->create())->get(route('sale-proofs.download', $attachment->id))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('sale-proofs.download', $attachment->id))->assertForbidden();
        $this->actingAs($this->manager);
        $this->postSale($draft);
        $this->actingAs($this->cashier)->get(route('sale-proofs.download', $attachment->id))->assertDownload('nota.pdf');
    }
}
