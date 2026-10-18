<?php

use App\Support\Database\LegacyMySqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (app(LegacyMySqlSchema::class)->adoptTable('profit_entries')) {
            return;
        }

        Schema::create('profit_entries', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('category');
            $table->bigInteger('amount_delta_idr');
            $table->dateTime('occurred_at');
            $table->string('business_date');
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->unsignedBigInteger('factory_sale_id')->nullable();
            $table->unsignedBigInteger('sort_sale_id')->nullable();
            $table->unsignedBigInteger('daily_expense_id')->nullable();
            $table->unsignedBigInteger('sort_stock_movement_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unique(['reversal_of_id'], 'profit_entries_unique_5');
            $table->index(['business_date', 'category'], 'profit_entries_date');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_chk_1` CHECK (category IN ( 'revenue' , 'purchase_cost' , 'loading_cost' , 'operating_expense' , 'stock_loss' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_chk_2` CHECK (( amount_delta_idr IS NOT NULL ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_chk_3` CHECK (reversal_of_id IS NULL AND amount_delta_idr >= 0 AND ( ( factory_sale_id IS NOT NULL ) + ( sort_sale_id IS NOT NULL ) + ( sort_stock_movement_id IS NOT NULL ) + ( daily_expense_id IS NOT NULL ) ) = 1 OR reversal_of_id IS NOT NULL AND amount_delta_idr <= 0 AND factory_sale_id IS NULL AND sort_sale_id IS NULL AND sort_stock_movement_id IS NULL AND daily_expense_id IS NULL)
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX `finance_profit_expense` ON `profit_entries` ((CASE WHEN reversal_of_id IS NULL THEN `daily_expense_id` ELSE NULL END), (CASE WHEN reversal_of_id IS NULL THEN `category` ELSE NULL END))
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX `profit_factory_category` ON `profit_entries` ((CASE WHEN reversal_of_id IS NULL THEN `factory_sale_id` ELSE NULL END), (CASE WHEN reversal_of_id IS NULL THEN `category` ELSE NULL END))
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX `profit_loss_category` ON `profit_entries` ((CASE WHEN reversal_of_id IS NULL THEN `sort_stock_movement_id` ELSE NULL END), (CASE WHEN reversal_of_id IS NULL THEN `category` ELSE NULL END))
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX `profit_sort_category` ON `profit_entries` ((CASE WHEN reversal_of_id IS NULL THEN `sort_sale_id` ELSE NULL END), (CASE WHEN reversal_of_id IS NULL THEN `category` ELSE NULL END))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
