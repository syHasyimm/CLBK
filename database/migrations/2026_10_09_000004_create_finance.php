<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            DB::transaction(function () {
                $triggers = $this->removeTriggers();
                $sources = [
                    'customer_payments' => 'customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE RESTRICT,',
                    'payroll_payments' => 'worker_id INTEGER NOT NULL REFERENCES loading_workers(id) ON DELETE RESTRICT, period_start TEXT NOT NULL, period_end TEXT NOT NULL CHECK(period_end>=period_start),',
                    'sale_receipts' => 'factory_sale_id INTEGER REFERENCES factory_sales(id) ON DELETE RESTRICT, sort_sale_id INTEGER REFERENCES sort_sales(id) ON DELETE RESTRICT, CHECK((factory_sale_id IS NOT NULL)+(sort_sale_id IS NOT NULL)=1),',
                    'daily_expenses' => 'expense_category_id INTEGER NOT NULL REFERENCES expense_categories(id) ON DELETE RESTRICT, trip_id INTEGER REFERENCES trips(id) ON DELETE RESTRICT, description TEXT NOT NULL CHECK(length(trim(description))>0),',
                ];
                foreach ($sources as $table => $specific) {
                    // Table CHECK clauses follow all column definitions in SQLite.
                    $receiptCheck = $table === 'sale_receipts' ? ', CHECK((factory_sale_id IS NOT NULL)+(sort_sale_id IS NOT NULL)=1)' : '';
                    $specific = str_replace(' CHECK((factory_sale_id IS NOT NULL)+(sort_sale_id IS NOT NULL)=1),', '', $specific);
                    DB::unprepared("CREATE TABLE {$table} (
id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT NOT NULL UNIQUE, {$specific}
cash_account_id INTEGER NOT NULL REFERENCES cash_accounts(id) ON DELETE RESTRICT,
occurred_at DATETIME NOT NULL, business_date TEXT NOT NULL,
amount_idr INTEGER NOT NULL CHECK(typeof(amount_idr)='integer' AND amount_idr BETWEEN 1 AND 100000000000000),
method TEXT NOT NULL CHECK(method IN ('cash','bank_transfer')), reference TEXT, notes TEXT, backdate_reason TEXT,
status TEXT NOT NULL CHECK(status IN ('posted','reversed')), posted_at DATETIME NOT NULL,
version INTEGER NOT NULL DEFAULT 1 CHECK(typeof(version)='integer' AND version>0),
created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT, created_at DATETIME, updated_at DATETIME,
reversed_at DATETIME, reversal_business_date TEXT, reversed_by INTEGER REFERENCES users(id) ON DELETE RESTRICT,
reversal_reason TEXT, return_reference TEXT,
CHECK(method='cash' OR reference IS NOT NULL AND length(trim(reference))>0),
CHECK(status='posted' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND return_reference IS NOT NULL AND length(trim(reversal_reason))>0 AND length(trim(return_reference))>0){$receiptCheck}
);
CREATE INDEX {$table}_date_status ON {$table}(business_date,status);
CREATE TRIGGER finance_{$table}_start BEFORE INSERT ON {$table} WHEN NEW.status!='posted' BEGIN SELECT RAISE(ABORT,'Voucher must start posted'); END;");
                }
                DB::unprepared('CREATE INDEX finance_customer_owner ON customer_payments(customer_id,status,occurred_at);
CREATE INDEX finance_payroll_owner ON payroll_payments(worker_id,status,period_start,period_end);
CREATE INDEX finance_receipt_factory ON sale_receipts(factory_sale_id,status);
CREATE INDEX finance_receipt_sort ON sale_receipts(sort_sale_id,status);');
                foreach (['customer' => ['customer_payments', 'weighing_id', 'weighings'], 'payroll' => ['payroll_payments', 'weighing_loader_id', 'weighing_loaders']] as $kind => [$header, $target, $targetTable]) {
                    $allocation = $kind.'_payment_allocations';
                    $parent = $kind.'_payment_id';
                    DB::unprepared("CREATE TABLE {$allocation} (
id INTEGER PRIMARY KEY AUTOINCREMENT, {$parent} INTEGER NOT NULL REFERENCES {$header}(id) ON DELETE RESTRICT,
{$target} INTEGER NOT NULL REFERENCES {$targetTable}(id) ON DELETE RESTRICT,
amount_idr INTEGER NOT NULL CHECK(typeof(amount_idr)='integer' AND amount_idr>0), UNIQUE({$parent},{$target})
);
CREATE INDEX {$allocation}_target ON {$allocation}({$target});");
                }
                DB::unprepared(<<<'SQL'
CREATE TABLE cash_entries (
id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT UNIQUE,
cash_account_id INTEGER NOT NULL REFERENCES cash_accounts(id) ON DELETE RESTRICT,
direction TEXT NOT NULL CHECK(direction IN ('in','out')),
amount_idr INTEGER NOT NULL CHECK(typeof(amount_idr)='integer' AND amount_idr BETWEEN 1 AND 100000000000000),
occurred_at DATETIME NOT NULL, business_date TEXT NOT NULL,
kind TEXT NOT NULL CHECK(kind IN ('customer','payroll','receipt','expense','opening','capital','withdrawal','reversal')),
customer_payment_id INTEGER UNIQUE REFERENCES customer_payments(id) ON DELETE RESTRICT,
payroll_payment_id INTEGER UNIQUE REFERENCES payroll_payments(id) ON DELETE RESTRICT,
sale_receipt_id INTEGER UNIQUE REFERENCES sale_receipts(id) ON DELETE RESTRICT,
daily_expense_id INTEGER UNIQUE REFERENCES daily_expenses(id) ON DELETE RESTRICT,
reversal_of_id INTEGER UNIQUE REFERENCES cash_entries(id) ON DELETE RESTRICT,
reference TEXT, reason TEXT, backdate_reason TEXT, created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
CHECK(kind IN ('customer','payroll','receipt','expense') AND reversal_of_id IS NULL AND
 ((customer_payment_id IS NOT NULL)+(payroll_payment_id IS NOT NULL)+(sale_receipt_id IS NOT NULL)+(daily_expense_id IS NOT NULL))=1 OR
 kind IN ('opening','capital','withdrawal') AND reversal_of_id IS NULL AND number IS NOT NULL AND reason IS NOT NULL AND length(trim(reason))>0 AND
 customer_payment_id IS NULL AND payroll_payment_id IS NULL AND sale_receipt_id IS NULL AND daily_expense_id IS NULL OR
 kind='reversal' AND reversal_of_id IS NOT NULL AND reason IS NOT NULL AND reference IS NOT NULL AND length(trim(reason))>0 AND length(trim(reference))>0 AND
 customer_payment_id IS NULL AND payroll_payment_id IS NULL AND sale_receipt_id IS NULL AND daily_expense_id IS NULL)
);
CREATE INDEX cash_entries_account_time ON cash_entries(cash_account_id,occurred_at,id);
CREATE INDEX cash_entries_business_date ON cash_entries(business_date,kind);
CREATE UNIQUE INDEX finance_one_opening ON cash_entries(cash_account_id) WHERE kind='opening';
SQL);
                $profitSql = DB::table('sqlite_master')->where('type', 'table')->where('name', 'profit_entries')->value('sql');
                $profitSql = str_replace('trip_id INTEGER NOT NULL', 'trip_id INTEGER', $profitSql);
                $profitSql = str_replace('sort_stock_movement_id INTEGER REFERENCES', 'daily_expense_id INTEGER REFERENCES daily_expenses(id) ON DELETE RESTRICT, sort_stock_movement_id INTEGER REFERENCES', $profitSql);
                $profitSql = str_replace('(sort_stock_movement_id IS NOT NULL))=1', '(sort_stock_movement_id IS NOT NULL)+(daily_expense_id IS NOT NULL))=1', $profitSql);
                $profitSql = str_replace('sort_stock_movement_id IS NULL)', 'sort_stock_movement_id IS NULL AND daily_expense_id IS NULL)', $profitSql);
                $this->rebuild('profit_entries', $profitSql);
                DB::statement('CREATE UNIQUE INDEX finance_profit_expense ON profit_entries(daily_expense_id,category) WHERE reversal_of_id IS NULL');
                $attachmentSql = DB::table('sqlite_master')->where('type', 'table')->where('name', 'attachments')->value('sql');
                $attachmentSql = str_replace("'factory','sort'", "'factory','sort','customer','payroll','receipt','expense'", $attachmentSql);
                $this->rebuild('attachments', $attachmentSql);
                foreach ($triggers as $trigger) {
                    if (in_array($trigger->name, ['attachment_source_guard', 'attachments_no_DELETE'], true)) {
                        continue;
                    }
                    DB::unprepared(str_replace('NEW.trip_id=p.trip_id', 'NEW.trip_id IS p.trip_id', $trigger->sql));
                }
                $this->financeTriggers();
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Finance migration failed foreign key validation.');
                }
            });
        });
    }

    private function removeTriggers(): array
    {
        $triggers = DB::select("SELECT name,sql FROM sqlite_master WHERE type='trigger'");
        foreach ($triggers as $trigger) {
            DB::statement('DROP TRIGGER "'.$trigger->name.'"');
        }

        return $triggers;
    }

    private function rebuild(string $table, string $sql, string $where = '1=1'): void
    {
        $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL", [$table]);
        $oldColumns = array_column(DB::select("PRAGMA table_info({$table})"), 'name');
        $create = preg_replace('/CREATE TABLE\s+["`]?'.$table.'["`]?/i', 'CREATE TABLE '.$table.'_new', $sql, 1);
        DB::unprepared($create);
        $columns = array_intersect($oldColumns, array_column(DB::select("PRAGMA table_info({$table}_new)"), 'name'));
        $list = implode(',', $columns);
        DB::statement("INSERT INTO {$table}_new({$list}) SELECT {$list} FROM {$table} WHERE {$where}");
        DB::statement("DROP TABLE {$table}");
        DB::statement("ALTER TABLE {$table}_new RENAME TO {$table}");
        foreach ($indexes as $index) {
            if (! str_contains($index->sql, 'finance_profit_expense') || in_array('daily_expense_id', $columns, true)) {
                DB::unprepared($index->sql);
            }
        }
    }

    private function financeTriggers(): void
    {
        foreach (['customer' => 'customer_payments', 'payroll' => 'payroll_payments', 'receipt' => 'sale_receipts', 'expense' => 'daily_expenses'] as $kind => $table) {
            $column = match ($kind) {
                'customer' => 'customer_payment_id', 'payroll' => 'payroll_payment_id', 'receipt' => 'sale_receipt_id', 'expense' => 'daily_expense_id'
            };
            $frozen = array_diff(array_column(DB::select("PRAGMA table_info({$table})"), 'name'), ['status', 'version', 'updated_at', 'reversed_at', 'reversal_business_date', 'reversed_by', 'reversal_reason', 'return_reference']);
            $condition = implode(' OR ', array_map(fn ($c) => "NEW.{$c} IS NOT OLD.{$c}", $frozen));
            DB::unprepared("CREATE TRIGGER finance_{$table}_freeze BEFORE UPDATE ON {$table} WHEN {$condition} OR NOT (OLD.status='posted' AND NEW.status='reversed' AND NEW.version=OLD.version+1)
BEGIN SELECT RAISE(ABORT,'Voucher is immutable'); END;
CREATE TRIGGER finance_{$table}_reverse BEFORE UPDATE ON {$table} WHEN NEW.status='reversed'
BEGIN SELECT CASE WHEN NOT EXISTS(SELECT 1 FROM cash_entries e JOIN cash_entries r ON r.reversal_of_id=e.id WHERE e.{$column}=OLD.id)
THEN RAISE(ABORT,'Voucher requires cash reversal') END; END;
CREATE TRIGGER finance_{$table}_no_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT,'Voucher cannot be deleted'); END;
CREATE TRIGGER finance_cash_{$kind} BEFORE INSERT ON cash_entries WHEN NEW.kind='{$kind}'
BEGIN SELECT CASE WHEN NEW.{$column} IS NULL OR NOT EXISTS(SELECT 1 FROM {$table} p WHERE p.id=NEW.{$column} AND p.status='posted' AND p.cash_account_id=NEW.cash_account_id AND p.amount_idr=NEW.amount_idr AND p.occurred_at=NEW.occurred_at AND p.business_date=NEW.business_date AND p.created_by=NEW.created_by AND p.reference IS NEW.reference)
OR NEW.direction!='".($kind === 'receipt' ? 'in' : 'out')."' THEN RAISE(ABORT,'Cash does not match voucher') END; END;");
            if (in_array($kind, ['customer', 'payroll'], true)) {
                $allocation = $kind.'_payment_allocations';
                $target = $kind === 'customer' ? 'weighing_id' : 'weighing_loader_id';
                $source = $kind === 'customer' ? 'weighings w' : 'weighing_loaders l JOIN weighings w ON w.id=l.weighing_id';
                $sourceId = $kind === 'customer' ? 'w.id' : 'l.id';
                $owner = $kind === 'customer' ? 'w.customer_id=p.customer_id' : 'l.worker_id=p.worker_id AND w.business_date BETWEEN p.period_start AND p.period_end';
                $entitlement = $kind === 'customer' ? 'w.total_purchase_idr' : 'l.share_amount_idr';
                DB::unprepared("CREATE TRIGGER finance_{$allocation}_guard BEFORE INSERT ON {$allocation}
BEGIN SELECT CASE WHEN NOT EXISTS(SELECT 1 FROM {$source} JOIN {$table} p ON p.id=NEW.{$column} WHERE {$sourceId}=NEW.{$target} AND {$owner} AND w.status='posted' AND p.status='posted' AND p.occurred_at>=w.weighed_at)
OR EXISTS(SELECT 1 FROM cash_entries WHERE {$column}=NEW.{$column})
OR NEW.amount_idr+COALESCE((SELECT SUM(a.amount_idr) FROM {$allocation} a JOIN {$table} p ON p.id=a.{$column} WHERE a.{$target}=NEW.{$target} AND p.status='posted'),0)>(SELECT {$entitlement} FROM {$source} WHERE {$sourceId}=NEW.{$target})
OR NEW.amount_idr+COALESCE((SELECT SUM(amount_idr) FROM {$allocation} WHERE {$column}=NEW.{$column}),0)>(SELECT amount_idr FROM {$table} WHERE id=NEW.{$column})
THEN RAISE(ABORT,'Invalid payment allocation') END; END;
CREATE TRIGGER finance_{$kind}_reconcile BEFORE INSERT ON cash_entries WHEN NEW.kind='{$kind}'
BEGIN SELECT CASE WHEN NEW.amount_idr IS NOT (SELECT SUM(amount_idr) FROM {$allocation} WHERE {$column}=NEW.{$column}) THEN RAISE(ABORT,'Payment allocations do not reconcile') END; END;");
                $this->appendOnly($allocation);
            }
        }
        DB::unprepared(<<<'SQL'
CREATE TRIGGER finance_cash_balance BEFORE INSERT ON cash_entries
BEGIN
SELECT CASE WHEN NEW.occurred_at<COALESCE((SELECT MAX(occurred_at) FROM cash_entries WHERE cash_account_id=NEW.cash_account_id),NEW.occurred_at)
OR COALESCE((SELECT SUM(CASE direction WHEN 'in' THEN amount_idr ELSE -amount_idr END) FROM cash_entries WHERE cash_account_id=NEW.cash_account_id),0)+(CASE NEW.direction WHEN 'in' THEN NEW.amount_idr ELSE -NEW.amount_idr END) NOT BETWEEN 0 AND 1000000000000000
THEN RAISE(ABORT,'Cash balance or chronology invalid') END;
SELECT CASE WHEN NEW.kind!='reversal' AND NOT EXISTS(SELECT 1 FROM cash_accounts WHERE id=NEW.cash_account_id AND is_active=1)
OR NEW.kind IN ('opening','capital','withdrawal','reversal') AND NOT EXISTS(SELECT 1 FROM users WHERE id=NEW.created_by AND role='manager' AND is_active=1)
OR NEW.kind='opening' AND EXISTS(SELECT 1 FROM cash_entries WHERE cash_account_id=NEW.cash_account_id)
OR NEW.kind IN ('opening','capital') AND NEW.direction!='in' OR NEW.kind='withdrawal' AND NEW.direction!='out'
THEN RAISE(ABORT,'Invalid manual cash entry') END;
SELECT CASE WHEN NEW.kind='reversal' AND NOT EXISTS(SELECT 1 FROM cash_entries e WHERE e.id=NEW.reversal_of_id AND e.kind!='reversal' AND e.cash_account_id=NEW.cash_account_id AND e.direction!=NEW.direction AND e.amount_idr=NEW.amount_idr AND NEW.occurred_at>=e.occurred_at)
THEN RAISE(ABORT,'Invalid cash reversal') END;
END;
CREATE TRIGGER finance_receipt_source BEFORE INSERT ON sale_receipts
BEGIN
SELECT CASE WHEN NEW.factory_sale_id IS NOT NULL AND (NOT EXISTS(SELECT 1 FROM factory_sales WHERE id=NEW.factory_sale_id AND status='posted' AND sold_at<=NEW.occurred_at) OR NEW.amount_idr+COALESCE((SELECT SUM(amount_idr) FROM sale_receipts WHERE factory_sale_id=NEW.factory_sale_id AND status='posted'),0)>(SELECT revenue_idr FROM factory_sales WHERE id=NEW.factory_sale_id))
OR NEW.sort_sale_id IS NOT NULL AND (NOT EXISTS(SELECT 1 FROM sort_sales WHERE id=NEW.sort_sale_id AND status='posted' AND sold_at<=NEW.occurred_at) OR NEW.amount_idr+COALESCE((SELECT SUM(amount_idr) FROM sale_receipts WHERE sort_sale_id=NEW.sort_sale_id AND status='posted'),0)>(SELECT revenue_idr FROM sort_sales WHERE id=NEW.sort_sale_id))
THEN RAISE(ABORT,'Invalid sale receipt') END;
END;
CREATE TRIGGER finance_expense_profit BEFORE INSERT ON profit_entries WHEN NEW.daily_expense_id IS NOT NULL
BEGIN SELECT CASE WHEN NEW.category!='operating_expense' OR NOT EXISTS(SELECT 1 FROM daily_expenses e WHERE e.id=NEW.daily_expense_id AND e.status='posted' AND e.amount_idr=NEW.amount_delta_idr AND e.trip_id IS NEW.trip_id AND e.occurred_at=NEW.occurred_at AND e.business_date=NEW.business_date)
THEN RAISE(ABORT,'Expense profit must match voucher') END; END;
CREATE TRIGGER finance_weighing_dependencies BEFORE UPDATE ON weighings WHEN OLD.status='posted' AND NEW.status='voided'
BEGIN SELECT CASE WHEN EXISTS(SELECT 1 FROM customer_payment_allocations a JOIN customer_payments p ON p.id=a.customer_payment_id WHERE a.weighing_id=OLD.id AND p.status='posted')
OR EXISTS(SELECT 1 FROM payroll_payment_allocations a JOIN payroll_payments p ON p.id=a.payroll_payment_id JOIN weighing_loaders l ON l.id=a.weighing_loader_id WHERE l.weighing_id=OLD.id AND p.status='posted')
THEN RAISE(ABORT,'Reverse customer and payroll payments first') END; END;
SQL);
        foreach (['factory', 'sort'] as $kind) {
            DB::unprepared("CREATE TRIGGER finance_{$kind}_sale_dependencies BEFORE UPDATE ON {$kind}_sales WHEN OLD.status='posted' AND NEW.status='reversed'
BEGIN SELECT CASE WHEN EXISTS(SELECT 1 FROM sale_receipts WHERE {$kind}_sale_id=OLD.id AND status='posted') THEN RAISE(ABORT,'Reverse sale receipts first') END; END;");
        }
        $guard = [];
        foreach (['factory' => 'factory_sales', 'sort' => 'sort_sales', 'customer' => 'customer_payments', 'payroll' => 'payroll_payments', 'receipt' => 'sale_receipts', 'expense' => 'daily_expenses'] as $kind => $table) {
            $guard[] = "NEW.entity_type='{$kind}' AND NOT EXISTS(SELECT 1 FROM {$table} WHERE id=NEW.entity_id)";
        }
        DB::unprepared('CREATE TRIGGER attachment_source_guard BEFORE INSERT ON attachments BEGIN SELECT CASE WHEN '.implode(' OR ', $guard)." THEN RAISE(ABORT,'Missing attachment source') END; END;");
        DB::unprepared("CREATE TRIGGER attachments_no_DELETE BEFORE DELETE ON attachments WHEN OLD.entity_type NOT IN ('factory','sort') OR OLD.entity_type='factory' AND (SELECT status FROM factory_sales WHERE id=OLD.entity_id)!='draft' OR OLD.entity_type='sort' AND (SELECT status FROM sort_sales WHERE id=OLD.entity_id)!='draft' BEGIN SELECT RAISE(ABORT,'Posted proof cannot be deleted'); END;");
        $this->appendOnly('cash_entries');
    }

    private function appendOnly(string $table): void
    {
        foreach (['UPDATE', 'DELETE'] as $event) {
            DB::unprepared("CREATE TRIGGER finance_{$table}_no_{$event} BEFORE {$event} ON {$table} BEGIN SELECT RAISE(ABORT,'Append-only record'); END;");
        }
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            DB::transaction(function () {
                $triggers = $this->removeTriggers();
                $stage3 = file_get_contents(__DIR__.'/2026_10_09_000003_create_sales_and_inventory.php');
                foreach (['profit_entries' => 'CREATE UNIQUE INDEX profit_factory_category', 'attachments' => 'CREATE INDEX attachments_entity'] as $table => $end) {
                    $start = strpos($stage3, "CREATE TABLE {$table} (");
                    $sql = substr($stage3, $start, strpos($stage3, $end, $start) - $start);
                    $where = $table === 'attachments' ? "entity_type IN ('factory','sort')" : 'daily_expense_id IS NULL AND (reversal_of_id IS NULL OR reversal_of_id IN (SELECT id FROM profit_entries WHERE daily_expense_id IS NULL AND reversal_of_id IS NULL))';
                    $this->rebuild($table, $sql, $where);
                }
                foreach (['cash_entries', 'customer_payment_allocations', 'payroll_payment_allocations', 'customer_payments', 'payroll_payments', 'sale_receipts', 'daily_expenses'] as $table) {
                    DB::statement("DROP TABLE {$table}");
                }
                foreach ($triggers as $trigger) {
                    if (str_starts_with($trigger->name, 'finance_') || $trigger->name === 'attachment_source_guard') {
                        continue;
                    }
                    $sql = str_replace('NEW.trip_id IS p.trip_id', 'NEW.trip_id=p.trip_id', $trigger->sql);
                    $sql = str_replace("OLD.entity_type NOT IN ('factory','sort') OR ", '', $sql);
                    DB::unprepared($sql);
                }
                $start = strpos($stage3, 'CREATE TRIGGER attachment_source_guard');
                DB::unprepared(substr($stage3, $start, strpos($stage3, 'CREATE TRIGGER sort_lot_frozen', $start) - $start));
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Finance rollback failed foreign key validation.');
                }
            });
        });
    }
};
