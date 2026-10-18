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
        if (app(LegacyMySqlSchema::class)->adoptTable('payroll_payments')) {
            return;
        }

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_bin');

            $table->bigIncrements('id');
            $table->string('number');
            $table->unsignedBigInteger('worker_id');
            $table->string('period_start');
            $table->string('period_end');
            $table->unsignedBigInteger('cash_account_id');
            $table->dateTime('occurred_at');
            $table->string('business_date');
            $table->bigInteger('amount_idr');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->longText('notes')->nullable();
            $table->longText('backdate_reason')->nullable();
            $table->string('status');
            $table->dateTime('posted_at');
            $table->bigInteger('version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('reversed_at')->nullable();
            $table->string('reversal_business_date')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->longText('reversal_reason')->nullable();
            $table->string('return_reference')->nullable();
            $table->unique(['number'], 'payroll_payments_unique_2');
            $table->index(['worker_id', 'status', 'period_start', 'period_end'], 'finance_payroll_owner');
            $table->index(['business_date', 'status'], 'payroll_payments_date_status');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_chk_1` CHECK (period_end >= period_start)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_chk_2` CHECK (( amount_idr IS NOT NULL ) AND amount_idr BETWEEN 1 AND 100000000000000)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_chk_3` CHECK (method IN ( 'cash' , 'bank_transfer' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_chk_4` CHECK (status IN ( 'posted' , 'reversed' ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_chk_5` CHECK (( version IS NOT NULL ) AND version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_chk_6` CHECK (method = 'cash' OR reference IS NOT NULL AND length( trim( reference ) ) > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_chk_7` CHECK (status = 'posted' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND return_reference IS NOT NULL AND length( trim( reversal_reason ) ) > 0 AND length( trim( return_reference ) ) > 0)
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Schema usaha tidak dapat dihapus melalui rollback. Gunakan backup/restore untuk pemulihan.');
    }
};
