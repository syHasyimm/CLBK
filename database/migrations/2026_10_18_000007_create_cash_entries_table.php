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
        if (app(LegacyMySqlSchema::class)->adoptTable('cash_entries')) {
            return;
        }

        Schema::create('cash_entries', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('number')->nullable();
            $table->unsignedBigInteger('cash_account_id');
            $table->string('direction');
            $table->bigInteger('amount_idr');
            $table->dateTime('occurred_at');
            $table->string('business_date');
            $table->string('kind');
            $table->unsignedBigInteger('customer_payment_id')->nullable();
            $table->unsignedBigInteger('payroll_payment_id')->nullable();
            $table->unsignedBigInteger('sale_receipt_id')->nullable();
            $table->unsignedBigInteger('daily_expense_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->string('reference')->nullable();
            $table->longText('reason')->nullable();
            $table->longText('backdate_reason')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unique(['reversal_of_id'], 'cash_entries_unique_3');
            $table->unique(['daily_expense_id'], 'cash_entries_unique_4');
            $table->unique(['sale_receipt_id'], 'cash_entries_unique_5');
            $table->unique(['payroll_payment_id'], 'cash_entries_unique_6');
            $table->unique(['customer_payment_id'], 'cash_entries_unique_7');
            $table->unique(['number'], 'cash_entries_unique_8');
            $table->index(['cash_account_id', 'occurred_at', 'id'], 'cash_entries_account_time');
            $table->index(['business_date', 'kind'], 'cash_entries_business_date');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_chk_1` CHECK (direction IN ( 'in' , 'out' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_chk_2` CHECK (( amount_idr IS NOT NULL ) AND amount_idr BETWEEN 1 AND 100000000000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_chk_3` CHECK (kind IN ( 'customer' , 'payroll' , 'receipt' , 'expense' , 'opening' , 'capital' , 'withdrawal' , 'reversal' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_chk_4` CHECK (kind IN ( 'customer' , 'payroll' , 'receipt' , 'expense' ) AND reversal_of_id IS NULL AND ( ( customer_payment_id IS NOT NULL ) + ( payroll_payment_id IS NOT NULL ) + ( sale_receipt_id IS NOT NULL ) + ( daily_expense_id IS NOT NULL ) ) = 1 OR kind IN ( 'opening' , 'capital' , 'withdrawal' ) AND reversal_of_id IS NULL AND number IS NOT NULL AND reason IS NOT NULL AND length( trim( reason ) ) > 0 AND customer_payment_id IS NULL AND payroll_payment_id IS NULL AND sale_receipt_id IS NULL AND daily_expense_id IS NULL OR kind = 'reversal' AND reversal_of_id IS NOT NULL AND reason IS NOT NULL AND reference IS NOT NULL AND length( trim( reason ) ) > 0 AND length( trim( reference ) ) > 0 AND customer_payment_id IS NULL AND payroll_payment_id IS NULL AND sale_receipt_id IS NULL AND daily_expense_id IS NULL)
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX `finance_one_opening` ON `cash_entries` ((CASE WHEN kind = 'opening' THEN `cash_account_id` ELSE NULL END))
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
