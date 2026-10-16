-- CLBK TOKE production baseline: MySQL 8.4, InnoDB.
-- Laravel loads this schema on the first migrate into an empty database.
-- Historical SQLite migrations remain available for local development/tests.
SET NAMES utf8mb4 COLLATE utf8mb4_0900_bin;
SET FOREIGN_KEY_CHECKS=0;
SET SESSION sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
    `key` VARCHAR(255) NOT NULL,
    `value_json` LONGTEXT NOT NULL,
    `updated_by` BIGINT UNSIGNED,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`key`),
    CHECK (json_valid( value_json ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(255) NOT NULL,
    `entity_id` BIGINT NOT NULL,
    `storage_path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(255) NOT NULL,
    `size_bytes` BIGINT NOT NULL,
    `uploaded_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `attachments_unique_1` (`storage_path`),
    CHECK (entity_type IN ( 'factory' , 'sort' , 'customer' , 'payroll' , 'receipt' , 'expense' )),
    CHECK (size_bytes BETWEEN 1 AND 10485760)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_id` BIGINT UNSIGNED,
    `action` VARCHAR(255) NOT NULL,
    `entity_type` VARCHAR(255) NOT NULL,
    `entity_id` VARCHAR(255) NOT NULL,
    `before_json` LONGTEXT,
    `after_json` LONGTEXT,
    `reason` LONGTEXT,
    `occurred_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    CHECK (before_json IS NULL OR json_valid( before_json )),
    CHECK (after_json IS NULL OR json_valid( after_json ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
    `key` VARCHAR(512) NOT NULL,
    `value` LONGTEXT NOT NULL,
    `expiration` BIGINT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
    `key` VARCHAR(512) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` BIGINT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `cash_accounts`;
CREATE TABLE `cash_accounts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `is_active` BIGINT NOT NULL DEFAULT 1,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `cash_accounts_unique_1` (`code`),
    CHECK (type IN ( 'cash' , 'bank' )),
    CHECK (is_active IN ( 0 , 1 ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `cash_entries`;
CREATE TABLE `cash_entries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(255),
    `cash_account_id` BIGINT UNSIGNED NOT NULL,
    `direction` VARCHAR(255) NOT NULL,
    `amount_idr` BIGINT NOT NULL,
    `occurred_at` DATETIME NOT NULL,
    `business_date` VARCHAR(255) NOT NULL,
    `kind` VARCHAR(255) NOT NULL,
    `customer_payment_id` BIGINT UNSIGNED,
    `payroll_payment_id` BIGINT UNSIGNED,
    `sale_receipt_id` BIGINT UNSIGNED,
    `daily_expense_id` BIGINT UNSIGNED,
    `reversal_of_id` BIGINT UNSIGNED,
    `reference` VARCHAR(255),
    `reason` LONGTEXT,
    `backdate_reason` LONGTEXT,
    `created_by` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `cash_entries_unique_3` (`reversal_of_id`),
    UNIQUE KEY `cash_entries_unique_4` (`daily_expense_id`),
    UNIQUE KEY `cash_entries_unique_5` (`sale_receipt_id`),
    UNIQUE KEY `cash_entries_unique_6` (`payroll_payment_id`),
    UNIQUE KEY `cash_entries_unique_7` (`customer_payment_id`),
    UNIQUE KEY `cash_entries_unique_8` (`number`),
    CHECK (direction IN ( 'in' , 'out' )),
    CHECK (( amount_idr IS NOT NULL ) AND amount_idr BETWEEN 1 AND 100000000000000),
    CHECK (kind IN ( 'customer' , 'payroll' , 'receipt' , 'expense' , 'opening' , 'capital' , 'withdrawal' , 'reversal' )),
    CHECK (kind IN ( 'customer' , 'payroll' , 'receipt' , 'expense' ) AND reversal_of_id IS NULL AND ( ( customer_payment_id IS NOT NULL ) + ( payroll_payment_id IS NOT NULL ) + ( sale_receipt_id IS NOT NULL ) + ( daily_expense_id IS NOT NULL ) ) = 1 OR kind IN ( 'opening' , 'capital' , 'withdrawal' ) AND reversal_of_id IS NULL AND number IS NOT NULL AND reason IS NOT NULL AND length( trim( reason ) ) > 0 AND customer_payment_id IS NULL AND payroll_payment_id IS NULL AND sale_receipt_id IS NULL AND daily_expense_id IS NULL OR kind = 'reversal' AND reversal_of_id IS NOT NULL AND reason IS NOT NULL AND reference IS NOT NULL AND length( trim( reason ) ) > 0 AND length( trim( reference ) ) > 0 AND customer_payment_id IS NULL AND payroll_payment_id IS NULL AND sale_receipt_id IS NULL AND daily_expense_id IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `customer_payment_allocations`;
CREATE TABLE `customer_payment_allocations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_payment_id` BIGINT UNSIGNED NOT NULL,
    `weighing_id` BIGINT UNSIGNED NOT NULL,
    `amount_idr` BIGINT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `customer_payment_allocations_unique_1` (`customer_payment_id`, `weighing_id`),
    CHECK (( amount_idr IS NOT NULL ) AND amount_idr > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `customer_payments`;
CREATE TABLE `customer_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(255) NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `cash_account_id` BIGINT UNSIGNED NOT NULL,
    `occurred_at` DATETIME NOT NULL,
    `business_date` VARCHAR(255) NOT NULL,
    `amount_idr` BIGINT NOT NULL,
    `method` VARCHAR(255) NOT NULL,
    `reference` VARCHAR(255),
    `notes` LONGTEXT,
    `backdate_reason` LONGTEXT,
    `status` VARCHAR(255) NOT NULL,
    `posted_at` DATETIME NOT NULL,
    `version` BIGINT NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `reversed_at` DATETIME,
    `reversal_business_date` VARCHAR(255),
    `reversed_by` BIGINT UNSIGNED,
    `reversal_reason` LONGTEXT,
    `return_reference` VARCHAR(255),
    PRIMARY KEY (`id`),
    UNIQUE KEY `customer_payments_unique_2` (`number`),
    CHECK (( amount_idr IS NOT NULL ) AND amount_idr BETWEEN 1 AND 100000000000000),
    CHECK (method IN ( 'cash' , 'bank_transfer' )),
    CHECK (status IN ( 'posted' , 'reversed' )),
    CHECK (( version IS NOT NULL ) AND version > 0),
    CHECK (method = 'cash' OR reference IS NOT NULL AND length( trim( reference ) ) > 0),
    CHECK (status = 'posted' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND return_reference IS NOT NULL AND length( trim( reversal_reason ) ) > 0 AND length( trim( return_reference ) ) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(255),
    `address` LONGTEXT,
    `farm_location` LONGTEXT,
    `notes` LONGTEXT,
    `is_active` BIGINT NOT NULL DEFAULT 1,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `customers_unique_1` (`code`),
    CHECK (is_active IN ( 0 , 1 ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `daily_expenses`;
CREATE TABLE `daily_expenses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(255) NOT NULL,
    `expense_category_id` BIGINT UNSIGNED NOT NULL,
    `trip_id` BIGINT UNSIGNED,
    `description` LONGTEXT NOT NULL,
    `cash_account_id` BIGINT UNSIGNED NOT NULL,
    `occurred_at` DATETIME NOT NULL,
    `business_date` VARCHAR(255) NOT NULL,
    `amount_idr` BIGINT NOT NULL,
    `method` VARCHAR(255) NOT NULL,
    `reference` VARCHAR(255),
    `notes` LONGTEXT,
    `backdate_reason` LONGTEXT,
    `status` VARCHAR(255) NOT NULL,
    `posted_at` DATETIME NOT NULL,
    `version` BIGINT NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `reversed_at` DATETIME,
    `reversal_business_date` VARCHAR(255),
    `reversed_by` BIGINT UNSIGNED,
    `reversal_reason` LONGTEXT,
    `return_reference` VARCHAR(255),
    PRIMARY KEY (`id`),
    UNIQUE KEY `daily_expenses_unique_1` (`number`),
    CHECK (length( trim( description ) ) > 0),
    CHECK (( amount_idr IS NOT NULL ) AND amount_idr BETWEEN 1 AND 100000000000000),
    CHECK (method IN ( 'cash' , 'bank_transfer' )),
    CHECK (status IN ( 'posted' , 'reversed' )),
    CHECK (( version IS NOT NULL ) AND version > 0),
    CHECK (method = 'cash' OR reference IS NOT NULL AND length( trim( reference ) ) > 0),
    CHECK (status = 'posted' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND return_reference IS NOT NULL AND length( trim( reversal_reason ) ) > 0 AND length( trim( return_reference ) ) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `document_sequences`;
CREATE TABLE `document_sequences` (
    `name` VARCHAR(255) NOT NULL,
    `next_value` BIGINT NOT NULL,
    PRIMARY KEY (`name`),
    CHECK (( next_value IS NOT NULL ) AND next_value > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `is_active` BIGINT NOT NULL DEFAULT 1,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `expense_categories_unique_1` (`code`),
    CHECK (is_active IN ( 0 , 1 ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `factories`;
CREATE TABLE `factories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `address` LONGTEXT,
    `contact_name` VARCHAR(255),
    `phone` VARCHAR(255),
    `is_active` BIGINT NOT NULL DEFAULT 1,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `factories_unique_1` (`code`),
    CHECK (is_active IN ( 0 , 1 ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `factory_price_schedules`;
CREATE TABLE `factory_price_schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `factory_id` BIGINT UNSIGNED NOT NULL,
    `effective_from` DATETIME NOT NULL,
    `tbs_sell_price_idr` BIGINT NOT NULL,
    `published_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `factory_price_schedules_unique_0` (`factory_id`, `effective_from`),
    CHECK (( tbs_sell_price_idr IS NOT NULL ) AND tbs_sell_price_idr BETWEEN 1 AND 100000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `factory_returns`;
CREATE TABLE `factory_returns` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `factory_sale_id` BIGINT UNSIGNED NOT NULL,
    `weight_grams` BIGINT NOT NULL,
    `purchase_cost_carried_idr` BIGINT,
    `loading_cost_carried_idr` BIGINT,
    `cost_status` VARCHAR(255) NOT NULL,
    `received_at` DATETIME NOT NULL,
    `notes` LONGTEXT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `factory_returns_unique_0` (`factory_sale_id`),
    CHECK (( weight_grams IS NOT NULL ) AND weight_grams > 0),
    CHECK (cost_status IN ( 'available' , 'unavailable' )),
    CHECK (cost_status = 'unavailable' AND purchase_cost_carried_idr IS NULL AND loading_cost_carried_idr IS NULL OR cost_status = 'available' AND ( purchase_cost_carried_idr IS NOT NULL ) AND purchase_cost_carried_idr >= 0 AND ( loading_cost_carried_idr IS NOT NULL ) AND loading_cost_carried_idr >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `factory_sales`;
CREATE TABLE `factory_sales` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(255) NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `workflow` VARCHAR(255) NOT NULL DEFAULT 'legacy',
    `trip_id` BIGINT UNSIGNED,
    `factory_id` BIGINT UNSIGNED NOT NULL,
    `factory_price_schedule_id` BIGINT UNSIGNED,
    `invoice_number` VARCHAR(255),
    `initial_load_grams` BIGINT,
    `sorted_out_grams` BIGINT,
    `accepted_grams` BIGINT NOT NULL,
    `weight_difference_grams` BIGINT,
    `variance_approved_by` BIGINT UNSIGNED,
    `variance_approved_version` BIGINT,
    `variance_reason` LONGTEXT,
    `sold_at` DATETIME NOT NULL,
    `business_date` VARCHAR(255) NOT NULL,
    `sell_price_snapshot_idr` BIGINT NOT NULL,
    `deduction_idr` BIGINT NOT NULL,
    `deduction_reason` LONGTEXT,
    `notes` LONGTEXT,
    `correction_reason` LONGTEXT,
    `revenue_idr` BIGINT NOT NULL,
    `purchase_cost_released_idr` BIGINT,
    `loading_cost_released_idr` BIGINT,
    `cost_status` VARCHAR(255) NOT NULL DEFAULT 'unavailable',
    `status` VARCHAR(255) NOT NULL,
    `posted_at` DATETIME,
    `posted_by` BIGINT UNSIGNED,
    `reversed_at` DATETIME,
    `reversal_business_date` VARCHAR(255),
    `reversed_by` BIGINT UNSIGNED,
    `reversal_reason` LONGTEXT,
    `replaces_sale_id` BIGINT UNSIGNED,
    `version` BIGINT NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `factory_sales_unique_6` (`replaces_sale_id`),
    UNIQUE KEY `factory_sales_unique_7` (`number`),
    CHECK (workflow IN ( 'legacy' , 'direct' )),
    CHECK (initial_load_grams IS NULL OR ( ( initial_load_grams IS NOT NULL ) AND initial_load_grams BETWEEN 1 AND 1000000000 )),
    CHECK (sorted_out_grams IS NULL OR ( ( sorted_out_grams IS NOT NULL ) AND sorted_out_grams BETWEEN 0 AND initial_load_grams )),
    CHECK (( accepted_grams IS NOT NULL ) AND accepted_grams BETWEEN 1 AND 1000000000),
    CHECK (weight_difference_grams = initial_load_grams - sorted_out_grams - accepted_grams),
    CHECK (( sell_price_snapshot_idr IS NOT NULL ) AND sell_price_snapshot_idr BETWEEN 1 AND 100000000),
    CHECK (( deduction_idr IS NOT NULL ) AND deduction_idr BETWEEN 0 AND 100000000000000),
    CHECK (( revenue_idr IS NOT NULL ) AND revenue_idr >= 0),
    CHECK (purchase_cost_released_idr IS NULL OR ( ( purchase_cost_released_idr IS NOT NULL ) AND purchase_cost_released_idr >= 0 )),
    CHECK (loading_cost_released_idr IS NULL OR ( ( loading_cost_released_idr IS NOT NULL ) AND loading_cost_released_idr >= 0 )),
    CHECK (cost_status IN ( 'available' , 'unavailable' )),
    CHECK (status IN ( 'draft' , 'posted' , 'reversed' )),
    CHECK (( version IS NOT NULL ) AND version > 0),
    CHECK (deduction_idr = 0 OR deduction_reason IS NOT NULL AND length( trim( deduction_reason ) ) > 0),
    CHECK (cost_status = 'unavailable' AND purchase_cost_released_idr IS NULL AND loading_cost_released_idr IS NULL OR cost_status = 'available' AND purchase_cost_released_idr IS NOT NULL AND loading_cost_released_idr IS NOT NULL),
    CHECK (status = 'draft' OR posted_at IS NOT NULL AND posted_by IS NOT NULL),
    CHECK (status != 'reversed' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND length( trim( reversal_reason ) ) > 0),
    CHECK (( workflow = 'legacy' AND trip_id IS NOT NULL ) OR ( workflow = 'direct' AND trip_id IS NULL )),
    CHECK (workflow = 'legacy' AND initial_load_grams IS NOT NULL AND sorted_out_grams IS NOT NULL AND weight_difference_grams IS NOT NULL AND invoice_number IS NOT NULL OR workflow = 'direct' AND initial_load_grams IS NULL AND sorted_out_grams IS NULL AND weight_difference_grams IS NULL AND variance_approved_by IS NULL AND variance_approved_version IS NULL AND variance_reason IS NULL AND factory_price_schedule_id IS NOT NULL AND cost_status = 'unavailable')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` VARCHAR(255) NOT NULL,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `idempotency_requests`;
CREATE TABLE `idempotency_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `operation` VARCHAR(255) NOT NULL,
    `key` VARCHAR(255) NOT NULL,
    `request_hash` VARCHAR(255) NOT NULL,
    `response_reference` BIGINT NOT NULL,
    `status` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idempotency_requests_unique_0` (`actor_id`, `operation`, `key`),
    CHECK (status = 'completed')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` BIGINT NOT NULL,
    `pending_jobs` BIGINT NOT NULL,
    `failed_jobs` BIGINT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` LONGTEXT,
    `cancelled_at` BIGINT,
    `created_at` BIGINT NOT NULL,
    `finished_at` BIGINT,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` BIGINT NOT NULL,
    `reserved_at` BIGINT,
    `available_at` BIGINT NOT NULL,
    `created_at` BIGINT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `loading_workers`;
CREATE TABLE `loading_workers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(255),
    `address` LONGTEXT,
    `joined_on` DATE,
    `is_active` BIGINT NOT NULL DEFAULT 1,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `loading_workers_unique_1` (`code`),
    CHECK (is_active IN ( 0 , 1 ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` BIGINT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` DATETIME,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `payroll_payment_allocations`;
CREATE TABLE `payroll_payment_allocations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payroll_payment_id` BIGINT UNSIGNED NOT NULL,
    `weighing_loader_id` BIGINT UNSIGNED NOT NULL,
    `amount_idr` BIGINT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `payroll_payment_allocations_unique_1` (`payroll_payment_id`, `weighing_loader_id`),
    CHECK (( amount_idr IS NOT NULL ) AND amount_idr > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `payroll_payments`;
CREATE TABLE `payroll_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(255) NOT NULL,
    `worker_id` BIGINT UNSIGNED NOT NULL,
    `period_start` VARCHAR(255) NOT NULL,
    `period_end` VARCHAR(255) NOT NULL,
    `cash_account_id` BIGINT UNSIGNED NOT NULL,
    `occurred_at` DATETIME NOT NULL,
    `business_date` VARCHAR(255) NOT NULL,
    `amount_idr` BIGINT NOT NULL,
    `method` VARCHAR(255) NOT NULL,
    `reference` VARCHAR(255),
    `notes` LONGTEXT,
    `backdate_reason` LONGTEXT,
    `status` VARCHAR(255) NOT NULL,
    `posted_at` DATETIME NOT NULL,
    `version` BIGINT NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `reversed_at` DATETIME,
    `reversal_business_date` VARCHAR(255),
    `reversed_by` BIGINT UNSIGNED,
    `reversal_reason` LONGTEXT,
    `return_reference` VARCHAR(255),
    PRIMARY KEY (`id`),
    UNIQUE KEY `payroll_payments_unique_2` (`number`),
    CHECK (period_end >= period_start),
    CHECK (( amount_idr IS NOT NULL ) AND amount_idr BETWEEN 1 AND 100000000000000),
    CHECK (method IN ( 'cash' , 'bank_transfer' )),
    CHECK (status IN ( 'posted' , 'reversed' )),
    CHECK (( version IS NOT NULL ) AND version > 0),
    CHECK (method = 'cash' OR reference IS NOT NULL AND length( trim( reference ) ) > 0),
    CHECK (status = 'posted' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND return_reference IS NOT NULL AND length( trim( reversal_reason ) ) > 0 AND length( trim( return_reference ) ) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `price_schedules`;
CREATE TABLE `price_schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `business_date` VARCHAR(255) NOT NULL,
    `effective_from` DATETIME NOT NULL,
    `tbs_buy_price_idr` BIGINT NOT NULL,
    `sort_buy_price_idr` BIGINT NOT NULL,
    `loading_rate_idr` BIGINT NOT NULL,
    `notes` LONGTEXT,
    `published_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `price_schedules_unique_1` (`effective_from`),
    CHECK (( tbs_buy_price_idr IS NOT NULL ) AND tbs_buy_price_idr BETWEEN 1 AND 100000000),
    CHECK (( sort_buy_price_idr IS NOT NULL ) AND sort_buy_price_idr BETWEEN 1 AND 100000000),
    CHECK (( loading_rate_idr IS NOT NULL ) AND loading_rate_idr BETWEEN 0 AND 100000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `profit_entries`;
CREATE TABLE `profit_entries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category` VARCHAR(255) NOT NULL,
    `amount_delta_idr` BIGINT NOT NULL,
    `occurred_at` DATETIME NOT NULL,
    `business_date` VARCHAR(255) NOT NULL,
    `trip_id` BIGINT UNSIGNED,
    `factory_sale_id` BIGINT UNSIGNED,
    `sort_sale_id` BIGINT UNSIGNED,
    `daily_expense_id` BIGINT UNSIGNED,
    `sort_stock_movement_id` BIGINT UNSIGNED,
    `reversal_of_id` BIGINT UNSIGNED,
    `created_by` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `profit_entries_unique_5` (`reversal_of_id`),
    CHECK (category IN ( 'revenue' , 'purchase_cost' , 'loading_cost' , 'operating_expense' , 'stock_loss' )),
    CHECK (( amount_delta_idr IS NOT NULL )),
    CHECK (reversal_of_id IS NULL AND amount_delta_idr >= 0 AND ( ( factory_sale_id IS NOT NULL ) + ( sort_sale_id IS NOT NULL ) + ( sort_stock_movement_id IS NOT NULL ) + ( daily_expense_id IS NOT NULL ) ) = 1 OR reversal_of_id IS NOT NULL AND amount_delta_idr <= 0 AND factory_sale_id IS NULL AND sort_sale_id IS NULL AND sort_stock_movement_id IS NULL AND daily_expense_id IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `sale_receipts`;
CREATE TABLE `sale_receipts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(255) NOT NULL,
    `factory_sale_id` BIGINT UNSIGNED,
    `sort_sale_id` BIGINT UNSIGNED,
    `cash_account_id` BIGINT UNSIGNED NOT NULL,
    `occurred_at` DATETIME NOT NULL,
    `business_date` VARCHAR(255) NOT NULL,
    `amount_idr` BIGINT NOT NULL,
    `method` VARCHAR(255) NOT NULL,
    `reference` VARCHAR(255),
    `notes` LONGTEXT,
    `backdate_reason` LONGTEXT,
    `status` VARCHAR(255) NOT NULL,
    `posted_at` DATETIME NOT NULL,
    `version` BIGINT NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `reversed_at` DATETIME,
    `reversal_business_date` VARCHAR(255),
    `reversed_by` BIGINT UNSIGNED,
    `reversal_reason` LONGTEXT,
    `return_reference` VARCHAR(255),
    `is_automatic` BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sale_receipts_unique_5` (`number`),
    CHECK (( amount_idr IS NOT NULL ) AND amount_idr BETWEEN 1 AND 100000000000000),
    CHECK (method IN ( 'cash' , 'bank_transfer' )),
    CHECK (status IN ( 'posted' , 'reversed' )),
    CHECK (( version IS NOT NULL ) AND version > 0),
    CHECK (is_automatic IN ( 0 , 1 )),
    CHECK (method = 'cash' OR reference IS NOT NULL AND length( trim( reference ) ) > 0),
    CHECK (status = 'posted' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND return_reference IS NOT NULL AND length( trim( reversal_reason ) ) > 0 AND length( trim( return_reference ) ) > 0),
    CHECK (( factory_sale_id IS NOT NULL ) + ( sort_sale_id IS NOT NULL ) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT,
    `ip_address` VARCHAR(255),
    `user_agent` VARCHAR(255),
    `payload` LONGTEXT NOT NULL,
    `last_activity` BIGINT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `sort_lots`;
CREATE TABLE `sort_lots` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_type` VARCHAR(255) NOT NULL,
    `source_weighing_item_id` BIGINT UNSIGNED,
    `source_factory_return_id` BIGINT UNSIGNED,
    `trip_id` BIGINT UNSIGNED NOT NULL,
    `received_at` DATETIME NOT NULL,
    `initial_grams` BIGINT NOT NULL,
    `initial_purchase_cost_idr` BIGINT,
    `initial_loading_cost_idr` BIGINT,
    `cost_status` VARCHAR(255) NOT NULL,
    `status` VARCHAR(255) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sort_lots_unique_1` (`source_factory_return_id`),
    UNIQUE KEY `sort_lots_unique_2` (`source_weighing_item_id`),
    CHECK (source_type IN ( 'INITIAL_SORT' , 'FACTORY_RETURN' )),
    CHECK (( initial_grams IS NOT NULL ) AND initial_grams BETWEEN 1 AND 1000000000),
    CHECK (cost_status IN ( 'available' , 'unavailable' )),
    CHECK (status IN ( 'active' , 'voided' )),
    CHECK (source_type = 'INITIAL_SORT' AND source_weighing_item_id IS NOT NULL AND source_factory_return_id IS NULL OR source_type = 'FACTORY_RETURN' AND source_factory_return_id IS NOT NULL AND source_weighing_item_id IS NULL),
    CHECK (cost_status = 'unavailable' AND initial_purchase_cost_idr IS NULL AND initial_loading_cost_idr IS NULL OR cost_status = 'available' AND ( initial_purchase_cost_idr IS NOT NULL ) AND initial_purchase_cost_idr >= 0 AND ( initial_loading_cost_idr IS NOT NULL ) AND initial_loading_cost_idr >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `sort_sale_items`;
CREATE TABLE `sort_sale_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sort_sale_id` BIGINT UNSIGNED NOT NULL,
    `lot_id` BIGINT UNSIGNED NOT NULL,
    `weight_grams` BIGINT NOT NULL,
    `sell_price_snapshot_idr` BIGINT NOT NULL,
    `gross_revenue_idr` BIGINT NOT NULL,
    `purchase_cost_released_idr` BIGINT,
    `loading_cost_released_idr` BIGINT,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sort_sale_items_unique_0` (`sort_sale_id`, `lot_id`),
    CHECK (( weight_grams IS NOT NULL ) AND weight_grams > 0),
    CHECK (( sell_price_snapshot_idr IS NOT NULL ) AND sell_price_snapshot_idr > 0),
    CHECK (( gross_revenue_idr IS NOT NULL ) AND gross_revenue_idr >= 0),
    CHECK (purchase_cost_released_idr IS NULL AND loading_cost_released_idr IS NULL OR ( purchase_cost_released_idr IS NOT NULL ) AND purchase_cost_released_idr >= 0 AND ( loading_cost_released_idr IS NOT NULL ) AND loading_cost_released_idr >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `sort_sales`;
CREATE TABLE `sort_sales` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(255) NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `workflow` VARCHAR(255) NOT NULL DEFAULT 'legacy',
    `trip_id` BIGINT UNSIGNED,
    `buyer_name` VARCHAR(255),
    `buyer_contact` VARCHAR(255),
    `manual_sold_grams` BIGINT NOT NULL,
    `sold_at` DATETIME NOT NULL,
    `business_date` VARCHAR(255) NOT NULL,
    `sell_price_snapshot_idr` BIGINT NOT NULL,
    `deduction_idr` BIGINT NOT NULL,
    `deduction_reason` LONGTEXT,
    `notes` LONGTEXT,
    `correction_reason` LONGTEXT,
    `revenue_idr` BIGINT NOT NULL,
    `purchase_cost_released_idr` BIGINT,
    `loading_cost_released_idr` BIGINT,
    `cost_status` VARCHAR(255) NOT NULL DEFAULT 'unavailable',
    `status` VARCHAR(255) NOT NULL,
    `posted_at` DATETIME,
    `posted_by` BIGINT UNSIGNED,
    `reversed_at` DATETIME,
    `reversal_business_date` VARCHAR(255),
    `reversed_by` BIGINT UNSIGNED,
    `reversal_reason` LONGTEXT,
    `replaces_sale_id` BIGINT UNSIGNED,
    `version` BIGINT NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sort_sales_unique_4` (`replaces_sale_id`),
    UNIQUE KEY `sort_sales_unique_5` (`number`),
    CHECK (workflow IN ( 'legacy' , 'direct' )),
    CHECK (( manual_sold_grams IS NOT NULL ) AND manual_sold_grams BETWEEN 1 AND 1000000000),
    CHECK (( sell_price_snapshot_idr IS NOT NULL ) AND sell_price_snapshot_idr BETWEEN 1 AND 100000000),
    CHECK (( deduction_idr IS NOT NULL ) AND deduction_idr BETWEEN 0 AND 100000000000000),
    CHECK (( revenue_idr IS NOT NULL ) AND revenue_idr >= 0),
    CHECK (purchase_cost_released_idr IS NULL OR ( ( purchase_cost_released_idr IS NOT NULL ) AND purchase_cost_released_idr >= 0 )),
    CHECK (loading_cost_released_idr IS NULL OR ( ( loading_cost_released_idr IS NOT NULL ) AND loading_cost_released_idr >= 0 )),
    CHECK (cost_status IN ( 'available' , 'unavailable' )),
    CHECK (status IN ( 'draft' , 'posted' , 'reversed' )),
    CHECK (( version IS NOT NULL ) AND version > 0),
    CHECK (deduction_idr = 0 OR deduction_reason IS NOT NULL AND length( trim( deduction_reason ) ) > 0),
    CHECK (cost_status = 'unavailable' AND purchase_cost_released_idr IS NULL AND loading_cost_released_idr IS NULL OR cost_status = 'available' AND purchase_cost_released_idr IS NOT NULL AND loading_cost_released_idr IS NOT NULL),
    CHECK (status = 'draft' OR posted_at IS NOT NULL AND posted_by IS NOT NULL),
    CHECK (status != 'reversed' OR reversed_at IS NOT NULL AND reversal_business_date IS NOT NULL AND reversed_by IS NOT NULL AND reversal_reason IS NOT NULL AND length( trim( reversal_reason ) ) > 0),
    CHECK (( workflow = 'legacy' AND trip_id IS NOT NULL ) OR ( workflow = 'direct' AND trip_id IS NULL )),
    CHECK (workflow = 'legacy' AND buyer_name IS NOT NULL OR workflow = 'direct' AND cost_status = 'unavailable')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `sort_stock_movements`;
CREATE TABLE `sort_stock_movements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lot_id` BIGINT UNSIGNED NOT NULL,
    `kind` VARCHAR(255) NOT NULL,
    `quantity_delta_grams` BIGINT NOT NULL,
    `purchase_cost_delta_idr` BIGINT,
    `loading_cost_delta_idr` BIGINT,
    `sort_sale_item_id` BIGINT UNSIGNED,
    `reversal_of_id` BIGINT UNSIGNED,
    `reason` LONGTEXT,
    `occurred_at` DATETIME NOT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sort_stock_movements_unique_2` (`reversal_of_id`),
    UNIQUE KEY `sort_stock_movements_unique_3` (`sort_sale_item_id`),
    CHECK (kind IN ( 'receipt' , 'sale' , 'adjustment' , 'reversal' )),
    CHECK (( quantity_delta_grams IS NOT NULL ) AND quantity_delta_grams != 0),
    CHECK (purchase_cost_delta_idr IS NULL AND loading_cost_delta_idr IS NULL OR ( purchase_cost_delta_idr IS NOT NULL ) AND ( loading_cost_delta_idr IS NOT NULL )),
    CHECK (kind = 'receipt' AND quantity_delta_grams > 0 AND reversal_of_id IS NULL AND sort_sale_item_id IS NULL OR kind = 'sale' AND quantity_delta_grams < 0 AND reversal_of_id IS NULL AND sort_sale_item_id IS NOT NULL OR kind = 'adjustment' AND quantity_delta_grams < 0 AND reversal_of_id IS NULL AND sort_sale_item_id IS NULL AND reason IS NOT NULL AND length( trim( reason ) ) > 0 OR kind = 'reversal' AND reversal_of_id IS NOT NULL AND sort_sale_item_id IS NULL AND reason IS NOT NULL AND length( trim( reason ) ) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `trips`;
CREATE TABLE `trips` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(255) NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `driver_id` BIGINT UNSIGNED NOT NULL,
    `planned_factory_id` BIGINT UNSIGNED,
    `business_date` VARCHAR(255) NOT NULL,
    `status` VARCHAR(255) NOT NULL,
    `departed_at` DATETIME,
    `closed_at` DATETIME,
    `manual_load_grams` BIGINT,
    `tbs_purchase_cost_snapshot_idr` BIGINT,
    `tbs_loading_cost_snapshot_idr` BIGINT,
    `cost_status` VARCHAR(255) NOT NULL DEFAULT 'unavailable',
    `version` BIGINT NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `trips_unique_3` (`number`),
    CHECK (status IN ( 'loading' , 'dispatched' , 'factory_recorded' , 'closed' , 'cancelled' , 'correction_pending' )),
    CHECK (manual_load_grams IS NULL OR ( ( manual_load_grams IS NOT NULL ) AND manual_load_grams BETWEEN 1 AND 1000000000 )),
    CHECK (tbs_purchase_cost_snapshot_idr IS NULL OR ( ( tbs_purchase_cost_snapshot_idr IS NOT NULL ) AND tbs_purchase_cost_snapshot_idr >= 0 )),
    CHECK (tbs_loading_cost_snapshot_idr IS NULL OR ( ( tbs_loading_cost_snapshot_idr IS NOT NULL ) AND tbs_loading_cost_snapshot_idr >= 0 )),
    CHECK (cost_status IN ( 'available' , 'unavailable' )),
    CHECK (( version IS NOT NULL ) AND version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `email_verified_at` DATETIME,
    `password` VARCHAR(255) NOT NULL,
    `remember_token` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `role` VARCHAR(255) NOT NULL DEFAULT 'driver',
    `is_active` BIGINT NOT NULL DEFAULT 1,
    `assigned_vehicle_id` BIGINT UNSIGNED,
    `vehicle_assignment_version` BIGINT NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    CHECK (role IN ( 'manager' , 'driver' , 'cashier' )),
    CHECK (is_active IN ( 0 , 1 )),
    CHECK (assigned_vehicle_id IS NULL OR role = 'driver'),
    CHECK (( vehicle_assignment_version IS NOT NULL ) AND vehicle_assignment_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(255) NOT NULL,
    `plate_number` VARCHAR(255) NOT NULL,
    `vehicle_type` VARCHAR(255),
    `capacity_grams` BIGINT,
    `default_driver_id` BIGINT UNSIGNED,
    `is_active` BIGINT NOT NULL DEFAULT 1,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vehicles_unique_1` (`plate_number`),
    UNIQUE KEY `vehicles_unique_2` (`code`),
    CHECK (capacity_grams IS NULL OR ( ( capacity_grams IS NOT NULL ) AND capacity_grams > 0 AND capacity_grams <= 1000000000 )),
    CHECK (is_active IN ( 0 , 1 ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `weighing_items`;
CREATE TABLE `weighing_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `weighing_id` BIGINT UNSIGNED NOT NULL,
    `fruit_type` VARCHAR(255) NOT NULL,
    `weight_grams` BIGINT NOT NULL,
    `buy_price_snapshot_idr` BIGINT,
    `purchase_amount_idr` BIGINT,
    `loading_basis_grams` BIGINT,
    `loading_cost_allocated_idr` BIGINT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `weighing_items_unique_0` (`weighing_id`, `fruit_type`),
    CHECK (fruit_type IN ( 'TBS' , 'SORT' )),
    CHECK (( weight_grams IS NOT NULL ) AND weight_grams BETWEEN 1 AND 1000000000),
    CHECK (buy_price_snapshot_idr IS NULL OR ( ( buy_price_snapshot_idr IS NOT NULL ) AND buy_price_snapshot_idr > 0 )),
    CHECK (purchase_amount_idr IS NULL OR ( ( purchase_amount_idr IS NOT NULL ) AND purchase_amount_idr >= 0 )),
    CHECK (loading_basis_grams IS NULL OR ( ( loading_basis_grams IS NOT NULL ) AND loading_basis_grams > 0 )),
    CHECK (loading_cost_allocated_idr IS NULL OR ( ( loading_cost_allocated_idr IS NOT NULL ) AND loading_cost_allocated_idr >= 0 ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `weighing_loaders`;
CREATE TABLE `weighing_loaders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `weighing_id` BIGINT UNSIGNED NOT NULL,
    `worker_id` BIGINT UNSIGNED NOT NULL,
    `member_count_snapshot` BIGINT,
    `share_amount_idr` BIGINT,
    `allocation_order` BIGINT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `weighing_loaders_unique_1` (`weighing_id`, `worker_id`),
    CHECK (member_count_snapshot IS NULL OR ( ( member_count_snapshot IS NOT NULL ) AND member_count_snapshot > 0 )),
    CHECK (share_amount_idr IS NULL OR ( ( share_amount_idr IS NOT NULL ) AND share_amount_idr >= 0 )),
    CHECK (allocation_order IS NULL OR ( ( allocation_order IS NOT NULL ) AND allocation_order > 0 ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;

DROP TABLE IF EXISTS `weighings`;
CREATE TABLE `weighings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `number` VARCHAR(255) NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `workflow` VARCHAR(255) NOT NULL DEFAULT 'legacy',
    `trip_id` BIGINT UNSIGNED,
    `weighed_at` DATETIME NOT NULL,
    `business_date` VARCHAR(255) NOT NULL,
    `backdate_reason` LONGTEXT,
    `price_schedule_id` BIGINT UNSIGNED,
    `loading_rate_snapshot_idr` BIGINT,
    `loading_basis_grams` BIGINT,
    `total_purchase_idr` BIGINT,
    `total_loading_wage_idr` BIGINT,
    `status` VARCHAR(255) NOT NULL,
    `posted_at` DATETIME,
    `posted_by` BIGINT UNSIGNED,
    `voided_at` DATETIME,
    `voided_by` BIGINT UNSIGNED,
    `void_reason` LONGTEXT,
    `replaces_weighing_id` BIGINT UNSIGNED,
    `version` BIGINT NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE KEY `weighings_unique_4` (`number`),
    CHECK (workflow IN ( 'legacy' , 'direct' )),
    CHECK (loading_rate_snapshot_idr IS NULL OR ( ( loading_rate_snapshot_idr IS NOT NULL ) AND loading_rate_snapshot_idr >= 0 )),
    CHECK (loading_basis_grams IS NULL OR ( ( loading_basis_grams IS NOT NULL ) AND loading_basis_grams > 0 )),
    CHECK (total_purchase_idr IS NULL OR ( ( total_purchase_idr IS NOT NULL ) AND total_purchase_idr >= 0 )),
    CHECK (total_loading_wage_idr IS NULL OR ( ( total_loading_wage_idr IS NOT NULL ) AND total_loading_wage_idr >= 0 )),
    CHECK (status IN ( 'draft' , 'posted' , 'voided' )),
    CHECK (( version IS NOT NULL ) AND version > 0),
    CHECK (status = 'draft' OR ( price_schedule_id IS NOT NULL AND loading_rate_snapshot_idr IS NOT NULL AND loading_basis_grams IS NOT NULL AND total_purchase_idr IS NOT NULL AND total_loading_wage_idr IS NOT NULL AND posted_at IS NOT NULL AND posted_by IS NOT NULL )),
    CHECK (status != 'voided' OR ( voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL AND length( trim( void_reason ) ) > 0 )),
    CHECK (( workflow = 'legacy' AND trip_id IS NOT NULL ) OR ( workflow = 'direct' AND trip_id IS NULL ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;
CREATE INDEX `attachments_entity` ON `attachments` (`entity_type`, `entity_id`);
CREATE INDEX `audit_logs_entity_time` ON `audit_logs` (`entity_type`, `entity_id`, `occurred_at`);
CREATE INDEX `cache_expiration_index` ON `cache` (`expiration`);
CREATE INDEX `cache_locks_expiration_index` ON `cache_locks` (`expiration`);
CREATE INDEX `cash_accounts_active_code` ON `cash_accounts` (`is_active`, `code`);
CREATE INDEX `cash_entries_account_time` ON `cash_entries` (`cash_account_id`, `occurred_at`, `id`);
CREATE INDEX `cash_entries_business_date` ON `cash_entries` (`business_date`, `kind`);
CREATE INDEX `customer_payment_allocations_target` ON `customer_payment_allocations` (`weighing_id`);
CREATE INDEX `customer_payments_date_status` ON `customer_payments` (`business_date`, `status`);
CREATE INDEX `customers_active_code` ON `customers` (`is_active`, `code`);
CREATE INDEX `daily_expenses_date_status` ON `daily_expenses` (`business_date`, `status`);
CREATE INDEX `expense_categories_active_code` ON `expense_categories` (`is_active`, `code`);
CREATE INDEX `factories_active_code` ON `factories` (`is_active`, `code`);
CREATE INDEX `factory_sales_date_status` ON `factory_sales` (`business_date`, `status`);
CREATE INDEX `factory_sales_trip_status` ON `factory_sales` (`trip_id`, `status`);
CREATE UNIQUE INDEX `failed_jobs_uuid_unique` ON `failed_jobs` (`uuid`);
CREATE INDEX `finance_customer_owner` ON `customer_payments` (`customer_id`, `status`, `occurred_at`);
CREATE UNIQUE INDEX `finance_one_opening` ON `cash_entries` ((CASE WHEN kind = 'opening' THEN `cash_account_id` ELSE NULL END));
CREATE INDEX `finance_payroll_owner` ON `payroll_payments` (`worker_id`, `status`, `period_start`, `period_end`);
CREATE UNIQUE INDEX `finance_profit_expense` ON `profit_entries` ((CASE WHEN reversal_of_id IS NULL THEN `daily_expense_id` ELSE NULL END), (CASE WHEN reversal_of_id IS NULL THEN `category` ELSE NULL END));
CREATE INDEX `finance_receipt_factory` ON `sale_receipts` (`factory_sale_id`, `status`);
CREATE INDEX `finance_receipt_sort` ON `sale_receipts` (`sort_sale_id`, `status`);
CREATE INDEX `jobs_queue_index` ON `jobs` (`queue`);
CREATE INDEX `loaders_worker_weighing` ON `weighing_loaders` (`worker_id`, `weighing_id`);
CREATE INDEX `loading_workers_active_code` ON `loading_workers` (`is_active`, `code`);
CREATE UNIQUE INDEX `one_active_factory_invoice` ON `factory_sales` ((CASE WHEN status IN ( 'draft' , 'posted' ) THEN `factory_id` ELSE NULL END), (CASE WHEN status IN ( 'draft' , 'posted' ) THEN `invoice_number` ELSE NULL END));
CREATE UNIQUE INDEX `one_active_trip_per_vehicle` ON `trips` ((CASE WHEN status IN ( 'loading' , 'dispatched' ) THEN `vehicle_id` ELSE NULL END));
CREATE UNIQUE INDEX `one_factory_sale_per_trip` ON `factory_sales` ((CASE WHEN status IN ( 'draft' , 'posted' ) THEN `trip_id` ELSE NULL END));
CREATE UNIQUE INDEX `one_sort_receipt` ON `sort_stock_movements` ((CASE WHEN kind = 'receipt' THEN `lot_id` ELSE NULL END));
CREATE INDEX `payroll_payment_allocations_target` ON `payroll_payment_allocations` (`weighing_loader_id`);
CREATE INDEX `payroll_payments_date_status` ON `payroll_payments` (`business_date`, `status`);
CREATE INDEX `prices_date_effective` ON `price_schedules` (`business_date`, `effective_from`);
CREATE INDEX `profit_entries_date` ON `profit_entries` (`business_date`, `category`);
CREATE UNIQUE INDEX `profit_factory_category` ON `profit_entries` ((CASE WHEN reversal_of_id IS NULL THEN `factory_sale_id` ELSE NULL END), (CASE WHEN reversal_of_id IS NULL THEN `category` ELSE NULL END));
CREATE UNIQUE INDEX `profit_loss_category` ON `profit_entries` ((CASE WHEN reversal_of_id IS NULL THEN `sort_stock_movement_id` ELSE NULL END), (CASE WHEN reversal_of_id IS NULL THEN `category` ELSE NULL END));
CREATE UNIQUE INDEX `profit_sort_category` ON `profit_entries` ((CASE WHEN reversal_of_id IS NULL THEN `sort_sale_id` ELSE NULL END), (CASE WHEN reversal_of_id IS NULL THEN `category` ELSE NULL END));
CREATE UNIQUE INDEX `sale_receipts_automatic_factory` ON `sale_receipts` ((CASE WHEN is_automatic = 1 THEN `factory_sale_id` ELSE NULL END));
CREATE UNIQUE INDEX `sale_receipts_automatic_sort` ON `sale_receipts` ((CASE WHEN is_automatic = 1 THEN `sort_sale_id` ELSE NULL END));
CREATE INDEX `sale_receipts_date_status` ON `sale_receipts` (`business_date`, `status`);
CREATE INDEX `sessions_last_activity_index` ON `sessions` (`last_activity`);
CREATE INDEX `sessions_user_id_index` ON `sessions` (`user_id`);
CREATE INDEX `sort_lots_trip_fifo` ON `sort_lots` (`trip_id`, `status`, `received_at`, `id`);
CREATE INDEX `sort_movements_lot_time` ON `sort_stock_movements` (`lot_id`, `occurred_at`);
CREATE INDEX `sort_sales_date_status` ON `sort_sales` (`business_date`, `status`);
CREATE INDEX `sort_sales_trip_status` ON `sort_sales` (`trip_id`, `status`);
CREATE INDEX `trips_driver_status` ON `trips` (`driver_id`, `status`);
CREATE INDEX `trips_vehicle_status_date` ON `trips` (`vehicle_id`, `status`, `business_date`);
CREATE INDEX `users_assigned_vehicle` ON `users` (`assigned_vehicle_id`);
CREATE UNIQUE INDEX `users_email_unique` ON `users` (`email`);
CREATE INDEX `users_role_active` ON `users` (`role`, `is_active`);
CREATE INDEX `vehicle_workflow_factory_sales_creator` ON `factory_sales` (`created_by`, `business_date`, `status`);
CREATE INDEX `vehicle_workflow_factory_sales_date` ON `factory_sales` (`vehicle_id`, `business_date`, `status`);
CREATE INDEX `vehicle_workflow_sort_sales_creator` ON `sort_sales` (`created_by`, `business_date`, `status`);
CREATE INDEX `vehicle_workflow_sort_sales_date` ON `sort_sales` (`vehicle_id`, `business_date`, `status`);
CREATE INDEX `vehicle_workflow_weighings_creator` ON `weighings` (`created_by`, `business_date`, `status`);
CREATE INDEX `vehicle_workflow_weighings_date` ON `weighings` (`vehicle_id`, `business_date`, `status`);
CREATE INDEX `vehicles_active_code` ON `vehicles` (`is_active`, `code`);
CREATE INDEX `weighings_customer_status_date` ON `weighings` (`customer_id`, `status`, `weighed_at`);
CREATE INDEX `weighings_trip_status` ON `weighings` (`trip_id`, `status`);
ALTER TABLE `app_settings` ADD CONSTRAINT `app_settings_fk_0` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `attachments` ADD CONSTRAINT `attachments_fk_0` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_fk_0` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_fk_0` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_fk_1` FOREIGN KEY (`reversal_of_id`) REFERENCES `cash_entries` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_fk_2` FOREIGN KEY (`daily_expense_id`) REFERENCES `daily_expenses` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_fk_3` FOREIGN KEY (`sale_receipt_id`) REFERENCES `sale_receipts` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_fk_4` FOREIGN KEY (`payroll_payment_id`) REFERENCES `payroll_payments` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_fk_5` FOREIGN KEY (`customer_payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `cash_entries` ADD CONSTRAINT `cash_entries_fk_6` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `customer_payment_allocations` ADD CONSTRAINT `customer_payment_allocations_fk_0` FOREIGN KEY (`weighing_id`) REFERENCES `weighings` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `customer_payment_allocations` ADD CONSTRAINT `customer_payment_allocations_fk_1` FOREIGN KEY (`customer_payment_id`) REFERENCES `customer_payments` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `customer_payments` ADD CONSTRAINT `customer_payments_fk_0` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `customer_payments` ADD CONSTRAINT `customer_payments_fk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `customer_payments` ADD CONSTRAINT `customer_payments_fk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `customer_payments` ADD CONSTRAINT `customer_payments_fk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `daily_expenses` ADD CONSTRAINT `daily_expenses_fk_0` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `daily_expenses` ADD CONSTRAINT `daily_expenses_fk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `daily_expenses` ADD CONSTRAINT `daily_expenses_fk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `daily_expenses` ADD CONSTRAINT `daily_expenses_fk_3` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `daily_expenses` ADD CONSTRAINT `daily_expenses_fk_4` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_price_schedules` ADD CONSTRAINT `factory_price_schedules_fk_0` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_price_schedules` ADD CONSTRAINT `factory_price_schedules_fk_1` FOREIGN KEY (`factory_id`) REFERENCES `factories` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_returns` ADD CONSTRAINT `factory_returns_fk_0` FOREIGN KEY (`factory_sale_id`) REFERENCES `factory_sales` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_fk_0` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_fk_1` FOREIGN KEY (`replaces_sale_id`) REFERENCES `factory_sales` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_fk_2` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_fk_3` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_fk_4` FOREIGN KEY (`variance_approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_fk_5` FOREIGN KEY (`factory_price_schedule_id`) REFERENCES `factory_price_schedules` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_fk_6` FOREIGN KEY (`factory_id`) REFERENCES `factories` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_fk_7` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `factory_sales` ADD CONSTRAINT `factory_sales_fk_8` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `idempotency_requests` ADD CONSTRAINT `idempotency_requests_fk_0` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `payroll_payment_allocations` ADD CONSTRAINT `payroll_payment_allocations_fk_0` FOREIGN KEY (`weighing_loader_id`) REFERENCES `weighing_loaders` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `payroll_payment_allocations` ADD CONSTRAINT `payroll_payment_allocations_fk_1` FOREIGN KEY (`payroll_payment_id`) REFERENCES `payroll_payments` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_fk_0` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_fk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_fk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `payroll_payments` ADD CONSTRAINT `payroll_payments_fk_3` FOREIGN KEY (`worker_id`) REFERENCES `loading_workers` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `price_schedules` ADD CONSTRAINT `price_schedules_fk_0` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_fk_0` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_fk_1` FOREIGN KEY (`reversal_of_id`) REFERENCES `profit_entries` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_fk_2` FOREIGN KEY (`sort_stock_movement_id`) REFERENCES `sort_stock_movements` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_fk_3` FOREIGN KEY (`daily_expense_id`) REFERENCES `daily_expenses` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_fk_4` FOREIGN KEY (`sort_sale_id`) REFERENCES `sort_sales` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_fk_5` FOREIGN KEY (`factory_sale_id`) REFERENCES `factory_sales` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `profit_entries` ADD CONSTRAINT `profit_entries_fk_6` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sale_receipts` ADD CONSTRAINT `sale_receipts_fk_0` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sale_receipts` ADD CONSTRAINT `sale_receipts_fk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sale_receipts` ADD CONSTRAINT `sale_receipts_fk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sale_receipts` ADD CONSTRAINT `sale_receipts_fk_3` FOREIGN KEY (`sort_sale_id`) REFERENCES `sort_sales` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sale_receipts` ADD CONSTRAINT `sale_receipts_fk_4` FOREIGN KEY (`factory_sale_id`) REFERENCES `factory_sales` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_lots` ADD CONSTRAINT `sort_lots_fk_0` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_lots` ADD CONSTRAINT `sort_lots_fk_1` FOREIGN KEY (`source_factory_return_id`) REFERENCES `factory_returns` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_lots` ADD CONSTRAINT `sort_lots_fk_2` FOREIGN KEY (`source_weighing_item_id`) REFERENCES `weighing_items` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_sale_items` ADD CONSTRAINT `sort_sale_items_fk_0` FOREIGN KEY (`lot_id`) REFERENCES `sort_lots` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_sale_items` ADD CONSTRAINT `sort_sale_items_fk_1` FOREIGN KEY (`sort_sale_id`) REFERENCES `sort_sales` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_fk_0` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_fk_1` FOREIGN KEY (`replaces_sale_id`) REFERENCES `sort_sales` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_fk_2` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_fk_3` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_fk_4` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_sales` ADD CONSTRAINT `sort_sales_fk_5` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_stock_movements` ADD CONSTRAINT `sort_stock_movements_fk_0` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_stock_movements` ADD CONSTRAINT `sort_stock_movements_fk_1` FOREIGN KEY (`reversal_of_id`) REFERENCES `sort_stock_movements` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_stock_movements` ADD CONSTRAINT `sort_stock_movements_fk_2` FOREIGN KEY (`sort_sale_item_id`) REFERENCES `sort_sale_items` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `sort_stock_movements` ADD CONSTRAINT `sort_stock_movements_fk_3` FOREIGN KEY (`lot_id`) REFERENCES `sort_lots` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `trips` ADD CONSTRAINT `trips_fk_0` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `trips` ADD CONSTRAINT `trips_fk_1` FOREIGN KEY (`planned_factory_id`) REFERENCES `factories` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `trips` ADD CONSTRAINT `trips_fk_2` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `trips` ADD CONSTRAINT `trips_fk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `users` ADD CONSTRAINT `users_fk_0` FOREIGN KEY (`assigned_vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `vehicles` ADD CONSTRAINT `vehicles_fk_0` FOREIGN KEY (`default_driver_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighing_items` ADD CONSTRAINT `weighing_items_fk_0` FOREIGN KEY (`weighing_id`) REFERENCES `weighings` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighing_loaders` ADD CONSTRAINT `weighing_loaders_fk_0` FOREIGN KEY (`worker_id`) REFERENCES `loading_workers` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighing_loaders` ADD CONSTRAINT `weighing_loaders_fk_1` FOREIGN KEY (`weighing_id`) REFERENCES `weighings` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighings` ADD CONSTRAINT `weighings_fk_0` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighings` ADD CONSTRAINT `weighings_fk_1` FOREIGN KEY (`replaces_weighing_id`) REFERENCES `weighings` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighings` ADD CONSTRAINT `weighings_fk_2` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighings` ADD CONSTRAINT `weighings_fk_3` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighings` ADD CONSTRAINT `weighings_fk_4` FOREIGN KEY (`price_schedule_id`) REFERENCES `price_schedules` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighings` ADD CONSTRAINT `weighings_fk_5` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighings` ADD CONSTRAINT `weighings_fk_6` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
ALTER TABLE `weighings` ADD CONSTRAINT `weighings_fk_7` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE NO ACTION;

-- Serialize business writes, matching the previous BEGIN IMMEDIATE boundary.
DROP TABLE IF EXISTS `database_write_locks`;
CREATE TABLE `database_write_locks` (`id` BIGINT NOT NULL PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO `database_write_locks` (`id`) VALUES (1);

DELIMITER ;;

CREATE TRIGGER `attachment_source_guard` BEFORE INSERT ON `attachments` FOR EACH ROW
BEGIN
    IF NEW.entity_type = 'factory' AND NOT EXISTS ( SELECT 1 FROM factory_sales WHERE id = NEW.entity_id ) OR NEW.entity_type = 'sort' AND NOT EXISTS ( SELECT 1 FROM sort_sales WHERE id = NEW.entity_id ) OR NEW.entity_type = 'customer' AND NOT EXISTS ( SELECT 1 FROM customer_payments WHERE id = NEW.entity_id ) OR NEW.entity_type = 'payroll' AND NOT EXISTS ( SELECT 1 FROM payroll_payments WHERE id = NEW.entity_id ) OR NEW.entity_type = 'receipt' AND NOT EXISTS ( SELECT 1 FROM sale_receipts WHERE id = NEW.entity_id ) OR NEW.entity_type = 'expense' AND NOT EXISTS ( SELECT 1 FROM daily_expenses WHERE id = NEW.entity_id ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Missing attachment source';
    END IF;
END;;

CREATE TRIGGER `attachments_no_DELETE` BEFORE DELETE ON `attachments` FOR EACH ROW
BEGIN
    IF OLD.entity_type NOT IN ( 'factory' , 'sort' ) OR OLD.entity_type = 'factory' AND ( SELECT status FROM factory_sales WHERE id = OLD.entity_id ) != 'draft' OR OLD.entity_type = 'sort' AND ( SELECT status FROM sort_sales WHERE id = OLD.entity_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted proof cannot be deleted';
    END IF;
END;;

CREATE TRIGGER `attachments_no_UPDATE` BEFORE UPDATE ON `attachments` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Proof metadata is immutable';
END;;

CREATE TRIGGER `audit_logs_no_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit bersifat append-only';
END;;

CREATE TRIGGER `audit_logs_no_update` BEFORE UPDATE ON `audit_logs` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit bersifat append-only';
END;;

CREATE TRIGGER `departure_reconciliation` BEFORE UPDATE ON `trips` FOR EACH ROW
BEGIN
    IF OLD.status = 'loading' AND NEW.status = 'dispatched' THEN
    IF NEW.departed_at IS NULL OR NEW.manual_load_grams IS NOT NULL OR EXISTS ( SELECT 1 FROM weighings WHERE trip_id = NEW.id AND status = 'draft' ) OR NOT ( NEW.tbs_purchase_cost_snapshot_idr <=> ( SELECT SUM( i.purchase_amount_idr ) FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE w.trip_id = NEW.id AND w.status = 'posted' AND i.fruit_type = 'TBS' ) ) OR NOT ( NEW.tbs_loading_cost_snapshot_idr <=> ( SELECT SUM( i.loading_cost_allocated_idr ) FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE w.trip_id = NEW.id AND w.status = 'posted' AND i.fruit_type = 'TBS' ) ) OR ( NEW.cost_status = 'available' ) != EXISTS ( SELECT 1 FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE w.trip_id = NEW.id AND w.status = 'posted' AND i.fruit_type = 'TBS' ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Departure costs do not reconcile';
    END IF;
    END IF;
END;;

CREATE TRIGGER `dispatched_trip_costs_frozen` BEFORE UPDATE ON `trips` FOR EACH ROW
BEGIN
    IF OLD.departed_at IS NOT NULL AND ( NOT ( NEW.departed_at <=> OLD.departed_at ) OR NEW.vehicle_id != OLD.vehicle_id OR NEW.driver_id != OLD.driver_id OR NOT ( NEW.tbs_purchase_cost_snapshot_idr <=> OLD.tbs_purchase_cost_snapshot_idr ) OR NOT ( NEW.tbs_loading_cost_snapshot_idr <=> OLD.tbs_loading_cost_snapshot_idr ) OR NEW.cost_status != OLD.cost_status ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Departure costs are frozen';
    END IF;
END;;

CREATE TRIGGER `factory_price_schedules_no_DELETE` BEFORE DELETE ON `factory_price_schedules` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `factory_price_schedules_no_UPDATE` BEFORE UPDATE ON `factory_price_schedules` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `factory_returns_no_DELETE` BEFORE DELETE ON `factory_returns` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `factory_returns_no_UPDATE` BEFORE UPDATE ON `factory_returns` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `factory_sales_freeze` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
BEGIN
    IF OLD.status != 'draft' AND ( NOT ( NEW.id <=> OLD.id ) OR NOT ( NEW.number <=> OLD.number ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) OR NOT ( NEW.factory_id <=> OLD.factory_id ) OR NOT ( NEW.factory_price_schedule_id <=> OLD.factory_price_schedule_id ) OR NOT ( NEW.invoice_number <=> OLD.invoice_number ) OR NOT ( NEW.initial_load_grams <=> OLD.initial_load_grams ) OR NOT ( NEW.sorted_out_grams <=> OLD.sorted_out_grams ) OR NOT ( NEW.accepted_grams <=> OLD.accepted_grams ) OR NOT ( NEW.weight_difference_grams <=> OLD.weight_difference_grams ) OR NOT ( NEW.variance_approved_by <=> OLD.variance_approved_by ) OR NOT ( NEW.variance_approved_version <=> OLD.variance_approved_version ) OR NOT ( NEW.variance_reason <=> OLD.variance_reason ) OR NOT ( NEW.sold_at <=> OLD.sold_at ) OR NOT ( NEW.business_date <=> OLD.business_date ) OR NOT ( NEW.sell_price_snapshot_idr <=> OLD.sell_price_snapshot_idr ) OR NOT ( NEW.deduction_idr <=> OLD.deduction_idr ) OR NOT ( NEW.deduction_reason <=> OLD.deduction_reason ) OR NOT ( NEW.notes <=> OLD.notes ) OR NOT ( NEW.correction_reason <=> OLD.correction_reason ) OR NOT ( NEW.revenue_idr <=> OLD.revenue_idr ) OR NOT ( NEW.purchase_cost_released_idr <=> OLD.purchase_cost_released_idr ) OR NOT ( NEW.loading_cost_released_idr <=> OLD.loading_cost_released_idr ) OR NOT ( NEW.cost_status <=> OLD.cost_status ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.posted_by <=> OLD.posted_by ) OR NOT ( NEW.replaces_sale_id <=> OLD.replaces_sale_id ) OR NOT ( NEW.created_by <=> OLD.created_by ) OR NOT ( NEW.created_at <=> OLD.created_at ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'reversed' ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale is immutable';
    END IF;
END;;

CREATE TRIGGER `factory_sales_no_delete` BEFORE DELETE ON `factory_sales` FOR EACH ROW
BEGIN
    IF OLD.status != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale cannot be deleted';
    END IF;
END;;

CREATE TRIGGER `factory_sales_protect_proof` BEFORE DELETE ON `factory_sales` FOR EACH ROW
BEGIN
    IF EXISTS ( SELECT 1 FROM attachments WHERE entity_type = 'factory' AND entity_id = OLD.id ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sale proof history cannot be deleted';
    END IF;
END;;

CREATE TRIGGER `factory_sales_start_draft` BEFORE INSERT ON `factory_sales` FOR EACH ROW
BEGIN
    IF NEW.status != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sale must start as draft';
    END IF;
END;;

CREATE TRIGGER `finance_cash_balance` BEFORE INSERT ON `cash_entries` FOR EACH ROW
BEGIN
    IF NEW.occurred_at < COALESCE( ( SELECT MAX( occurred_at ) FROM cash_entries WHERE cash_account_id = NEW.cash_account_id ) , NEW.occurred_at ) OR COALESCE( ( SELECT SUM( CASE direction WHEN 'in' THEN amount_idr ELSE - amount_idr END ) FROM cash_entries WHERE cash_account_id = NEW.cash_account_id ) , 0 ) + ( CASE NEW.direction WHEN 'in' THEN NEW.amount_idr ELSE - NEW.amount_idr END ) NOT BETWEEN 0 AND 1000000000000000 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash balance or chronology invalid';
    END IF;
    IF NEW.kind != 'reversal' AND NOT EXISTS ( SELECT 1 FROM cash_accounts WHERE id = NEW.cash_account_id AND is_active = 1 ) OR NEW.kind IN ( 'opening' , 'capital' , 'withdrawal' , 'reversal' ) AND NOT EXISTS ( SELECT 1 FROM users WHERE id = NEW.created_by AND role = 'manager' AND is_active = 1 ) OR NEW.kind = 'opening' AND EXISTS ( SELECT 1 FROM cash_entries WHERE cash_account_id = NEW.cash_account_id ) OR NEW.kind IN ( 'opening' , 'capital' ) AND NEW.direction != 'in' OR NEW.kind = 'withdrawal' AND NEW.direction != 'out' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid manual cash entry';
    END IF;
    IF NEW.kind = 'reversal' AND NOT EXISTS ( SELECT 1 FROM cash_entries e WHERE e.id = NEW.reversal_of_id AND e.kind != 'reversal' AND e.cash_account_id = NEW.cash_account_id AND e.direction != NEW.direction AND e.amount_idr = NEW.amount_idr AND NEW.occurred_at >= e.occurred_at ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid cash reversal';
    END IF;
END;;

CREATE TRIGGER `finance_cash_customer` BEFORE INSERT ON `cash_entries` FOR EACH ROW
BEGIN
    IF NEW.kind = 'customer' THEN
    IF NEW.customer_payment_id IS NULL OR NOT EXISTS ( SELECT 1 FROM customer_payments p WHERE p.id = NEW.customer_payment_id AND p.status = 'posted' AND p.cash_account_id = NEW.cash_account_id AND p.amount_idr = NEW.amount_idr AND p.occurred_at = NEW.occurred_at AND p.business_date = NEW.business_date AND p.created_by = NEW.created_by AND ( p.reference <=> NEW.reference ) ) OR NEW.direction != 'out' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash does not match voucher';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_cash_entries_no_DELETE` BEFORE DELETE ON `cash_entries` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `finance_cash_entries_no_UPDATE` BEFORE UPDATE ON `cash_entries` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `finance_cash_expense` BEFORE INSERT ON `cash_entries` FOR EACH ROW
BEGIN
    IF NEW.kind = 'expense' THEN
    IF NEW.daily_expense_id IS NULL OR NOT EXISTS ( SELECT 1 FROM daily_expenses p WHERE p.id = NEW.daily_expense_id AND p.status = 'posted' AND p.cash_account_id = NEW.cash_account_id AND p.amount_idr = NEW.amount_idr AND p.occurred_at = NEW.occurred_at AND p.business_date = NEW.business_date AND p.created_by = NEW.created_by AND ( p.reference <=> NEW.reference ) ) OR NEW.direction != 'out' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash does not match voucher';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_cash_payroll` BEFORE INSERT ON `cash_entries` FOR EACH ROW
BEGIN
    IF NEW.kind = 'payroll' THEN
    IF NEW.payroll_payment_id IS NULL OR NOT EXISTS ( SELECT 1 FROM payroll_payments p WHERE p.id = NEW.payroll_payment_id AND p.status = 'posted' AND p.cash_account_id = NEW.cash_account_id AND p.amount_idr = NEW.amount_idr AND p.occurred_at = NEW.occurred_at AND p.business_date = NEW.business_date AND p.created_by = NEW.created_by AND ( p.reference <=> NEW.reference ) ) OR NEW.direction != 'out' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash does not match voucher';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_cash_receipt` BEFORE INSERT ON `cash_entries` FOR EACH ROW
BEGIN
    IF NEW.kind = 'receipt' THEN
    IF NEW.sale_receipt_id IS NULL OR NOT EXISTS ( SELECT 1 FROM sale_receipts p WHERE p.id = NEW.sale_receipt_id AND p.status = 'posted' AND p.cash_account_id = NEW.cash_account_id AND p.amount_idr = NEW.amount_idr AND p.occurred_at = NEW.occurred_at AND p.business_date = NEW.business_date AND p.created_by = NEW.created_by AND ( p.reference <=> NEW.reference ) ) OR NEW.direction != 'in' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cash does not match voucher';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_customer_payment_allocations_guard` BEFORE INSERT ON `customer_payment_allocations` FOR EACH ROW
BEGIN
    IF NOT EXISTS ( SELECT 1 FROM weighings w JOIN customer_payments p ON p.id = NEW.customer_payment_id WHERE w.id = NEW.weighing_id AND w.customer_id = p.customer_id AND w.status = 'posted' AND p.status = 'posted' AND p.occurred_at >= w.weighed_at ) OR EXISTS ( SELECT 1 FROM cash_entries WHERE customer_payment_id = NEW.customer_payment_id ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( a.amount_idr ) FROM customer_payment_allocations a JOIN customer_payments p ON p.id = a.customer_payment_id WHERE a.weighing_id = NEW.weighing_id AND p.status = 'posted' ) , 0 ) > ( SELECT w.total_purchase_idr FROM weighings w WHERE w.id = NEW.weighing_id ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( amount_idr ) FROM customer_payment_allocations WHERE customer_payment_id = NEW.customer_payment_id ) , 0 ) > ( SELECT amount_idr FROM customer_payments WHERE id = NEW.customer_payment_id ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid payment allocation';
    END IF;
END;;

CREATE TRIGGER `finance_customer_payment_allocations_no_DELETE` BEFORE DELETE ON `customer_payment_allocations` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `finance_customer_payment_allocations_no_UPDATE` BEFORE UPDATE ON `customer_payment_allocations` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `finance_customer_payments_freeze` BEFORE UPDATE ON `customer_payments` FOR EACH ROW
BEGIN
    IF NOT ( NEW.id <=> OLD.id ) OR NOT ( NEW.number <=> OLD.number ) OR NOT ( NEW.customer_id <=> OLD.customer_id ) OR NOT ( NEW.cash_account_id <=> OLD.cash_account_id ) OR NOT ( NEW.occurred_at <=> OLD.occurred_at ) OR NOT ( NEW.business_date <=> OLD.business_date ) OR NOT ( NEW.amount_idr <=> OLD.amount_idr ) OR NOT ( NEW.method <=> OLD.method ) OR NOT ( NEW.reference <=> OLD.reference ) OR NOT ( NEW.notes <=> OLD.notes ) OR NOT ( NEW.backdate_reason <=> OLD.backdate_reason ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.created_by <=> OLD.created_by ) OR NOT ( NEW.created_at <=> OLD.created_at ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'reversed' AND NEW.version = OLD.version + 1 ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher is immutable';
    END IF;
END;;

CREATE TRIGGER `finance_customer_payments_no_delete` BEFORE DELETE ON `customer_payments` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher cannot be deleted';
END;;

CREATE TRIGGER `finance_customer_payments_reverse` BEFORE UPDATE ON `customer_payments` FOR EACH ROW
BEGIN
    IF NEW.status = 'reversed' THEN
    IF NOT EXISTS ( SELECT 1 FROM cash_entries e JOIN cash_entries r ON r.reversal_of_id = e.id WHERE e.customer_payment_id = OLD.id ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher requires cash reversal';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_customer_payments_start` BEFORE INSERT ON `customer_payments` FOR EACH ROW
BEGIN
    IF NEW.status != 'posted' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher must start posted';
    END IF;
END;;

CREATE TRIGGER `finance_customer_reconcile` BEFORE INSERT ON `cash_entries` FOR EACH ROW
BEGIN
    IF NEW.kind = 'customer' THEN
    IF NOT ( NEW.amount_idr <=> ( SELECT SUM( amount_idr ) FROM customer_payment_allocations WHERE customer_payment_id = NEW.customer_payment_id ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Payment allocations do not reconcile';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_daily_expenses_freeze` BEFORE UPDATE ON `daily_expenses` FOR EACH ROW
BEGIN
    IF NOT ( NEW.id <=> OLD.id ) OR NOT ( NEW.number <=> OLD.number ) OR NOT ( NEW.expense_category_id <=> OLD.expense_category_id ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) OR NOT ( NEW.description <=> OLD.description ) OR NOT ( NEW.cash_account_id <=> OLD.cash_account_id ) OR NOT ( NEW.occurred_at <=> OLD.occurred_at ) OR NOT ( NEW.business_date <=> OLD.business_date ) OR NOT ( NEW.amount_idr <=> OLD.amount_idr ) OR NOT ( NEW.method <=> OLD.method ) OR NOT ( NEW.reference <=> OLD.reference ) OR NOT ( NEW.notes <=> OLD.notes ) OR NOT ( NEW.backdate_reason <=> OLD.backdate_reason ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.created_by <=> OLD.created_by ) OR NOT ( NEW.created_at <=> OLD.created_at ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'reversed' AND NEW.version = OLD.version + 1 ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher is immutable';
    END IF;
END;;

CREATE TRIGGER `finance_daily_expenses_no_delete` BEFORE DELETE ON `daily_expenses` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher cannot be deleted';
END;;

CREATE TRIGGER `finance_daily_expenses_reverse` BEFORE UPDATE ON `daily_expenses` FOR EACH ROW
BEGIN
    IF NEW.status = 'reversed' THEN
    IF NOT EXISTS ( SELECT 1 FROM cash_entries e JOIN cash_entries r ON r.reversal_of_id = e.id WHERE e.daily_expense_id = OLD.id ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher requires cash reversal';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_daily_expenses_start` BEFORE INSERT ON `daily_expenses` FOR EACH ROW
BEGIN
    IF NEW.status != 'posted' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher must start posted';
    END IF;
END;;

CREATE TRIGGER `finance_expense_profit` BEFORE INSERT ON `profit_entries` FOR EACH ROW
BEGIN
    IF NEW.daily_expense_id IS NOT NULL THEN
    IF NEW.category != 'operating_expense' OR NOT EXISTS ( SELECT 1 FROM daily_expenses e WHERE e.id = NEW.daily_expense_id AND e.status = 'posted' AND e.amount_idr = NEW.amount_delta_idr AND ( e.trip_id <=> NEW.trip_id ) AND e.occurred_at = NEW.occurred_at AND e.business_date = NEW.business_date ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Expense profit must match voucher';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_factory_sale_dependencies` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
BEGIN
    IF OLD.status = 'posted' AND NEW.status = 'reversed' THEN
    IF EXISTS ( SELECT 1 FROM sale_receipts WHERE factory_sale_id = OLD.id AND status = 'posted' ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reverse sale receipts first';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_payroll_payment_allocations_guard` BEFORE INSERT ON `payroll_payment_allocations` FOR EACH ROW
BEGIN
    IF NOT EXISTS ( SELECT 1 FROM weighing_loaders l JOIN weighings w ON w.id = l.weighing_id JOIN payroll_payments p ON p.id = NEW.payroll_payment_id WHERE l.id = NEW.weighing_loader_id AND l.worker_id = p.worker_id AND w.business_date BETWEEN p.period_start AND p.period_end AND w.status = 'posted' AND p.status = 'posted' AND p.occurred_at >= w.weighed_at ) OR EXISTS ( SELECT 1 FROM cash_entries WHERE payroll_payment_id = NEW.payroll_payment_id ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( a.amount_idr ) FROM payroll_payment_allocations a JOIN payroll_payments p ON p.id = a.payroll_payment_id WHERE a.weighing_loader_id = NEW.weighing_loader_id AND p.status = 'posted' ) , 0 ) > ( SELECT l.share_amount_idr FROM weighing_loaders l JOIN weighings w ON w.id = l.weighing_id WHERE l.id = NEW.weighing_loader_id ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( amount_idr ) FROM payroll_payment_allocations WHERE payroll_payment_id = NEW.payroll_payment_id ) , 0 ) > ( SELECT amount_idr FROM payroll_payments WHERE id = NEW.payroll_payment_id ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid payment allocation';
    END IF;
END;;

CREATE TRIGGER `finance_payroll_payment_allocations_no_DELETE` BEFORE DELETE ON `payroll_payment_allocations` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `finance_payroll_payment_allocations_no_UPDATE` BEFORE UPDATE ON `payroll_payment_allocations` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `finance_payroll_payments_freeze` BEFORE UPDATE ON `payroll_payments` FOR EACH ROW
BEGIN
    IF NOT ( NEW.id <=> OLD.id ) OR NOT ( NEW.number <=> OLD.number ) OR NOT ( NEW.worker_id <=> OLD.worker_id ) OR NOT ( NEW.period_start <=> OLD.period_start ) OR NOT ( NEW.period_end <=> OLD.period_end ) OR NOT ( NEW.cash_account_id <=> OLD.cash_account_id ) OR NOT ( NEW.occurred_at <=> OLD.occurred_at ) OR NOT ( NEW.business_date <=> OLD.business_date ) OR NOT ( NEW.amount_idr <=> OLD.amount_idr ) OR NOT ( NEW.method <=> OLD.method ) OR NOT ( NEW.reference <=> OLD.reference ) OR NOT ( NEW.notes <=> OLD.notes ) OR NOT ( NEW.backdate_reason <=> OLD.backdate_reason ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.created_by <=> OLD.created_by ) OR NOT ( NEW.created_at <=> OLD.created_at ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'reversed' AND NEW.version = OLD.version + 1 ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher is immutable';
    END IF;
END;;

CREATE TRIGGER `finance_payroll_payments_no_delete` BEFORE DELETE ON `payroll_payments` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher cannot be deleted';
END;;

CREATE TRIGGER `finance_payroll_payments_reverse` BEFORE UPDATE ON `payroll_payments` FOR EACH ROW
BEGIN
    IF NEW.status = 'reversed' THEN
    IF NOT EXISTS ( SELECT 1 FROM cash_entries e JOIN cash_entries r ON r.reversal_of_id = e.id WHERE e.payroll_payment_id = OLD.id ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher requires cash reversal';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_payroll_payments_start` BEFORE INSERT ON `payroll_payments` FOR EACH ROW
BEGIN
    IF NEW.status != 'posted' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher must start posted';
    END IF;
END;;

CREATE TRIGGER `finance_payroll_reconcile` BEFORE INSERT ON `cash_entries` FOR EACH ROW
BEGIN
    IF NEW.kind = 'payroll' THEN
    IF NOT ( NEW.amount_idr <=> ( SELECT SUM( amount_idr ) FROM payroll_payment_allocations WHERE payroll_payment_id = NEW.payroll_payment_id ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Payment allocations do not reconcile';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_receipt_source` BEFORE INSERT ON `sale_receipts` FOR EACH ROW
BEGIN
    IF NEW.factory_sale_id IS NOT NULL AND ( NOT EXISTS ( SELECT 1 FROM factory_sales WHERE id = NEW.factory_sale_id AND status = 'posted' AND sold_at <= NEW.occurred_at ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( amount_idr ) FROM sale_receipts WHERE factory_sale_id = NEW.factory_sale_id AND status = 'posted' ) , 0 ) > ( SELECT revenue_idr FROM factory_sales WHERE id = NEW.factory_sale_id ) ) OR NEW.sort_sale_id IS NOT NULL AND ( NOT EXISTS ( SELECT 1 FROM sort_sales WHERE id = NEW.sort_sale_id AND status = 'posted' AND sold_at <= NEW.occurred_at ) OR NEW.amount_idr + COALESCE( ( SELECT SUM( amount_idr ) FROM sale_receipts WHERE sort_sale_id = NEW.sort_sale_id AND status = 'posted' ) , 0 ) > ( SELECT revenue_idr FROM sort_sales WHERE id = NEW.sort_sale_id ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid sale receipt';
    END IF;
END;;

CREATE TRIGGER `finance_sale_receipts_freeze` BEFORE UPDATE ON `sale_receipts` FOR EACH ROW
BEGIN
    IF NOT ( NEW.id <=> OLD.id ) OR NOT ( NEW.number <=> OLD.number ) OR NOT ( NEW.factory_sale_id <=> OLD.factory_sale_id ) OR NOT ( NEW.sort_sale_id <=> OLD.sort_sale_id ) OR NOT ( NEW.cash_account_id <=> OLD.cash_account_id ) OR NOT ( NEW.occurred_at <=> OLD.occurred_at ) OR NOT ( NEW.business_date <=> OLD.business_date ) OR NOT ( NEW.amount_idr <=> OLD.amount_idr ) OR NOT ( NEW.method <=> OLD.method ) OR NOT ( NEW.reference <=> OLD.reference ) OR NOT ( NEW.notes <=> OLD.notes ) OR NOT ( NEW.backdate_reason <=> OLD.backdate_reason ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.created_by <=> OLD.created_by ) OR NOT ( NEW.created_at <=> OLD.created_at ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'reversed' AND NEW.version = OLD.version + 1 ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher is immutable';
    END IF;
END;;

CREATE TRIGGER `finance_sale_receipts_no_delete` BEFORE DELETE ON `sale_receipts` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher cannot be deleted';
END;;

CREATE TRIGGER `finance_sale_receipts_reverse` BEFORE UPDATE ON `sale_receipts` FOR EACH ROW
BEGIN
    IF NEW.status = 'reversed' THEN
    IF NOT EXISTS ( SELECT 1 FROM cash_entries e JOIN cash_entries r ON r.reversal_of_id = e.id WHERE e.sale_receipt_id = OLD.id ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher requires cash reversal';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_sale_receipts_start` BEFORE INSERT ON `sale_receipts` FOR EACH ROW
BEGIN
    IF NEW.status != 'posted' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Voucher must start posted';
    END IF;
END;;

CREATE TRIGGER `finance_sort_sale_dependencies` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
BEGIN
    IF OLD.status = 'posted' AND NEW.status = 'reversed' THEN
    IF EXISTS ( SELECT 1 FROM sale_receipts WHERE sort_sale_id = OLD.id AND status = 'posted' ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reverse sale receipts first';
    END IF;
    END IF;
END;;

CREATE TRIGGER `finance_weighing_dependencies` BEFORE UPDATE ON `weighings` FOR EACH ROW
BEGIN
    IF OLD.status = 'posted' AND NEW.status = 'voided' THEN
    IF EXISTS ( SELECT 1 FROM customer_payment_allocations a JOIN customer_payments p ON p.id = a.customer_payment_id WHERE a.weighing_id = OLD.id AND p.status = 'posted' ) OR EXISTS ( SELECT 1 FROM payroll_payment_allocations a JOIN payroll_payments p ON p.id = a.payroll_payment_id JOIN weighing_loaders l ON l.id = a.weighing_loader_id WHERE l.weighing_id = OLD.id AND p.status = 'posted' ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reverse customer and payroll payments first';
    END IF;
    END IF;
END;;

CREATE TRIGGER `freeze_posted_weighing` BEFORE UPDATE ON `weighings` FOR EACH ROW
BEGIN
    IF OLD.status != 'draft' AND ( NEW.customer_id != OLD.customer_id OR NEW.trip_id != OLD.trip_id OR NEW.number != OLD.number OR NEW.weighed_at != OLD.weighed_at OR NEW.business_date != OLD.business_date OR NOT ( NEW.price_schedule_id <=> OLD.price_schedule_id ) OR NOT ( NEW.loading_rate_snapshot_idr <=> OLD.loading_rate_snapshot_idr ) OR NOT ( NEW.loading_basis_grams <=> OLD.loading_basis_grams ) OR NOT ( NEW.total_purchase_idr <=> OLD.total_purchase_idr ) OR NOT ( NEW.total_loading_wage_idr <=> OLD.total_loading_wage_idr ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.posted_by <=> OLD.posted_by ) OR NEW.created_by != OLD.created_by OR NOT ( NEW.replaces_weighing_id <=> OLD.replaces_weighing_id ) OR NOT ( NEW.backdate_reason <=> OLD.backdate_reason ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'voided' ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted weighing is immutable';
    END IF;
END;;

CREATE TRIGGER `idempotency_requests_no_DELETE` BEFORE DELETE ON `idempotency_requests` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `idempotency_requests_no_UPDATE` BEFORE UPDATE ON `idempotency_requests` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `no_delete_posted_weighing` BEFORE DELETE ON `weighings` FOR EACH ROW
BEGIN
    IF OLD.status != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted weighing cannot be deleted';
    END IF;
END;;

CREATE TRIGGER `price_schedules_no_DELETE` BEFORE DELETE ON `price_schedules` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `price_schedules_no_UPDATE` BEFORE UPDATE ON `price_schedules` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `profit_entries_no_DELETE` BEFORE DELETE ON `profit_entries` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `profit_entries_no_UPDATE` BEFORE UPDATE ON `profit_entries` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `profit_reversal_guard` BEFORE INSERT ON `profit_entries` FOR EACH ROW
BEGIN
    IF NEW.reversal_of_id IS NOT NULL THEN
    IF NOT EXISTS ( SELECT 1 FROM profit_entries p WHERE p.id = NEW.reversal_of_id AND p.reversal_of_id IS NULL AND NEW.amount_delta_idr = - p.amount_delta_idr AND NEW.category = p.category AND ( NEW.trip_id <=> p.trip_id ) AND NEW.occurred_at >= p.occurred_at ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid profit reversal';
    END IF;
    END IF;
END;;

CREATE TRIGGER `reconcile_factory_post` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'legacy' AND OLD.status = 'draft' AND NEW.status = 'posted' THEN
    IF ( SELECT status FROM trips WHERE id = NEW.trip_id ) NOT IN ( 'dispatched' , 'correction_pending' ) OR NOT ( NEW.revenue_idr <=> ( ( NEW.accepted_grams * NEW.sell_price_snapshot_idr + 500 ) DIV 1000 - NEW.deduction_idr ) ) OR NOT ( NEW.cost_status <=> ( SELECT cost_status FROM trips WHERE id = NEW.trip_id ) ) OR ( NEW.sorted_out_grams > 0 ) != EXISTS ( SELECT 1 FROM factory_returns WHERE factory_sale_id = NEW.id AND weight_grams = NEW.sorted_out_grams ) OR NEW.cost_status = 'available' AND ( NOT ( NEW.purchase_cost_released_idr <=> ( ( SELECT tbs_purchase_cost_snapshot_idr FROM trips WHERE id = NEW.trip_id ) - COALESCE( ( SELECT purchase_cost_carried_idr FROM factory_returns WHERE factory_sale_id = NEW.id ) , 0 ) ) ) OR NOT ( NEW.loading_cost_released_idr <=> ( ( SELECT tbs_loading_cost_snapshot_idr FROM trips WHERE id = NEW.trip_id ) - COALESCE( ( SELECT loading_cost_carried_idr FROM factory_returns WHERE factory_sale_id = NEW.id ) , 0 ) ) ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Factory sale does not reconcile';
    END IF;
    IF ( NEW.weight_difference_grams < 0 OR NEW.weight_difference_grams * 10000 > NEW.initial_load_grams * 200 ) AND ( NOT ( NEW.variance_approved_version <=> OLD.version ) OR NEW.variance_reason IS NULL OR length( trim( NEW.variance_reason ) ) = 0 OR NOT EXISTS ( SELECT 1 FROM users WHERE id = NEW.variance_approved_by AND role = 'manager' AND is_active = 1 ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Factory variance needs approval';
    END IF;
    END IF;
END;;

CREATE TRIGGER `reconcile_sort_post` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'legacy' AND OLD.status = 'draft' AND NEW.status = 'posted' THEN
    IF NOT ( NEW.manual_sold_grams <=> ( SELECT SUM( weight_grams ) FROM sort_sale_items WHERE sort_sale_id = NEW.id ) ) OR NOT ( NEW.revenue_idr <=> ( ( SELECT SUM( gross_revenue_idr ) FROM sort_sale_items WHERE sort_sale_id = NEW.id ) - NEW.deduction_idr ) ) OR EXISTS ( SELECT 1 FROM sort_sale_items i JOIN sort_lots l ON l.id = i.lot_id WHERE i.sort_sale_id = NEW.id AND ( l.trip_id != NEW.trip_id OR i.sell_price_snapshot_idr != NEW.sell_price_snapshot_idr ) ) OR NEW.cost_status = 'available' AND ( EXISTS ( SELECT 1 FROM sort_sale_items WHERE sort_sale_id = NEW.id AND purchase_cost_released_idr IS NULL ) OR NOT ( NEW.purchase_cost_released_idr <=> ( SELECT SUM( purchase_cost_released_idr ) FROM sort_sale_items WHERE sort_sale_id = NEW.id ) ) OR NOT ( NEW.loading_cost_released_idr <=> ( SELECT SUM( loading_cost_released_idr ) FROM sort_sale_items WHERE sort_sale_id = NEW.id ) ) ) OR NEW.cost_status = 'unavailable' AND NOT EXISTS ( SELECT 1 FROM sort_sale_items WHERE sort_sale_id = NEW.id AND purchase_cost_released_idr IS NULL ) OR EXISTS ( SELECT 1 FROM sort_sale_items i WHERE i.sort_sale_id = NEW.id AND NOT EXISTS ( SELECT 1 FROM sort_stock_movements m WHERE m.sort_sale_item_id = i.id AND m.lot_id = i.lot_id AND m.quantity_delta_grams = - i.weight_grams AND ( m.purchase_cost_delta_idr <=> - i.purchase_cost_released_idr ) AND ( m.loading_cost_delta_idr <=> - i.loading_cost_released_idr ) ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sort sale does not reconcile';
    END IF;
    END IF;
END;;

CREATE TRIGGER `reconcile_weighing_on_post` BEFORE UPDATE ON `weighings` FOR EACH ROW
BEGIN
    IF OLD.status = 'draft' AND NEW.status = 'posted' THEN
    IF ( SELECT COUNT( * ) FROM weighing_items WHERE weighing_id = NEW.id ) = 0 OR ( SELECT COUNT( * ) FROM weighing_loaders WHERE weighing_id = NEW.id ) = 0 OR EXISTS ( SELECT 1 FROM weighing_items WHERE weighing_id = NEW.id AND ( buy_price_snapshot_idr IS NULL OR purchase_amount_idr IS NULL OR loading_cost_allocated_idr IS NULL OR NOT ( loading_basis_grams <=> weight_grams ) ) ) OR EXISTS ( SELECT 1 FROM weighing_loaders WHERE weighing_id = NEW.id AND ( share_amount_idr IS NULL OR allocation_order IS NULL OR NOT ( member_count_snapshot <=> ( SELECT COUNT( * ) FROM weighing_loaders WHERE weighing_id = NEW.id ) ) ) ) OR NOT ( NEW.loading_basis_grams <=> ( SELECT SUM( weight_grams ) FROM weighing_items WHERE weighing_id = NEW.id ) ) OR NOT ( NEW.total_purchase_idr <=> ( SELECT SUM( purchase_amount_idr ) FROM weighing_items WHERE weighing_id = NEW.id ) ) OR NOT ( NEW.total_loading_wage_idr <=> ( SELECT SUM( loading_cost_allocated_idr ) FROM weighing_items WHERE weighing_id = NEW.id ) ) OR NOT ( NEW.total_loading_wage_idr <=> ( SELECT SUM( share_amount_idr ) FROM weighing_loaders WHERE weighing_id = NEW.id ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Weighing allocations do not reconcile';
    END IF;
    END IF;
END;;

CREATE TRIGGER `sale_items_freeze_DELETE` BEFORE DELETE ON `sort_sale_items` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM sort_sales WHERE id = OLD.sort_sale_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale detail is immutable';
    END IF;
END;;

CREATE TRIGGER `sale_items_freeze_INSERT` BEFORE INSERT ON `sort_sale_items` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM sort_sales WHERE id = NEW.sort_sale_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale detail is immutable';
    END IF;
END;;

CREATE TRIGGER `sale_items_freeze_UPDATE` BEFORE UPDATE ON `sort_sale_items` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM sort_sales WHERE id = OLD.sort_sale_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale detail is immutable';
    END IF;
END;;

CREATE TRIGGER `sale_items_freeze_move` BEFORE UPDATE ON `sort_sale_items` FOR EACH ROW
BEGIN
    IF NEW.sort_sale_id != OLD.sort_sale_id AND ( SELECT status FROM sort_sales WHERE id = NEW.sort_sale_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cannot move detail to posted sale';
    END IF;
END;;

CREATE TRIGGER `sale_receipts_automatic_frozen` BEFORE UPDATE ON `sale_receipts` FOR EACH ROW
BEGIN
    IF NOT ( NEW.is_automatic <=> OLD.is_automatic ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Automatic sale receipt provenance is immutable';
    END IF;
END;;

CREATE TRIGGER `sort_lot_frozen` BEFORE UPDATE ON `sort_lots` FOR EACH ROW
BEGIN
    IF NEW.id != OLD.id OR NEW.source_type != OLD.source_type OR NOT ( NEW.source_weighing_item_id <=> OLD.source_weighing_item_id ) OR NOT ( NEW.source_factory_return_id <=> OLD.source_factory_return_id ) OR NEW.trip_id != OLD.trip_id OR NEW.received_at != OLD.received_at OR NEW.initial_grams != OLD.initial_grams OR NOT ( NEW.initial_purchase_cost_idr <=> OLD.initial_purchase_cost_idr ) OR NOT ( NEW.initial_loading_cost_idr <=> OLD.initial_loading_cost_idr ) OR NEW.cost_status != OLD.cost_status OR NOT ( OLD.status = 'active' AND NEW.status = 'voided' AND ( SELECT SUM( quantity_delta_grams ) FROM sort_stock_movements WHERE lot_id = OLD.id ) = 0 ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Source lot is immutable';
    END IF;
END;;

CREATE TRIGGER `sort_sales_freeze` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
BEGIN
    IF OLD.status != 'draft' AND ( NOT ( NEW.id <=> OLD.id ) OR NOT ( NEW.number <=> OLD.number ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) OR NOT ( NEW.buyer_name <=> OLD.buyer_name ) OR NOT ( NEW.buyer_contact <=> OLD.buyer_contact ) OR NOT ( NEW.manual_sold_grams <=> OLD.manual_sold_grams ) OR NOT ( NEW.sold_at <=> OLD.sold_at ) OR NOT ( NEW.business_date <=> OLD.business_date ) OR NOT ( NEW.sell_price_snapshot_idr <=> OLD.sell_price_snapshot_idr ) OR NOT ( NEW.deduction_idr <=> OLD.deduction_idr ) OR NOT ( NEW.deduction_reason <=> OLD.deduction_reason ) OR NOT ( NEW.notes <=> OLD.notes ) OR NOT ( NEW.correction_reason <=> OLD.correction_reason ) OR NOT ( NEW.revenue_idr <=> OLD.revenue_idr ) OR NOT ( NEW.purchase_cost_released_idr <=> OLD.purchase_cost_released_idr ) OR NOT ( NEW.loading_cost_released_idr <=> OLD.loading_cost_released_idr ) OR NOT ( NEW.cost_status <=> OLD.cost_status ) OR NOT ( NEW.posted_at <=> OLD.posted_at ) OR NOT ( NEW.posted_by <=> OLD.posted_by ) OR NOT ( NEW.replaces_sale_id <=> OLD.replaces_sale_id ) OR NOT ( NEW.created_by <=> OLD.created_by ) OR NOT ( NEW.created_at <=> OLD.created_at ) OR NOT ( OLD.status = 'posted' AND NEW.status = 'reversed' ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale is immutable';
    END IF;
END;;

CREATE TRIGGER `sort_sales_no_delete` BEFORE DELETE ON `sort_sales` FOR EACH ROW
BEGIN
    IF OLD.status != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted sale cannot be deleted';
    END IF;
END;;

CREATE TRIGGER `sort_sales_protect_proof` BEFORE DELETE ON `sort_sales` FOR EACH ROW
BEGIN
    IF EXISTS ( SELECT 1 FROM attachments WHERE entity_type = 'sort' AND entity_id = OLD.id ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sale proof history cannot be deleted';
    END IF;
END;;

CREATE TRIGGER `sort_sales_start_draft` BEFORE INSERT ON `sort_sales` FOR EACH ROW
BEGIN
    IF NEW.status != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sale must start as draft';
    END IF;
END;;

CREATE TRIGGER `sort_stock_movements_no_DELETE` BEFORE DELETE ON `sort_stock_movements` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `sort_stock_movements_no_UPDATE` BEFORE UPDATE ON `sort_stock_movements` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only record';
END;;

CREATE TRIGGER `stock_movement_guard` BEFORE INSERT ON `sort_stock_movements` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM sort_lots WHERE id = NEW.lot_id ) != 'active' OR NEW.occurred_at < ( SELECT received_at FROM sort_lots WHERE id = NEW.lot_id ) OR NEW.occurred_at < COALESCE( ( SELECT MAX( occurred_at ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , NEW.occurred_at ) OR ( ( SELECT cost_status FROM sort_lots WHERE id = NEW.lot_id ) = 'available' ) != ( NEW.purchase_cost_delta_idr IS NOT NULL AND NEW.loading_cost_delta_idr IS NOT NULL ) OR COALESCE( ( SELECT SUM( quantity_delta_grams ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.quantity_delta_grams NOT BETWEEN 0 AND ( SELECT initial_grams FROM sort_lots WHERE id = NEW.lot_id ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid stock balance';
    END IF;
    IF NEW.kind = 'receipt' AND NOT EXISTS ( SELECT 1 FROM sort_lots l WHERE l.id = NEW.lot_id AND NEW.quantity_delta_grams = l.initial_grams AND ( NEW.purchase_cost_delta_idr <=> l.initial_purchase_cost_idr ) AND ( NEW.loading_cost_delta_idr <=> l.initial_loading_cost_idr ) ) OR NEW.kind IN ( 'sale' , 'adjustment' ) AND ( NEW.purchase_cost_delta_idr > 0 OR NEW.loading_cost_delta_idr > 0 ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Stock movement does not match source';
    END IF;
    IF NEW.kind = 'reversal' AND NOT EXISTS ( SELECT 1 FROM sort_stock_movements m WHERE m.id = NEW.reversal_of_id AND m.kind != 'reversal' AND m.lot_id = NEW.lot_id AND NEW.quantity_delta_grams = - m.quantity_delta_grams AND ( NEW.purchase_cost_delta_idr <=> - m.purchase_cost_delta_idr ) AND ( NEW.loading_cost_delta_idr <=> - m.loading_cost_delta_idr ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid stock reversal';
    END IF;
    IF NEW.purchase_cost_delta_idr IS NOT NULL AND ( COALESCE( ( SELECT SUM( purchase_cost_delta_idr ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.purchase_cost_delta_idr NOT BETWEEN 0 AND ( SELECT initial_purchase_cost_idr FROM sort_lots WHERE id = NEW.lot_id ) OR COALESCE( ( SELECT SUM( loading_cost_delta_idr ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.loading_cost_delta_idr NOT BETWEEN 0 AND ( SELECT initial_loading_cost_idr FROM sort_lots WHERE id = NEW.lot_id ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid stock cost balance';
    END IF;
    IF COALESCE( ( SELECT SUM( quantity_delta_grams ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.quantity_delta_grams = 0 AND NEW.purchase_cost_delta_idr IS NOT NULL AND ( COALESCE( ( SELECT SUM( purchase_cost_delta_idr ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.purchase_cost_delta_idr != 0 OR COALESCE( ( SELECT SUM( loading_cost_delta_idr ) FROM sort_stock_movements WHERE lot_id = NEW.lot_id ) , 0 ) + NEW.loading_cost_delta_idr != 0 ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Empty lot must have zero remaining cost';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_factory_price_INSERT` BEFORE INSERT ON `factory_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'direct' AND NOT EXISTS ( SELECT 1 FROM factory_price_schedules p WHERE p.id = NEW.factory_price_schedule_id AND p.factory_id = NEW.factory_id AND p.tbs_sell_price_idr = NEW.sell_price_snapshot_idr AND p.effective_from <= NEW.sold_at ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct factory sale needs its matching price snapshot';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_factory_price_UPDATE` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'direct' AND NOT EXISTS ( SELECT 1 FROM factory_price_schedules p WHERE p.id = NEW.factory_price_schedule_id AND p.factory_id = NEW.factory_id AND p.tbs_sell_price_idr = NEW.sell_price_snapshot_idr AND p.effective_from <= NEW.sold_at ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct factory sale needs its matching price snapshot';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_factory_return_INSERT` BEFORE INSERT ON `factory_returns` FOR EACH ROW
BEGIN
    IF ( SELECT workflow FROM factory_sales WHERE id = NEW.factory_sale_id ) = 'direct' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct factory sales do not create return stock';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_factory_return_UPDATE` BEFORE UPDATE ON `factory_returns` FOR EACH ROW
BEGIN
    IF ( SELECT workflow FROM factory_sales WHERE id = NEW.factory_sale_id ) = 'direct' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct factory sales do not create return stock';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_factory_sales_INSERT` BEFORE INSERT ON `factory_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_factory_sales_UPDATE` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_factory_sales_freeze` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow != OLD.workflow OR ( OLD.status != 'draft' AND ( NOT ( NEW.vehicle_id <=> OLD.vehicle_id ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transaction workflow and posted vehicle are immutable';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_factory_sales_post` BEFORE UPDATE ON `factory_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'direct' AND OLD.status = 'draft' AND NEW.status = 'posted' THEN
    IF NOT ( NEW.revenue_idr <=> ( ( NEW.accepted_grams * NEW.sell_price_snapshot_idr + 500 ) DIV 1000 - NEW.deduction_idr ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct sale revenue does not reconcile';
    END IF;
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_sort_item_INSERT` BEFORE INSERT ON `sort_sale_items` FOR EACH ROW
BEGIN
    IF ( SELECT workflow FROM sort_sales WHERE id = NEW.sort_sale_id ) = 'direct' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct sort sales do not allocate FIFO';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_sort_item_UPDATE` BEFORE UPDATE ON `sort_sale_items` FOR EACH ROW
BEGIN
    IF ( SELECT workflow FROM sort_sales WHERE id = NEW.sort_sale_id ) = 'direct' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct sort sales do not allocate FIFO';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_sort_lot_INSERT` BEFORE INSERT ON `sort_lots` FOR EACH ROW
BEGIN
    IF EXISTS ( SELECT 1 FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE i.id = NEW.source_weighing_item_id AND w.workflow = 'direct' ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct weighings do not create stock';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_sort_lot_UPDATE` BEFORE UPDATE ON `sort_lots` FOR EACH ROW
BEGIN
    IF EXISTS ( SELECT 1 FROM weighing_items i JOIN weighings w ON w.id = i.weighing_id WHERE i.id = NEW.source_weighing_item_id AND w.workflow = 'direct' ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct weighings do not create stock';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_sort_sales_INSERT` BEFORE INSERT ON `sort_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_sort_sales_UPDATE` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_sort_sales_freeze` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow != OLD.workflow OR ( OLD.status != 'draft' AND ( NOT ( NEW.vehicle_id <=> OLD.vehicle_id ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transaction workflow and posted vehicle are immutable';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_sort_sales_post` BEFORE UPDATE ON `sort_sales` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'direct' AND OLD.status = 'draft' AND NEW.status = 'posted' THEN
    IF NOT ( NEW.revenue_idr <=> ( ( NEW.manual_sold_grams * NEW.sell_price_snapshot_idr + 500 ) DIV 1000 - NEW.deduction_idr ) ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Direct sale revenue does not reconcile';
    END IF;
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_weighings_INSERT` BEFORE INSERT ON `weighings` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_weighings_UPDATE` BEFORE UPDATE ON `weighings` FOR EACH ROW
BEGIN
    IF NEW.workflow = 'legacy' AND NOT ( NEW.vehicle_id <=> ( SELECT vehicle_id FROM trips WHERE id = NEW.trip_id ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Legacy transaction vehicle must match its trip';
    END IF;
END;;

CREATE TRIGGER `vehicle_workflow_weighings_freeze` BEFORE UPDATE ON `weighings` FOR EACH ROW
BEGIN
    IF NEW.workflow != OLD.workflow OR ( OLD.status != 'draft' AND ( NOT ( NEW.vehicle_id <=> OLD.vehicle_id ) OR NOT ( NEW.trip_id <=> OLD.trip_id ) ) ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transaction workflow and posted vehicle are immutable';
    END IF;
END;;

CREATE TRIGGER `weighing_after_departure_delete` BEFORE DELETE ON `weighings` FOR EACH ROW
BEGIN
    IF ( SELECT departed_at FROM trips WHERE id = OLD.trip_id ) IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dispatched ticket is frozen';
    END IF;
END;;

CREATE TRIGGER `weighing_after_departure_insert` BEFORE INSERT ON `weighings` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM trips WHERE id = NEW.trip_id ) != 'loading' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Trip no longer accepts weighings';
    END IF;
END;;

CREATE TRIGGER `weighing_after_departure_update` BEFORE UPDATE ON `weighings` FOR EACH ROW
BEGIN
    IF ( SELECT departed_at FROM trips WHERE id = OLD.trip_id ) IS NOT NULL OR ( SELECT status FROM trips WHERE id = NEW.trip_id ) != 'loading' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dispatched ticket is frozen';
    END IF;
END;;

CREATE TRIGGER `weighing_items_freeze_DELETE` BEFORE DELETE ON `weighing_items` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM weighings WHERE id = OLD.weighing_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted detail is immutable';
    END IF;
END;;

CREATE TRIGGER `weighing_items_freeze_INSERT` BEFORE INSERT ON `weighing_items` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM weighings WHERE id = NEW.weighing_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted detail is immutable';
    END IF;
END;;

CREATE TRIGGER `weighing_items_freeze_UPDATE` BEFORE UPDATE ON `weighing_items` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM weighings WHERE id = OLD.weighing_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted detail is immutable';
    END IF;
END;;

CREATE TRIGGER `weighing_items_freeze_move` BEFORE UPDATE ON `weighing_items` FOR EACH ROW
BEGIN
    IF NEW.weighing_id != OLD.weighing_id AND ( SELECT status FROM weighings WHERE id = NEW.weighing_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cannot move detail to posted weighing';
    END IF;
END;;

CREATE TRIGGER `weighing_loaders_freeze_DELETE` BEFORE DELETE ON `weighing_loaders` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM weighings WHERE id = OLD.weighing_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted detail is immutable';
    END IF;
END;;

CREATE TRIGGER `weighing_loaders_freeze_INSERT` BEFORE INSERT ON `weighing_loaders` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM weighings WHERE id = NEW.weighing_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted detail is immutable';
    END IF;
END;;

CREATE TRIGGER `weighing_loaders_freeze_UPDATE` BEFORE UPDATE ON `weighing_loaders` FOR EACH ROW
BEGIN
    IF ( SELECT status FROM weighings WHERE id = OLD.weighing_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posted detail is immutable';
    END IF;
END;;

CREATE TRIGGER `weighing_loaders_freeze_move` BEFORE UPDATE ON `weighing_loaders` FOR EACH ROW
BEGIN
    IF NEW.weighing_id != OLD.weighing_id AND ( SELECT status FROM weighings WHERE id = NEW.weighing_id ) != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cannot move detail to posted weighing';
    END IF;
END;;

CREATE TRIGGER `weighings_must_start_as_draft` BEFORE INSERT ON `weighings` FOR EACH ROW
BEGIN
    IF NEW.status != 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Weighing must start as draft';
    END IF;
END;;

DELIMITER ;
SET FOREIGN_KEY_CHECKS=1;

-- Record the historical migrations incorporated in this baseline.
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (4,'2026_10_09_000001_add_roles_and_master_data',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (5,'2026_10_09_000002_create_prices_and_weighings',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (6,'2026_10_09_000003_create_sales_and_inventory',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (7,'2026_10_09_000004_create_finance',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (8,'2026_10_09_000005_prepare_direct_vehicle_workflow',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (9,'2026_10_10_000001_mark_automatic_sale_receipts',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (10,'2026_10_10_000002_assign_driver_vehicle',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (11,'2026_10_15_000001_add_whatsapp_notifications',1);
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES (12,'2026_10_16_000001_remove_whatsapp_notifications',1);
