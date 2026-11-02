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
