-- PoultryPro database schema. Compatible with MySQL 8.0+ and MariaDB 10.4+.
-- Inventory tables hold balances; the *_transactions tables are the append-only history.
-- source_key columns are UNIQUE so that a document can never post to a ledger twice.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- Access control
CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  slug VARCHAR(60) NOT NULL UNIQUE,
  all_farms TINYINT(1) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  module VARCHAR(60) NOT NULL,
  label VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  password_changed_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  KEY idx_login_email (email, attempted_at),
  KEY idx_login_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  email VARCHAR(160) NOT NULL,
  token_hash CHAR(64) NULL UNIQUE COMMENT 'SHA-256 of the emailed token. NULL when the email matched no account.',
  ip_address VARCHAR(45) NOT NULL,
  requested_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  KEY idx_reset_email (email, requested_at),
  KEY idx_reset_ip (ip_address, requested_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id BIGINT UNSIGNED NULL,
  description VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_user (user_id, created_at),
  KEY idx_activity_entity (entity_type, entity_id),
  KEY idx_activity_created (created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Parties
CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  contact_number VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  address VARCHAR(255) NULL,
  supplied_products VARCHAR(255) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_supplier_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  business_name VARCHAR(160) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  address VARCHAR(255) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_customer_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Farm structure
CREATE TABLE farms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  code VARCHAR(30) NOT NULL UNIQUE,
  location VARCHAR(255) NULL,
  manager_name VARCHAR(120) NULL,
  contact_phone VARCHAR(40) NULL,
  contact_email VARCHAR(160) NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 0,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_farms (
  user_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, farm_id),
  CONSTRAINT fk_uf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uf_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sheds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farm_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  shed_number VARCHAR(30) NOT NULL,
  shed_type ENUM('broiler','layer') NOT NULL,
  max_capacity INT UNSIGNED NOT NULL,
  current_bird_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_shed_number (farm_id, shed_number),
  CONSTRAINT fk_sheds_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT chk_shed_capacity CHECK (current_bird_count <= max_capacity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  shed_id INT UNSIGNED NOT NULL COMMENT 'Shed the batch was first placed in',
  breed VARCHAR(80) NULL,
  batch_type ENUM('broiler','layer','breeder') NOT NULL,
  purchase_date DATE NOT NULL,
  age_at_purchase_days INT UNSIGNED NOT NULL DEFAULT 0,
  initial_quantity INT UNSIGNED NOT NULL,
  supplier_id INT UNSIGNED NULL,
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Per bird',
  avg_bird_cost DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'Moving average, used for cost of birds sold or lost',
  expected_production_date DATE NULL,
  status ENUM('active','closed','cancelled') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_batch_farm (farm_id, status),
  KEY idx_batch_shed (shed_id),
  CONSTRAINT fk_batch_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_batch_shed FOREIGN KEY (shed_id) REFERENCES sheds(id),
  CONSTRAINT fk_batch_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Birds
CREATE TABLE bird_inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  shed_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bird_inv (batch_id, shed_id),
  KEY idx_bird_inv_farm (farm_id),
  CONSTRAINT fk_binv_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_binv_shed FOREIGN KEY (shed_id) REFERENCES sheds(id),
  CONSTRAINT fk_binv_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT chk_binv_nonneg CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bird_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  shed_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  txn_date DATE NOT NULL,
  txn_type ENUM('opening','purchase','transfer_in','transfer_out','mortality','sale','adjustment_in','adjustment_out') NOT NULL,
  quantity INT NOT NULL COMMENT 'Signed: positive adds birds. Reversals carry the opposite sign and the same type.',
  balance_after INT NOT NULL,
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  reference_type VARCHAR(40) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  source_key VARCHAR(100) NOT NULL UNIQUE,
  reversal_of BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_btx_batch (batch_id, txn_date),
  KEY idx_btx_farm (farm_id, txn_date),
  KEY idx_btx_ref (reference_type, reference_id),
  CONSTRAINT fk_btx_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_btx_shed FOREIGN KEY (shed_id) REFERENCES sheds(id),
  CONSTRAINT fk_btx_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_btx_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bird_transfers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  from_shed_id INT UNSIGNED NOT NULL,
  to_shed_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  transfer_date DATE NOT NULL,
  notes VARCHAR(255) NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_transfer_batch (batch_id, transfer_date),
  CONSTRAINT fk_tr_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_tr_from FOREIGN KEY (from_shed_id) REFERENCES sheds(id),
  CONSTRAINT fk_tr_to FOREIGN KEY (to_shed_id) REFERENCES sheds(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bird_adjustments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  shed_id INT UNSIGNED NOT NULL,
  direction ENUM('increase','reduction') NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  adjustment_date DATE NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  approved_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_adj_batch (batch_id, adjustment_date),
  CONSTRAINT fk_adj_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_adj_shed FOREIGN KEY (shed_id) REFERENCES sheds(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mortality_reasons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mortality_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_date DATE NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  shed_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  reason_id INT UNSIGNED NULL,
  quantity INT UNSIGNED NOT NULL,
  notes VARCHAR(255) NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mort_date (record_date),
  KEY idx_mort_farm (farm_id, record_date),
  KEY idx_mort_batch (batch_id, record_date),
  CONSTRAINT fk_mort_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_mort_shed FOREIGN KEY (shed_id) REFERENCES sheds(id),
  CONSTRAINT fk_mort_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_mort_reason FOREIGN KEY (reason_id) REFERENCES mortality_reasons(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Feed
CREATE TABLE units (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  symbol VARCHAR(20) NOT NULL UNIQUE,
  kg_factor DECIMAL(12,4) NOT NULL COMMENT 'Kilograms in one unit',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT chk_unit_factor CHECK (kg_factor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feed_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feed_products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  supplier_id INT UNSIGNED NULL,
  default_unit_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  sku VARCHAR(40) NULL UNIQUE,
  low_stock_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
  sale_price_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_fp_cat FOREIGN KEY (category_id) REFERENCES feed_categories(id),
  CONSTRAINT fk_fp_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_fp_unit FOREIGN KEY (default_unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feed_inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farm_id INT UNSIGNED NOT NULL,
  feed_product_id INT UNSIGNED NOT NULL,
  quantity_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
  avg_cost_kg DECIMAL(14,4) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_feed_inv (farm_id, feed_product_id),
  CONSTRAINT fk_finv_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_finv_prod FOREIGN KEY (feed_product_id) REFERENCES feed_products(id),
  CONSTRAINT chk_finv_nonneg CHECK (quantity_kg >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feed_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farm_id INT UNSIGNED NOT NULL,
  feed_product_id INT UNSIGNED NOT NULL,
  txn_date DATE NOT NULL,
  txn_type ENUM('purchase','consumption','wastage','sale','adjustment') NOT NULL,
  quantity_kg DECIMAL(14,3) NOT NULL COMMENT 'Signed',
  balance_after_kg DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  reference_type VARCHAR(40) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  source_key VARCHAR(100) NOT NULL UNIQUE,
  reversal_of BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ftx_farm (farm_id, feed_product_id, txn_date),
  KEY idx_ftx_ref (reference_type, reference_id),
  CONSTRAINT fk_ftx_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_ftx_prod FOREIGN KEY (feed_product_id) REFERENCES feed_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feed_consumption (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_date DATE NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  shed_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  feed_product_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  quantity DECIMAL(14,3) NOT NULL COMMENT 'In the entered unit',
  quantity_kg DECIMAL(14,3) NOT NULL,
  wastage_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  total_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fc_farm (farm_id, record_date),
  KEY idx_fc_batch (batch_id, record_date),
  KEY idx_fc_shed (shed_id, record_date),
  CONSTRAINT fk_fc_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_fc_shed FOREIGN KEY (shed_id) REFERENCES sheds(id),
  CONSTRAINT fk_fc_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_fc_prod FOREIGN KEY (feed_product_id) REFERENCES feed_products(id),
  CONSTRAINT fk_fc_unit FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Eggs
CREATE TABLE egg_production (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_date DATE NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  shed_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  total_eggs INT UNSIGNED NOT NULL,
  good_eggs INT UNSIGNED NOT NULL,
  damaged_eggs INT UNSIGNED NOT NULL DEFAULT 0,
  broken_eggs INT UNSIGNED NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_egg_farm (farm_id, record_date),
  KEY idx_egg_batch (batch_id, record_date),
  KEY idx_egg_shed (shed_id, record_date),
  CONSTRAINT fk_egg_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_egg_shed FOREIGN KEY (shed_id) REFERENCES sheds(id),
  CONSTRAINT fk_egg_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT chk_egg_total CHECK (total_eggs = good_eggs + damaged_eggs + broken_eggs)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE egg_inventory (
  farm_id INT UNSIGNED PRIMARY KEY,
  quantity INT NOT NULL DEFAULT 0 COMMENT 'Saleable good eggs',
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_einv_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT chk_einv_nonneg CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE egg_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farm_id INT UNSIGNED NOT NULL,
  txn_date DATE NOT NULL,
  txn_type ENUM('production','sale','damage_adjustment','adjustment') NOT NULL,
  quantity INT NOT NULL COMMENT 'Signed',
  balance_after INT NOT NULL,
  reference_type VARCHAR(40) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  source_key VARCHAR(100) NOT NULL UNIQUE,
  reversal_of BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_etx_farm (farm_id, txn_date),
  KEY idx_etx_ref (reference_type, reference_id),
  CONSTRAINT fk_etx_farm FOREIGN KEY (farm_id) REFERENCES farms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE egg_adjustments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farm_id INT UNSIGNED NOT NULL,
  adjustment_date DATE NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eadj_farm FOREIGN KEY (farm_id) REFERENCES farms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Medicine & vaccination
CREATE TABLE medicine_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medicines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  supplier_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'dose',
  low_stock_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_med_cat FOREIGN KEY (category_id) REFERENCES medicine_categories(id),
  CONSTRAINT fk_med_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medicine_lots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farm_id INT UNSIGNED NOT NULL,
  medicine_id INT UNSIGNED NOT NULL,
  lot_no VARCHAR(60) NULL,
  expiry_date DATE NULL,
  quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_lot_med (farm_id, medicine_id, expiry_date),
  KEY idx_lot_expiry (expiry_date),
  CONSTRAINT fk_lot_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_lot_med FOREIGN KEY (medicine_id) REFERENCES medicines(id),
  CONSTRAINT chk_lot_nonneg CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medicine_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lot_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  medicine_id INT UNSIGNED NOT NULL,
  txn_date DATE NOT NULL,
  txn_type ENUM('purchase','usage','adjustment') NOT NULL,
  quantity DECIMAL(14,3) NOT NULL COMMENT 'Signed',
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  reference_type VARCHAR(40) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  source_key VARCHAR(100) NOT NULL UNIQUE,
  reversal_of BIGINT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mtx_farm (farm_id, medicine_id, txn_date),
  KEY idx_mtx_ref (reference_type, reference_id),
  CONSTRAINT fk_mtx_lot FOREIGN KEY (lot_id) REFERENCES medicine_lots(id),
  CONSTRAINT fk_mtx_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_mtx_med FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE medicine_usage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_date DATE NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  medicine_id INT UNSIGNED NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  total_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  veterinarian VARCHAR(120) NULL,
  diagnosis VARCHAR(255) NULL,
  notes VARCHAR(255) NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mu_batch (batch_id, record_date),
  KEY idx_mu_farm (farm_id, record_date),
  CONSTRAINT fk_mu_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_mu_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_mu_med FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vaccinations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  disease VARCHAR(160) NULL,
  medicine_id INT UNSIGNED NULL,
  batch_type ENUM('all','broiler','layer','breeder') NOT NULL DEFAULT 'all',
  age_days INT UNSIGNED NOT NULL DEFAULT 0,
  repeat_days INT UNSIGNED NULL,
  route VARCHAR(60) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_vac_med FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vaccination_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  vaccination_id INT UNSIGNED NOT NULL,
  scheduled_date DATE NOT NULL,
  given_date DATE NULL,
  next_date DATE NULL,
  doses DECIMAL(14,3) NULL,
  veterinarian VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  status ENUM('scheduled','given','missed','cancelled') NOT NULL DEFAULT 'scheduled',
  usage_id INT UNSIGNED NULL COMMENT 'Stock consumed when given',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_vr_sched (status, scheduled_date),
  KEY idx_vr_batch (batch_id),
  CONSTRAINT fk_vr_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_vr_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_vr_vac FOREIGN KEY (vaccination_id) REFERENCES vaccinations(id),
  CONSTRAINT fk_vr_usage FOREIGN KEY (usage_id) REFERENCES medicine_usage(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Website catalogue (also sold through Sales)
CREATE TABLE product_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  slug VARCHAR(140) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  summary VARCHAR(255) NULL,
  description TEXT NULL,
  image VARCHAR(255) NULL,
  price DECIMAL(12,2) NULL,
  price_unit VARCHAR(40) NULL,
  availability ENUM('in_stock','limited','out_of_stock','on_order') NOT NULL DEFAULT 'in_stock',
  track_stock TINYINT(1) NOT NULL DEFAULT 0,
  stock_quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
  avg_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_product_pub (is_published, sort_order),
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES product_categories(id),
  CONSTRAINT chk_prod_stock CHECK (stock_quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  txn_date DATE NOT NULL,
  txn_type ENUM('purchase','sale','adjustment') NOT NULL,
  quantity DECIMAL(14,3) NOT NULL COMMENT 'Signed',
  balance_after DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  reference_type VARCHAR(40) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  source_key VARCHAR(100) NOT NULL UNIQUE,
  reversal_of BIGINT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ptx_ref (reference_type, reference_id),
  CONSTRAINT fk_ptx_prod FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Purchases
CREATE TABLE purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_no VARCHAR(30) NOT NULL UNIQUE,
  supplier_invoice_no VARCHAR(60) NULL,
  supplier_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  purchase_date DATE NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'draft' COMMENT 'draft is an open purchase order',
  payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  notes VARCHAR(500) NULL,
  confirmed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  cancel_reason VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pur_date (purchase_date),
  KEY idx_pur_supplier (supplier_id, purchase_date),
  KEY idx_pur_farm (farm_id, purchase_date),
  KEY idx_pur_status (status, payment_status),
  CONSTRAINT fk_pur_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_pur_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT chk_pur_paid CHECK (paid_amount >= 0 AND paid_amount <= total_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT UNSIGNED NOT NULL,
  item_type ENUM('chick','feed','medicine','product','equipment','other') NOT NULL,
  batch_id INT UNSIGNED NULL,
  feed_product_id INT UNSIGNED NULL,
  unit_id INT UNSIGNED NULL,
  medicine_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NULL,
  lot_no VARCHAR(60) NULL,
  expiry_date DATE NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit_price DECIMAL(14,4) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  KEY idx_pi_purchase (purchase_id),
  CONSTRAINT fk_pi_pur FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_pi_feed FOREIGN KEY (feed_product_id) REFERENCES feed_products(id),
  CONSTRAINT fk_pi_unit FOREIGN KEY (unit_id) REFERENCES units(id),
  CONSTRAINT fk_pi_med FOREIGN KEY (medicine_id) REFERENCES medicines(id),
  CONSTRAINT fk_pi_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT chk_pi_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Sales
CREATE TABLE sales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NOT NULL,
  sale_date DATE NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'draft',
  payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  notes VARCHAR(500) NULL,
  confirmed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  cancel_reason VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sale_date (sale_date),
  KEY idx_sale_customer (customer_id, sale_date),
  KEY idx_sale_farm (farm_id, sale_date),
  KEY idx_sale_status (status, payment_status),
  CONSTRAINT fk_sale_cust FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_sale_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT chk_sale_paid CHECK (paid_amount >= 0 AND paid_amount <= grand_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  item_type ENUM('bird','egg','feed','product') NOT NULL,
  batch_id INT UNSIGNED NULL COMMENT 'Required for birds; optional attribution for eggs',
  shed_id INT UNSIGNED NULL,
  feed_product_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit_price DECIMAL(14,4) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  KEY idx_si_sale (sale_id),
  KEY idx_si_batch (batch_id),
  CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_si_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_si_shed FOREIGN KEY (shed_id) REFERENCES sheds(id),
  CONSTRAINT fk_si_feed FOREIGN KEY (feed_product_id) REFERENCES feed_products(id),
  CONSTRAINT fk_si_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT chk_si_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Finance
CREATE TABLE accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  account_type ENUM('cash','bank') NOT NULL,
  account_number VARCHAR(60) NULL,
  opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expense_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  expense_type ENUM('operational','capital') NOT NULL DEFAULT 'operational',
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(30) NOT NULL UNIQUE,
  farm_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(160) NULL,
  address VARCHAR(255) NULL,
  designation VARCHAR(120) NULL,
  salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  joining_date DATE NULL,
  status ENUM('active','on_leave','resigned','terminated') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_emp_farm (farm_id),
  CONSTRAINT fk_emp_farm FOREIGN KEY (farm_id) REFERENCES farms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  status ENUM('present','absent','leave','half_day') NOT NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attendance (employee_id, attendance_date),
  KEY idx_att_date (attendance_date),
  CONSTRAINT fk_att_emp FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NULL,
  batch_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  receipt VARCHAR(255) NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  cancel_reason VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_exp_date (expense_date),
  KEY idx_exp_farm (farm_id, expense_date),
  KEY idx_exp_batch (batch_id),
  KEY idx_exp_cat (category_id, expense_date),
  CONSTRAINT fk_exp_cat FOREIGN KEY (category_id) REFERENCES expense_categories(id),
  CONSTRAINT fk_exp_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_exp_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT chk_exp_paid CHECK (paid_amount >= 0 AND paid_amount <= amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE salaries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL COMMENT 'YYYY-MM',
  basic_amount DECIMAL(12,2) NOT NULL,
  allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
  deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_amount DECIMAL(12,2) NOT NULL,
  pay_date DATE NOT NULL,
  expense_id INT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  active_key VARCHAR(30) NULL UNIQUE COMMENT 'employee:period while posted; NULL once cancelled, so a period can be re-paid',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sal_emp (employee_id, period),
  CONSTRAINT fk_sal_emp FOREIGN KEY (employee_id) REFERENCES employees(id),
  CONSTRAINT fk_sal_exp FOREIGN KEY (expense_id) REFERENCES expenses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_no VARCHAR(30) NOT NULL UNIQUE,
  direction ENUM('in','out') NOT NULL,
  party_type ENUM('customer','supplier','other') NOT NULL,
  customer_id INT UNSIGNED NULL,
  supplier_id INT UNSIGNED NULL,
  sale_id INT UNSIGNED NULL,
  purchase_id INT UNSIGNED NULL,
  expense_id INT UNSIGNED NULL,
  account_id INT UNSIGNED NOT NULL,
  farm_id INT UNSIGNED NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  method ENUM('cash','bank_transfer','cheque','card','mobile_wallet') NOT NULL DEFAULT 'cash',
  reference_no VARCHAR(80) NULL,
  notes VARCHAR(255) NULL,
  status ENUM('posted','voided') NOT NULL DEFAULT 'posted',
  voided_at DATETIME NULL,
  void_reason VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pay_date (payment_date),
  KEY idx_pay_customer (customer_id, payment_date),
  KEY idx_pay_supplier (supplier_id, payment_date),
  KEY idx_pay_sale (sale_id),
  KEY idx_pay_purchase (purchase_id),
  KEY idx_pay_expense (expense_id),
  CONSTRAINT fk_pay_cust FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_pay_sup FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_pay_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
  CONSTRAINT fk_pay_pur FOREIGN KEY (purchase_id) REFERENCES purchases(id),
  CONSTRAINT fk_pay_exp FOREIGN KEY (expense_id) REFERENCES expenses(id),
  CONSTRAINT fk_pay_acc FOREIGN KEY (account_id) REFERENCES accounts(id),
  CONSTRAINT fk_pay_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT chk_pay_amt CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per financial effect. Reports sum these rows; nothing is typed in by hand.
--   revenue / tax_collected         earned on a confirmed sale (accrual)
--   cogs                            cost of birds, feed, medicine or products leaving stock
--   opex                            operating expense (P&L)
--   capex                           capital spend (not P&L)
--   inventory_purchase              value moved into stock (not P&L until consumed or sold)
--   receipt / disbursement / transfer_in / transfer_out   money moving through an account
-- A reversal is a row of the same entry_type with a negative amount and reversal_of set.
CREATE TABLE financial_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  txn_date DATE NOT NULL,
  entry_type ENUM('revenue','tax_collected','cogs','opex','capex','inventory_purchase','receipt','disbursement','transfer_in','transfer_out') NOT NULL,
  sub_type VARCHAR(40) NULL,
  amount DECIMAL(14,2) NOT NULL,
  account_id INT UNSIGNED NULL,
  farm_id INT UNSIGNED NULL,
  batch_id INT UNSIGNED NULL,
  expense_category_id INT UNSIGNED NULL,
  reference_type VARCHAR(40) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  source_key VARCHAR(100) NOT NULL UNIQUE,
  reversal_of BIGINT UNSIGNED NULL,
  description VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ft_date (txn_date, entry_type),
  KEY idx_ft_farm (farm_id, txn_date),
  KEY idx_ft_batch (batch_id, entry_type),
  KEY idx_ft_acc (account_id, txn_date),
  KEY idx_ft_ref (reference_type, reference_id),
  CONSTRAINT fk_ft_acc FOREIGN KEY (account_id) REFERENCES accounts(id),
  CONSTRAINT fk_ft_farm FOREIGN KEY (farm_id) REFERENCES farms(id),
  CONSTRAINT fk_ft_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_ft_cat FOREIGN KEY (expense_category_id) REFERENCES expense_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_transfers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_account_id INT UNSIGNED NOT NULL,
  to_account_id INT UNSIGNED NOT NULL,
  transfer_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  notes VARCHAR(255) NULL,
  status ENUM('posted','cancelled') NOT NULL DEFAULT 'posted',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_at_from FOREIGN KEY (from_account_id) REFERENCES accounts(id),
  CONSTRAINT fk_at_to FOREIGN KEY (to_account_id) REFERENCES accounts(id),
  CONSTRAINT chk_at_amt CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Website CMS
CREATE TABLE content_blocks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  block_type ENUM('service','facility','why','process') NOT NULL,
  title VARCHAR(160) NOT NULL,
  summary VARCHAR(500) NULL,
  icon VARCHAR(60) NULL,
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_block_type (block_type, is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE testimonials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(120) NOT NULL,
  customer_title VARCHAR(120) NULL,
  image VARCHAR(255) NULL,
  feedback TEXT NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gallery (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  category ENUM('farm','sheds','chickens','eggs','activities') NOT NULL DEFAULT 'farm',
  media_type ENUM('image','video') NOT NULL DEFAULT 'image',
  image VARCHAR(255) NULL,
  video_url VARCHAR(255) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_gallery_pub (is_published, category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  category ENUM('farming_tips','bird_health','vaccination','feed_nutrition','industry') NOT NULL DEFAULT 'farming_tips',
  excerpt VARCHAR(500) NULL,
  body MEDIUMTEXT NOT NULL,
  image VARCHAR(255) NULL,
  author_id INT UNSIGNED NULL,
  meta_title VARCHAR(200) NULL,
  meta_description VARCHAR(320) NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_blog_pub (status, published_at),
  CONSTRAINT fk_blog_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_type ENUM('contact','quote','inquiry') NOT NULL DEFAULT 'contact',
  product_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NULL,
  phone VARCHAR(40) NULL,
  subject VARCHAR(200) NULL,
  message TEXT NOT NULL,
  ip_address VARCHAR(45) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_msg_read (is_read, created_at),
  KEY idx_msg_ip (ip_address, created_at),
  CONSTRAINT fk_msg_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE website_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
SET FOREIGN_KEY_CHECKS = 0;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `account_transfers` WRITE;
UNLOCK TABLES;

LOCK TABLES `accounts` WRITE;
UNLOCK TABLES;

LOCK TABLES `activity_logs` WRITE;
UNLOCK TABLES;

LOCK TABLES `attendance` WRITE;
UNLOCK TABLES;

LOCK TABLES `batches` WRITE;
UNLOCK TABLES;

LOCK TABLES `bird_adjustments` WRITE;
UNLOCK TABLES;

LOCK TABLES `bird_inventory` WRITE;
UNLOCK TABLES;

LOCK TABLES `bird_transactions` WRITE;
UNLOCK TABLES;

LOCK TABLES `bird_transfers` WRITE;
UNLOCK TABLES;

LOCK TABLES `blog_posts` WRITE;
UNLOCK TABLES;

LOCK TABLES `contact_messages` WRITE;
UNLOCK TABLES;

LOCK TABLES `content_blocks` WRITE;
INSERT INTO `content_blocks` (`id`, `block_type`, `title`, `summary`, `icon`, `image`, `sort_order`, `is_published`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'service','Poultry farming','Broilers and layers raised to contract or for the open market.','bi-house-heart',NULL,0,1,'2026-11-01 16:57:23',NULL,NULL),(2,'service','Chick supply','Uniform day-old chicks, booked around your placement dates.','bi-egg-fried',NULL,1,1,'2026-11-01 16:57:23',NULL,NULL),(3,'service','Egg production','Daily collected, graded eggs by the tray, with regular delivery.','bi-egg',NULL,2,1,'2026-11-01 16:57:23',NULL,NULL),(4,'service','Poultry feed supply','Starter, grower, finisher and layer feed in 50 kg bags.','bi-basket',NULL,3,1,'2026-11-01 16:57:23',NULL,NULL),(5,'service','Farm consultancy','Practical advice on sheds, feeding and flock health from people who farm every day.','bi-chat-dots',NULL,4,1,'2026-11-01 16:57:23',NULL,NULL),(6,'service','Poultry farm management','Day-to-day management and record keeping for owners who want it run properly.','bi-clipboard-check',NULL,5,1,'2026-11-01 16:57:23',NULL,NULL),(7,'facility','Modern poultry sheds','Well-spaced, easy-to-clean houses built for bird comfort.','bi-house-door','images/site/hero-shed.jpg',0,1,'2026-11-01 16:57:23',NULL,NULL),(8,'facility','Automatic feeding systems','Measured feed lines give every bird fair access and limit waste.','bi-gear-wide-connected','images/site/layer-house.jpg',1,1,'2026-11-01 16:57:23',NULL,NULL),(9,'facility','Clean water systems','Nipple drinkers on filtered lines, checked every day.','bi-droplet','images/site/broiler-flock.jpg',2,1,'2026-11-01 16:57:23',NULL,NULL),(10,'facility','Ventilation systems','Fans and inlets keep air fresh and litter dry.','bi-fan','images/site/hen-cage.jpg',3,1,'2026-11-01 16:57:23',NULL,NULL),(11,'facility','Temperature control','Brooding heat for chicks and cooling for the hot months.','bi-thermometer-half','images/site/chicks-brooder.jpg',4,1,'2026-11-01 16:57:23',NULL,NULL),(12,'facility','Biosecurity facilities','Controlled entry, footbaths and disinfection for people and vehicles.','bi-shield-check','images/site/hens-fence.jpg',5,1,'2026-11-01 16:57:23',NULL,NULL),(13,'facility','Veterinary care','A planned vaccination programme and regular visits from a veterinarian.','bi-heart-pulse','images/site/hen-closeup.jpg',6,1,'2026-11-01 16:57:23',NULL,NULL),(14,'why','Healthy poultry','Every batch follows a vaccination programme and daily health checks.',NULL,NULL,0,1,'2026-11-01 16:57:23',NULL,NULL),(15,'why','Quality feed and nutrition','Feed matched to the age and type of the birds.',NULL,NULL,1,1,'2026-11-01 16:57:23',NULL,NULL),(16,'why','Hygienic farming','Clean sheds, clean water and strict entry rules.',NULL,NULL,2,1,'2026-11-01 16:57:23',NULL,NULL),(17,'why','Experienced management','A team that has raised flocks through every season.',NULL,NULL,3,1,'2026-11-01 16:57:23',NULL,NULL),(18,'why','Reliable supply','Planned placements mean we can tell you what is available and when.',NULL,NULL,4,1,'2026-11-01 16:57:23',NULL,NULL),(19,'why','Customer satisfaction','Straight answers, fair weights and deliveries that turn up.',NULL,NULL,5,1,'2026-11-01 16:57:23',NULL,NULL),(20,'process','Quality chick selection','Chicks from selected breeders, checked on arrival.',NULL,NULL,0,1,'2026-11-01 16:57:23',NULL,NULL),(21,'process','Proper feeding','Measured rations for each stage of growth.',NULL,NULL,1,1,'2026-11-01 16:57:23',NULL,NULL),(22,'process','Health monitoring','Daily checks and mortality records.',NULL,NULL,2,1,'2026-11-01 16:57:23',NULL,NULL),(23,'process','Vaccination and care','A scheduled programme for every batch.',NULL,NULL,3,1,'2026-11-01 16:57:23',NULL,NULL),(24,'process','Quality production','Birds and eggs checked before sale.',NULL,NULL,4,1,'2026-11-01 16:57:23',NULL,NULL),(25,'process','Product delivery','On time, to your door or dock.',NULL,NULL,5,1,'2026-11-01 16:57:23',NULL,NULL);
UNLOCK TABLES;

LOCK TABLES `customers` WRITE;
UNLOCK TABLES;

LOCK TABLES `egg_adjustments` WRITE;
UNLOCK TABLES;

LOCK TABLES `egg_inventory` WRITE;
UNLOCK TABLES;

LOCK TABLES `egg_production` WRITE;
UNLOCK TABLES;

LOCK TABLES `egg_transactions` WRITE;
UNLOCK TABLES;

LOCK TABLES `employees` WRITE;
UNLOCK TABLES;

LOCK TABLES `expense_categories` WRITE;
INSERT INTO `expense_categories` (`id`, `name`, `expense_type`, `description`, `created_at`, `deleted_at`) VALUES (1,'Feed','operational','Feed costs not held in stock, such as delivery. Stocked feed belongs in Purchases.','2026-11-01 16:57:23',NULL),(2,'Medicine','operational','Medicine costs not held in stock. Stocked medicine belongs in Purchases.','2026-11-01 16:57:23',NULL),(3,'Electricity','operational',NULL,'2026-11-01 16:57:23',NULL),(4,'Water','operational',NULL,'2026-11-01 16:57:23',NULL),(5,'Employee Salaries','operational','Filled automatically when a salary is paid.','2026-11-01 16:57:23',NULL),(6,'Transportation','operational',NULL,'2026-11-01 16:57:23',NULL),(7,'Maintenance','operational',NULL,'2026-11-01 16:57:23',NULL),(8,'Equipment','capital','Long-lasting equipment. Recorded as capital, not in profit.','2026-11-01 16:57:23',NULL),(9,'Veterinary','operational','Vet visits and services.','2026-11-01 16:57:23',NULL),(10,'Other Expenses','operational',NULL,'2026-11-01 16:57:23',NULL);
UNLOCK TABLES;

LOCK TABLES `expenses` WRITE;
UNLOCK TABLES;

LOCK TABLES `farms` WRITE;
UNLOCK TABLES;

LOCK TABLES `feed_categories` WRITE;
UNLOCK TABLES;

LOCK TABLES `feed_consumption` WRITE;
UNLOCK TABLES;

LOCK TABLES `feed_inventory` WRITE;
UNLOCK TABLES;

LOCK TABLES `feed_products` WRITE;
UNLOCK TABLES;

LOCK TABLES `feed_transactions` WRITE;
UNLOCK TABLES;

LOCK TABLES `financial_transactions` WRITE;
UNLOCK TABLES;

LOCK TABLES `gallery` WRITE;
INSERT INTO `gallery` (`id`, `title`, `category`, `media_type`, `image`, `video_url`, `is_published`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Inside a broiler shed','sheds','image','images/site/hero-shed.jpg',NULL,1,0,'2026-11-01 16:57:23',NULL,NULL),(2,'Layer house with feed lines','sheds','image','images/site/layer-house.jpg',NULL,1,1,'2026-11-01 16:57:23',NULL,NULL),(3,'Hens out on grass','chickens','image','images/site/hens-field.jpg',NULL,1,2,'2026-11-01 16:57:23',NULL,NULL),(4,'A flock in the shed','chickens','image','images/site/broiler-flock.jpg',NULL,1,3,'2026-11-01 16:57:23',NULL,NULL),(5,'Eggs ready for packing','eggs','image','images/site/eggs-tray.jpg',NULL,1,4,'2026-11-01 16:57:23',NULL,NULL),(6,'Chicks under the brooder','activities','image','images/site/chicks-brooder.jpg',NULL,1,5,'2026-11-01 16:57:23',NULL,NULL),(7,'Chicks arriving','activities','image','images/site/chicks-box.jpg',NULL,1,6,'2026-11-01 16:57:23',NULL,NULL),(8,'Hens at the fence','farm','image','images/site/hens-fence.jpg',NULL,1,7,'2026-11-01 16:57:23',NULL,NULL),(9,'Fresh eggs in a nest','eggs','image','images/site/eggs-nest.jpg',NULL,1,8,'2026-11-01 16:57:23',NULL,NULL),(10,'A young chick','chickens','image','images/site/chick-grass.jpg',NULL,1,9,'2026-11-01 16:57:23',NULL,NULL),(11,'Mixed eggs in a tray','eggs','image','images/site/eggs-mixed.jpg',NULL,1,10,'2026-11-01 16:57:23',NULL,NULL),(12,'Out in the yard','farm','image','images/site/hen-yard.jpg',NULL,1,11,'2026-11-01 16:57:23',NULL,NULL);
UNLOCK TABLES;

LOCK TABLES `login_attempts` WRITE;
UNLOCK TABLES;

LOCK TABLES `medicine_categories` WRITE;
UNLOCK TABLES;

LOCK TABLES `medicine_lots` WRITE;
UNLOCK TABLES;

LOCK TABLES `medicine_transactions` WRITE;
UNLOCK TABLES;

LOCK TABLES `medicine_usage` WRITE;
UNLOCK TABLES;

LOCK TABLES `medicines` WRITE;
UNLOCK TABLES;

LOCK TABLES `mortality_reasons` WRITE;
INSERT INTO `mortality_reasons` (`id`, `name`, `created_at`, `deleted_at`) VALUES (1,'Disease','2026-11-01 16:57:23',NULL),(2,'Heat stress','2026-11-01 16:57:23',NULL),(3,'Injury','2026-11-01 16:57:23',NULL),(4,'Predator','2026-11-01 16:57:23',NULL),(5,'Culled','2026-11-01 16:57:23',NULL),(6,'Unknown','2026-11-01 16:57:23',NULL);
UNLOCK TABLES;

LOCK TABLES `mortality_records` WRITE;
UNLOCK TABLES;

LOCK TABLES `newsletter_subscribers` WRITE;
UNLOCK TABLES;

LOCK TABLES `payments` WRITE;
UNLOCK TABLES;

LOCK TABLES `permissions` WRITE;
INSERT INTO `permissions` (`id`, `slug`, `module`, `label`, `created_at`) VALUES (1,'dashboard.view','dashboard','View dashboard','2026-11-01 16:57:22'),(2,'farms.view','farms','View farms','2026-11-01 16:57:22'),(3,'farms.manage','farms','Manage farms','2026-11-01 16:57:22'),(4,'sheds.view','sheds','View sheds','2026-11-01 16:57:22'),(5,'sheds.manage','sheds','Manage sheds','2026-11-01 16:57:22'),(6,'batches.view','batches','View batches','2026-11-01 16:57:22'),(7,'batches.manage','batches','Manage batches','2026-11-01 16:57:22'),(8,'birds.view','birds','View bird inventory','2026-11-01 16:57:22'),(9,'birds.manage','birds','Manage bird inventory','2026-11-01 16:57:22'),(10,'feed.view','feed','View feed stock and products','2026-11-01 16:57:22'),(11,'feed.manage','feed','Manage feed stock and products','2026-11-01 16:57:22'),(12,'feed_consumption.view','feed_consumption','View feed consumption','2026-11-01 16:57:22'),(13,'feed_consumption.manage','feed_consumption','Manage feed consumption','2026-11-01 16:57:22'),(14,'eggs.view','eggs','View egg production','2026-11-01 16:57:22'),(15,'eggs.manage','eggs','Manage egg production','2026-11-01 16:57:22'),(16,'mortality.view','mortality','View mortality','2026-11-01 16:57:22'),(17,'mortality.manage','mortality','Manage mortality','2026-11-01 16:57:22'),(18,'medicines.view','medicines','View medicines and treatments','2026-11-01 16:57:22'),(19,'medicines.manage','medicines','Manage medicines and treatments','2026-11-01 16:57:22'),(20,'vaccinations.view','vaccinations','View vaccinations','2026-11-01 16:57:22'),(21,'vaccinations.manage','vaccinations','Manage vaccinations','2026-11-01 16:57:22'),(22,'purchases.view','purchases','View purchases','2026-11-01 16:57:22'),(23,'purchases.manage','purchases','Manage purchases','2026-11-01 16:57:22'),(24,'sales.view','sales','View sales','2026-11-01 16:57:22'),(25,'sales.manage','sales','Manage sales','2026-11-01 16:57:22'),(26,'expenses.view','expenses','View expenses','2026-11-01 16:57:22'),(27,'expenses.manage','expenses','Manage expenses','2026-11-01 16:57:22'),(28,'customers.view','customers','View customers','2026-11-01 16:57:22'),(29,'customers.manage','customers','Manage customers','2026-11-01 16:57:22'),(30,'suppliers.view','suppliers','View suppliers','2026-11-01 16:57:22'),(31,'suppliers.manage','suppliers','Manage suppliers','2026-11-01 16:57:22'),(32,'employees.view','employees','View employees and attendance','2026-11-01 16:57:22'),(33,'employees.manage','employees','Manage employees and attendance','2026-11-01 16:57:22'),(34,'salaries.view','salaries','View salaries','2026-11-01 16:57:22'),(35,'salaries.manage','salaries','Manage salaries','2026-11-01 16:57:22'),(36,'payments.view','payments','View payments','2026-11-01 16:57:22'),(37,'payments.manage','payments','Manage payments','2026-11-01 16:57:22'),(38,'accounts.view','accounts','View cash and bank accounts','2026-11-01 16:57:22'),(39,'accounts.manage','accounts','Manage cash and bank accounts','2026-11-01 16:57:22'),(40,'finance.view','finance','View financial figures','2026-11-01 16:57:22'),(41,'reports.view','reports','View operational reports','2026-11-01 16:57:22'),(42,'reports_financial.view','reports_financial','View financial reports','2026-11-01 16:57:22'),(43,'cms.view','cms','View website cms','2026-11-01 16:57:22'),(44,'cms.manage','cms','Manage website cms','2026-11-01 16:57:22'),(45,'users.view','users','View users','2026-11-01 16:57:22'),(46,'users.manage','users','Manage users','2026-11-01 16:57:22'),(47,'roles.view','roles','View roles and permissions','2026-11-01 16:57:22'),(48,'roles.manage','roles','Manage roles and permissions','2026-11-01 16:57:22'),(49,'settings.view','settings','View system settings','2026-11-01 16:57:22'),(50,'settings.manage','settings','Manage system settings','2026-11-01 16:57:22'),(51,'activity_logs.view','activity_logs','View activity log','2026-11-01 16:57:22');
UNLOCK TABLES;

LOCK TABLES `product_categories` WRITE;
INSERT INTO `product_categories` (`id`, `name`, `slug`, `sort_order`, `created_at`, `deleted_at`) VALUES (1,'Live Birds','live-birds',0,'2026-11-01 16:57:23',NULL),(2,'Chicks','chicks',1,'2026-11-01 16:57:23',NULL),(3,'Eggs','eggs',2,'2026-11-01 16:57:23',NULL),(4,'Feed','feed',3,'2026-11-01 16:57:23',NULL),(5,'Other Products','other-products',4,'2026-11-01 16:57:23',NULL);
UNLOCK TABLES;

LOCK TABLES `product_transactions` WRITE;
UNLOCK TABLES;

LOCK TABLES `products` WRITE;
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `summary`, `description`, `image`, `price`, `price_unit`, `availability`, `track_stock`, `stock_quantity`, `avg_cost`, `is_published`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Broiler Chickens','broiler-chickens','Market-ready broilers, weighed and delivered to order.','Our broilers reach market weight in about six weeks on a measured starter and finisher programme. Each batch has a full record of feed, vaccinations and health checks.\n\nAvailable in full or part loads for shops, restaurants and wholesalers.','images/site/broiler-flock.jpg',480.00,'bird','in_stock',0,0.000,0.0000,1,0,'2026-11-01 16:57:23',NULL,NULL),(2,1,'Layer Chickens','layer-chickens','Point-of-lay pullets, vaccinated and ready for your layer house.','Well-grown pullets raised on a complete vaccination programme and sold close to point of lay.\n\nWe can advise on housing and feeding for the first weeks after delivery.','images/site/hens-field.jpg',1450.00,'bird','limited',0,0.000,0.0000,1,1,'2026-11-01 16:57:23',NULL,NULL),(3,2,'Day-Old Chicks','day-old-chicks','Healthy, uniform chicks from selected breeders.','Broiler and layer chicks selected for vigour and uniformity, delivered in ventilated boxes.\n\nBook ahead: chick placements are planned around your shed availability.','images/site/chicks-box.jpg',85.00,'chick','on_order',0,0.000,0.0000,1,2,'2026-11-01 16:57:23',NULL,NULL),(4,3,'Fresh Eggs','fresh-eggs','Graded table eggs collected every day from our layer houses.','Eggs are collected daily, checked, and only sound eggs are packed. Available by the tray for shops, bakeries and caterers.\n\nRegular weekly deliveries can be arranged.','images/site/eggs-tray.jpg',780.00,'tray of 30','in_stock',0,0.000,0.0000,1,3,'2026-11-01 16:57:23',NULL,NULL),(5,4,'Poultry Feed','poultry-feed','Starter, grower, finisher and layer feed in 50 kg bags.','The same feed programme we use on our own farms, available in 50 kg bags.\n\nAsk us which feed fits the age and type of your birds.','images/site/layer-house.jpg',3100.00,'50 kg bag','in_stock',0,0.000,0.0000,1,4,'2026-11-01 16:57:23',NULL,NULL),(6,5,'Poultry Manure','poultry-manure','Well-rotted manure for fields, orchards and nurseries.','Composted litter from our sheds, sold by the trolley load to growers nearby. Collection from the farm or delivery by arrangement.','images/site/hens-fence.jpg',NULL,NULL,'limited',0,0.000,0.0000,1,5,'2026-11-01 16:57:23',NULL,NULL);
UNLOCK TABLES;

LOCK TABLES `purchase_items` WRITE;
UNLOCK TABLES;

LOCK TABLES `purchases` WRITE;
UNLOCK TABLES;

LOCK TABLES `role_permissions` WRITE;
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,1),(2,2),(2,4),(2,5),(2,6),(2,7),(2,8),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),(2,17),(2,18),(2,19),(2,20),(2,21),(2,32),(2,41),(3,1),(3,2),(3,6),(3,8),(3,10),(3,14),(3,18),(3,22),(3,23),(3,24),(3,25),(3,26),(3,27),(3,28),(3,29),(3,30),(3,31),(3,32),(3,34),(3,35),(3,36),(3,37),(3,38),(3,39),(3,40),(3,41),(3,42),(4,1),(4,12),(4,13),(4,14),(4,15),(4,16),(4,17),(5,1),(5,2),(5,4),(5,6),(5,8),(5,10),(5,12),(5,14),(5,16),(5,18),(5,20),(5,22),(5,24),(5,26),(5,28),(5,30),(5,32),(5,34),(5,36),(5,38),(5,40),(5,41),(5,42);
UNLOCK TABLES;

LOCK TABLES `roles` WRITE;
INSERT INTO `roles` (`id`, `name`, `slug`, `all_farms`, `description`, `created_at`, `updated_at`) VALUES (1,'Super Admin','super_admin',1,'Full access to every farm and module.','2026-11-01 16:57:22',NULL),(2,'Farm Manager','farm_manager',0,'Runs the assigned farms: sheds, batches, stock, production and mortality.','2026-11-01 16:57:22',NULL),(3,'Accountant','accountant',1,'Purchases, sales, expenses, payments and financial reports.','2026-11-01 16:57:22',NULL),(4,'Farm Staff','farm_staff',0,'Daily entries at the assigned farms: feed, eggs and mortality.','2026-11-01 16:57:22',NULL),(5,'Viewer','viewer',1,'Read-only access.','2026-11-01 16:57:22',NULL);
UNLOCK TABLES;

LOCK TABLES `salaries` WRITE;
UNLOCK TABLES;

LOCK TABLES `sale_items` WRITE;
UNLOCK TABLES;

LOCK TABLES `sales` WRITE;
UNLOCK TABLES;

LOCK TABLES `sheds` WRITE;
UNLOCK TABLES;

LOCK TABLES `suppliers` WRITE;
UNLOCK TABLES;

LOCK TABLES `testimonials` WRITE;
UNLOCK TABLES;

LOCK TABLES `units` WRITE;
INSERT INTO `units` (`id`, `name`, `symbol`, `kg_factor`, `created_at`, `deleted_at`) VALUES (1,'Kilogram','kg',1.0000,'2026-11-01 16:57:23',NULL),(2,'Bag (50 kg)','bag50',50.0000,'2026-11-01 16:57:23',NULL),(3,'Bag (25 kg)','bag25',25.0000,'2026-11-01 16:57:23',NULL),(4,'Tonne','t',1000.0000,'2026-11-01 16:57:23',NULL);
UNLOCK TABLES;

LOCK TABLES `user_farms` WRITE;
UNLOCK TABLES;

LOCK TABLES `users` WRITE;
INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `phone`, `password_hash`, `avatar`, `status`, `must_change_password`, `last_login_at`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'System Administrator','admin@poultrypro.test',NULL,'$2y$10$3Dx0FOF04M95x1Jz9orDS.QVrriY9xSgvIC5MuOsLeVT8gU6DVsmS',NULL,'active',1,NULL,'2026-11-01 16:57:23',NULL,NULL);
UNLOCK TABLES;

LOCK TABLES `vaccination_records` WRITE;
UNLOCK TABLES;

LOCK TABLES `vaccinations` WRITE;
UNLOCK TABLES;

LOCK TABLES `website_settings` WRITE;
INSERT INTO `website_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('about_history','We began with a single broiler shed and a handful of local customers. As demand for dependable supply grew, we added layer houses, our own feed planning and a small veterinary team.\n\nToday we supply live birds, day-old chicks, eggs and feed to shops, restaurants and other farms across the region.',NULL),('about_image','images/site/hen-closeup.jpg',NULL),('about_intro','PoultryPro Farms raises broilers and layers in clean, climate-managed sheds. Every flock is recorded from the day it arrives until the day it leaves, so we always know what our birds ate, how they are doing and what we can promise our customers.',NULL),('about_mission','To supply healthy birds and fresh eggs that families and businesses can rely on, every week of the year.',NULL),('about_quality','A vaccination programme for every batch, daily health checks, and full records of feed, medicine and mortality for each flock.',NULL),('about_standards','Controlled ventilation and temperature, clean water, measured feeding, and strict entry rules for every shed.',NULL),('about_title','A family farm run with modern discipline',NULL),('about_vision','To be the farm that other farms measure themselves against for bird welfare, hygiene and honest dealing.',NULL),('company_name','PoultryPro Farms',NULL),('company_tagline','Healthy birds, fresh eggs and dependable supply.',NULL),('contact_address','Farm Road, Lahore, Pakistan',NULL),('contact_email','info@poultrypro.example',NULL),('contact_map_embed','https://www.google.com/maps?q=Lahore,Pakistan&output=embed',NULL),('contact_phone','+92 300 0000000',NULL),('contact_whatsapp','923000000000',NULL),('currency','PKR',NULL),('default_tax_rate','0',NULL),('expiry_alert_days','30',NULL),('favicon','',NULL),('footer_about','Broilers, layers, chicks, eggs and feed from professionally managed farms. Raised with care and recorded at every step.',NULL),('footer_copyright','© {year} PoultryPro Farms. All rights reserved.',NULL),('hero_cta_primary','Explore Our Products',NULL),('hero_cta_secondary','Contact Our Team',NULL),('hero_image','images/site/hero-shed.jpg',NULL),('hero_text','We are committed to providing healthy poultry, quality eggs, and reliable poultry products through modern farming practices and professional farm management.',NULL),('hero_title','Healthy Poultry, Quality Products, Sustainable Farming.',NULL),('logo','',NULL),('mortality_alert_pct','5',NULL),('seo_description','Broiler chickens, layer hens, day-old chicks, fresh eggs and poultry feed from a modern, professionally managed farm.',NULL),('seo_keywords','poultry farm, broiler chickens, layer hens, day-old chicks, fresh eggs, poultry feed',NULL),('seo_title','PoultryPro Farms | Healthy poultry, fresh eggs and day-old chicks',NULL),('show_about','1',NULL),('show_blog','1',NULL),('show_contact','1',NULL),('show_facilities','1',NULL),('show_gallery','1',NULL),('show_process','1',NULL),('show_products','1',NULL),('show_services','1',NULL),('show_stats','1',NULL),('show_testimonials','1',NULL),('show_why','1',NULL),('social_facebook','https://www.facebook.com/',NULL),('social_instagram','https://www.instagram.com/',NULL),('social_linkedin','',NULL),('social_youtube','https://www.youtube.com/',NULL),('stat_birds','25000',NULL),('stat_customers','350',NULL),('stat_farms','2',NULL),('stat_years','12',NULL),('vaccination_reminder_days','7',NULL);
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

SET FOREIGN_KEY_CHECKS = 1;
