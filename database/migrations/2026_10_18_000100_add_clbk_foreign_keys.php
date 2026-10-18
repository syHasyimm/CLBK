<?php

use App\Support\Database\LegacyMySqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app(LegacyMySqlSchema::class)->adoptExistingSchema()) {
            return;
        }

        $existing = array_column(Schema::getForeignKeys('app_settings'), 'name');
        Schema::table('app_settings', function (Blueprint $table) use ($existing) {
            if (! in_array('app_settings_fk_0', $existing, true)) {
                $table->foreign('updated_by', 'app_settings_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('attachments'), 'name');
        Schema::table('attachments', function (Blueprint $table) use ($existing) {
            if (! in_array('attachments_fk_0', $existing, true)) {
                $table->foreign('uploaded_by', 'attachments_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('audit_logs'), 'name');
        Schema::table('audit_logs', function (Blueprint $table) use ($existing) {
            if (! in_array('audit_logs_fk_0', $existing, true)) {
                $table->foreign('actor_id', 'audit_logs_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('cash_entries'), 'name');
        Schema::table('cash_entries', function (Blueprint $table) use ($existing) {
            if (! in_array('cash_entries_fk_0', $existing, true)) {
                $table->foreign('created_by', 'cash_entries_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('cash_entries_fk_1', $existing, true)) {
                $table->foreign('reversal_of_id', 'cash_entries_fk_1')->references('id')->on('cash_entries')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('cash_entries_fk_2', $existing, true)) {
                $table->foreign('daily_expense_id', 'cash_entries_fk_2')->references('id')->on('daily_expenses')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('cash_entries_fk_3', $existing, true)) {
                $table->foreign('sale_receipt_id', 'cash_entries_fk_3')->references('id')->on('sale_receipts')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('cash_entries_fk_4', $existing, true)) {
                $table->foreign('payroll_payment_id', 'cash_entries_fk_4')->references('id')->on('payroll_payments')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('cash_entries_fk_5', $existing, true)) {
                $table->foreign('customer_payment_id', 'cash_entries_fk_5')->references('id')->on('customer_payments')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('cash_entries_fk_6', $existing, true)) {
                $table->foreign('cash_account_id', 'cash_entries_fk_6')->references('id')->on('cash_accounts')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('customer_payment_allocations'), 'name');
        Schema::table('customer_payment_allocations', function (Blueprint $table) use ($existing) {
            if (! in_array('customer_payment_allocations_fk_0', $existing, true)) {
                $table->foreign('weighing_id', 'customer_payment_allocations_fk_0')->references('id')->on('weighings')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('customer_payment_allocations_fk_1', $existing, true)) {
                $table->foreign('customer_payment_id', 'customer_payment_allocations_fk_1')->references('id')->on('customer_payments')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('customer_payments'), 'name');
        Schema::table('customer_payments', function (Blueprint $table) use ($existing) {
            if (! in_array('customer_payments_fk_0', $existing, true)) {
                $table->foreign('reversed_by', 'customer_payments_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('customer_payments_fk_1', $existing, true)) {
                $table->foreign('created_by', 'customer_payments_fk_1')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('customer_payments_fk_2', $existing, true)) {
                $table->foreign('cash_account_id', 'customer_payments_fk_2')->references('id')->on('cash_accounts')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('customer_payments_fk_3', $existing, true)) {
                $table->foreign('customer_id', 'customer_payments_fk_3')->references('id')->on('customers')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('daily_expenses'), 'name');
        Schema::table('daily_expenses', function (Blueprint $table) use ($existing) {
            if (! in_array('daily_expenses_fk_0', $existing, true)) {
                $table->foreign('reversed_by', 'daily_expenses_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('daily_expenses_fk_1', $existing, true)) {
                $table->foreign('created_by', 'daily_expenses_fk_1')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('daily_expenses_fk_2', $existing, true)) {
                $table->foreign('cash_account_id', 'daily_expenses_fk_2')->references('id')->on('cash_accounts')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('daily_expenses_fk_3', $existing, true)) {
                $table->foreign('trip_id', 'daily_expenses_fk_3')->references('id')->on('trips')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('daily_expenses_fk_4', $existing, true)) {
                $table->foreign('expense_category_id', 'daily_expenses_fk_4')->references('id')->on('expense_categories')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('factory_price_schedules'), 'name');
        Schema::table('factory_price_schedules', function (Blueprint $table) use ($existing) {
            if (! in_array('factory_price_schedules_fk_0', $existing, true)) {
                $table->foreign('published_by', 'factory_price_schedules_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('factory_price_schedules_fk_1', $existing, true)) {
                $table->foreign('factory_id', 'factory_price_schedules_fk_1')->references('id')->on('factories')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('factory_returns'), 'name');
        Schema::table('factory_returns', function (Blueprint $table) use ($existing) {
            if (! in_array('factory_returns_fk_0', $existing, true)) {
                $table->foreign('factory_sale_id', 'factory_returns_fk_0')->references('id')->on('factory_sales')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('factory_sales'), 'name');
        Schema::table('factory_sales', function (Blueprint $table) use ($existing) {
            if (! in_array('factory_sales_fk_0', $existing, true)) {
                $table->foreign('created_by', 'factory_sales_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('factory_sales_fk_1', $existing, true)) {
                $table->foreign('replaces_sale_id', 'factory_sales_fk_1')->references('id')->on('factory_sales')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('factory_sales_fk_2', $existing, true)) {
                $table->foreign('reversed_by', 'factory_sales_fk_2')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('factory_sales_fk_3', $existing, true)) {
                $table->foreign('posted_by', 'factory_sales_fk_3')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('factory_sales_fk_4', $existing, true)) {
                $table->foreign('variance_approved_by', 'factory_sales_fk_4')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('factory_sales_fk_5', $existing, true)) {
                $table->foreign('factory_price_schedule_id', 'factory_sales_fk_5')->references('id')->on('factory_price_schedules')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('factory_sales_fk_6', $existing, true)) {
                $table->foreign('factory_id', 'factory_sales_fk_6')->references('id')->on('factories')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('factory_sales_fk_7', $existing, true)) {
                $table->foreign('trip_id', 'factory_sales_fk_7')->references('id')->on('trips')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('factory_sales_fk_8', $existing, true)) {
                $table->foreign('vehicle_id', 'factory_sales_fk_8')->references('id')->on('vehicles')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('idempotency_requests'), 'name');
        Schema::table('idempotency_requests', function (Blueprint $table) use ($existing) {
            if (! in_array('idempotency_requests_fk_0', $existing, true)) {
                $table->foreign('actor_id', 'idempotency_requests_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('payroll_payment_allocations'), 'name');
        Schema::table('payroll_payment_allocations', function (Blueprint $table) use ($existing) {
            if (! in_array('payroll_payment_allocations_fk_0', $existing, true)) {
                $table->foreign('weighing_loader_id', 'payroll_payment_allocations_fk_0')->references('id')->on('weighing_loaders')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('payroll_payment_allocations_fk_1', $existing, true)) {
                $table->foreign('payroll_payment_id', 'payroll_payment_allocations_fk_1')->references('id')->on('payroll_payments')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('payroll_payments'), 'name');
        Schema::table('payroll_payments', function (Blueprint $table) use ($existing) {
            if (! in_array('payroll_payments_fk_0', $existing, true)) {
                $table->foreign('reversed_by', 'payroll_payments_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('payroll_payments_fk_1', $existing, true)) {
                $table->foreign('created_by', 'payroll_payments_fk_1')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('payroll_payments_fk_2', $existing, true)) {
                $table->foreign('cash_account_id', 'payroll_payments_fk_2')->references('id')->on('cash_accounts')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('payroll_payments_fk_3', $existing, true)) {
                $table->foreign('worker_id', 'payroll_payments_fk_3')->references('id')->on('loading_workers')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('price_schedules'), 'name');
        Schema::table('price_schedules', function (Blueprint $table) use ($existing) {
            if (! in_array('price_schedules_fk_0', $existing, true)) {
                $table->foreign('published_by', 'price_schedules_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('profit_entries'), 'name');
        Schema::table('profit_entries', function (Blueprint $table) use ($existing) {
            if (! in_array('profit_entries_fk_0', $existing, true)) {
                $table->foreign('created_by', 'profit_entries_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('profit_entries_fk_1', $existing, true)) {
                $table->foreign('reversal_of_id', 'profit_entries_fk_1')->references('id')->on('profit_entries')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('profit_entries_fk_2', $existing, true)) {
                $table->foreign('sort_stock_movement_id', 'profit_entries_fk_2')->references('id')->on('sort_stock_movements')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('profit_entries_fk_3', $existing, true)) {
                $table->foreign('daily_expense_id', 'profit_entries_fk_3')->references('id')->on('daily_expenses')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('profit_entries_fk_4', $existing, true)) {
                $table->foreign('sort_sale_id', 'profit_entries_fk_4')->references('id')->on('sort_sales')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('profit_entries_fk_5', $existing, true)) {
                $table->foreign('factory_sale_id', 'profit_entries_fk_5')->references('id')->on('factory_sales')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('profit_entries_fk_6', $existing, true)) {
                $table->foreign('trip_id', 'profit_entries_fk_6')->references('id')->on('trips')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('sale_receipts'), 'name');
        Schema::table('sale_receipts', function (Blueprint $table) use ($existing) {
            if (! in_array('sale_receipts_fk_0', $existing, true)) {
                $table->foreign('reversed_by', 'sale_receipts_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sale_receipts_fk_1', $existing, true)) {
                $table->foreign('created_by', 'sale_receipts_fk_1')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sale_receipts_fk_2', $existing, true)) {
                $table->foreign('cash_account_id', 'sale_receipts_fk_2')->references('id')->on('cash_accounts')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sale_receipts_fk_3', $existing, true)) {
                $table->foreign('sort_sale_id', 'sale_receipts_fk_3')->references('id')->on('sort_sales')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sale_receipts_fk_4', $existing, true)) {
                $table->foreign('factory_sale_id', 'sale_receipts_fk_4')->references('id')->on('factory_sales')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('sort_lots'), 'name');
        Schema::table('sort_lots', function (Blueprint $table) use ($existing) {
            if (! in_array('sort_lots_fk_0', $existing, true)) {
                $table->foreign('trip_id', 'sort_lots_fk_0')->references('id')->on('trips')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_lots_fk_1', $existing, true)) {
                $table->foreign('source_factory_return_id', 'sort_lots_fk_1')->references('id')->on('factory_returns')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_lots_fk_2', $existing, true)) {
                $table->foreign('source_weighing_item_id', 'sort_lots_fk_2')->references('id')->on('weighing_items')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('sort_sale_items'), 'name');
        Schema::table('sort_sale_items', function (Blueprint $table) use ($existing) {
            if (! in_array('sort_sale_items_fk_0', $existing, true)) {
                $table->foreign('lot_id', 'sort_sale_items_fk_0')->references('id')->on('sort_lots')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_sale_items_fk_1', $existing, true)) {
                $table->foreign('sort_sale_id', 'sort_sale_items_fk_1')->references('id')->on('sort_sales')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('sort_sales'), 'name');
        Schema::table('sort_sales', function (Blueprint $table) use ($existing) {
            if (! in_array('sort_sales_fk_0', $existing, true)) {
                $table->foreign('created_by', 'sort_sales_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_sales_fk_1', $existing, true)) {
                $table->foreign('replaces_sale_id', 'sort_sales_fk_1')->references('id')->on('sort_sales')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_sales_fk_2', $existing, true)) {
                $table->foreign('reversed_by', 'sort_sales_fk_2')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_sales_fk_3', $existing, true)) {
                $table->foreign('posted_by', 'sort_sales_fk_3')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_sales_fk_4', $existing, true)) {
                $table->foreign('trip_id', 'sort_sales_fk_4')->references('id')->on('trips')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_sales_fk_5', $existing, true)) {
                $table->foreign('vehicle_id', 'sort_sales_fk_5')->references('id')->on('vehicles')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('sort_stock_movements'), 'name');
        Schema::table('sort_stock_movements', function (Blueprint $table) use ($existing) {
            if (! in_array('sort_stock_movements_fk_0', $existing, true)) {
                $table->foreign('created_by', 'sort_stock_movements_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_stock_movements_fk_1', $existing, true)) {
                $table->foreign('reversal_of_id', 'sort_stock_movements_fk_1')->references('id')->on('sort_stock_movements')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_stock_movements_fk_2', $existing, true)) {
                $table->foreign('sort_sale_item_id', 'sort_stock_movements_fk_2')->references('id')->on('sort_sale_items')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('sort_stock_movements_fk_3', $existing, true)) {
                $table->foreign('lot_id', 'sort_stock_movements_fk_3')->references('id')->on('sort_lots')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('trips'), 'name');
        Schema::table('trips', function (Blueprint $table) use ($existing) {
            if (! in_array('trips_fk_0', $existing, true)) {
                $table->foreign('created_by', 'trips_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('trips_fk_1', $existing, true)) {
                $table->foreign('planned_factory_id', 'trips_fk_1')->references('id')->on('factories')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('trips_fk_2', $existing, true)) {
                $table->foreign('driver_id', 'trips_fk_2')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('trips_fk_3', $existing, true)) {
                $table->foreign('vehicle_id', 'trips_fk_3')->references('id')->on('vehicles')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('users'), 'name');
        Schema::table('users', function (Blueprint $table) use ($existing) {
            if (! in_array('users_fk_0', $existing, true)) {
                $table->foreign('assigned_vehicle_id', 'users_fk_0')->references('id')->on('vehicles')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('vehicles'), 'name');
        Schema::table('vehicles', function (Blueprint $table) use ($existing) {
            if (! in_array('vehicles_fk_0', $existing, true)) {
                $table->foreign('default_driver_id', 'vehicles_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('weighing_items'), 'name');
        Schema::table('weighing_items', function (Blueprint $table) use ($existing) {
            if (! in_array('weighing_items_fk_0', $existing, true)) {
                $table->foreign('weighing_id', 'weighing_items_fk_0')->references('id')->on('weighings')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('weighing_loaders'), 'name');
        Schema::table('weighing_loaders', function (Blueprint $table) use ($existing) {
            if (! in_array('weighing_loaders_fk_0', $existing, true)) {
                $table->foreign('worker_id', 'weighing_loaders_fk_0')->references('id')->on('loading_workers')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('weighing_loaders_fk_1', $existing, true)) {
                $table->foreign('weighing_id', 'weighing_loaders_fk_1')->references('id')->on('weighings')->restrictOnDelete()->noActionOnUpdate();
            }
        });
        $existing = array_column(Schema::getForeignKeys('weighings'), 'name');
        Schema::table('weighings', function (Blueprint $table) use ($existing) {
            if (! in_array('weighings_fk_0', $existing, true)) {
                $table->foreign('created_by', 'weighings_fk_0')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('weighings_fk_1', $existing, true)) {
                $table->foreign('replaces_weighing_id', 'weighings_fk_1')->references('id')->on('weighings')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('weighings_fk_2', $existing, true)) {
                $table->foreign('voided_by', 'weighings_fk_2')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('weighings_fk_3', $existing, true)) {
                $table->foreign('posted_by', 'weighings_fk_3')->references('id')->on('users')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('weighings_fk_4', $existing, true)) {
                $table->foreign('price_schedule_id', 'weighings_fk_4')->references('id')->on('price_schedules')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('weighings_fk_5', $existing, true)) {
                $table->foreign('trip_id', 'weighings_fk_5')->references('id')->on('trips')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('weighings_fk_6', $existing, true)) {
                $table->foreign('vehicle_id', 'weighings_fk_6')->references('id')->on('vehicles')->restrictOnDelete()->noActionOnUpdate();
            }
            if (! in_array('weighings_fk_7', $existing, true)) {
                $table->foreign('customer_id', 'weighings_fk_7')->references('id')->on('customers')->restrictOnDelete()->noActionOnUpdate();
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
