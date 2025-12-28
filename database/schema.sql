-- Car Trip Now Database Schema
-- Turo-Style Car-Sharing Marketplace with Swipe Pay System

CREATE DATABASE IF NOT EXISTS car_trip_now;
USE car_trip_now;

-- Users Table (Renters, Vehicle Owners, Admin)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    user_type ENUM('renter', 'owner', 'admin') NOT NULL,
    driver_license_number VARCHAR(50),
    driver_license_verified BOOLEAN DEFAULT FALSE,
    driver_license_expiry DATE,
    status ENUM('active', 'suspended', 'frozen') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user_type (user_type),
    INDEX idx_status (status)
);

-- Renter Payment Credentials (Platform-Issued)
CREATE TABLE payment_credentials (
    credential_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    credential_type ENUM('platform_card', 'platform_number') NOT NULL,
    credential_number VARCHAR(50) UNIQUE NOT NULL,
    credential_name VARCHAR(255),
    status ENUM('active', 'suspended', 'expired') DEFAULT 'active',
    issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_credential_number (credential_number),
    INDEX idx_user_id (user_id)
);

-- Renter Balances
CREATE TABLE renter_balances (
    balance_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    current_balance DECIMAL(10, 2) DEFAULT 0.00,
    pending_holds DECIMAL(10, 2) DEFAULT 0.00,
    total_spent DECIMAL(10, 2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Vehicle Owner Balances
CREATE TABLE owner_balances (
    balance_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    available_balance DECIMAL(10, 2) DEFAULT 0.00,
    pending_balance DECIMAL(10, 2) DEFAULT 0.00,
    total_earned DECIMAL(10, 2) DEFAULT 0.00,
    total_paid_out DECIMAL(10, 2) DEFAULT 0.00,
    platform_fees_paid DECIMAL(10, 2) DEFAULT 0.00,
    insurance_fees_paid DECIMAL(10, 2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Owner Verification
CREATE TABLE owner_verification (
    verification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    business_name VARCHAR(255),
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_documents TEXT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Vehicles (Listings)
CREATE TABLE vehicles (
    vehicle_id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT NOT NULL,
    make VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    year INT NOT NULL,
    vin VARCHAR(17) UNIQUE,
    license_plate VARCHAR(20),
    color VARCHAR(50),
    vehicle_type ENUM('sedan', 'suv', 'truck', 'van', 'sports', 'luxury', 'electric', 'hybrid', 'other') NOT NULL,
    transmission ENUM('automatic', 'manual') DEFAULT 'automatic',
    fuel_type ENUM('gasoline', 'diesel', 'electric', 'hybrid') DEFAULT 'gasoline',
    seats INT DEFAULT 5,
    doors INT DEFAULT 4,
    odometer_reading INT DEFAULT 0,
    description TEXT,
    vehicle_features TEXT,
    vehicle_rules TEXT,
    
    -- Pricing
    daily_price DECIMAL(10, 2) NOT NULL,
    weekly_discount_percent DECIMAL(5, 2) DEFAULT 0.00,
    monthly_discount_percent DECIMAL(5, 2) DEFAULT 0.00,
    
    -- Mileage
    mileage_limit_per_day INT DEFAULT 200,
    extra_mileage_fee DECIMAL(10, 2) DEFAULT 0.50,
    
    -- Location
    pickup_location_address TEXT NOT NULL,
    pickup_city VARCHAR(100),
    pickup_state VARCHAR(100),
    pickup_country VARCHAR(100) DEFAULT 'USA',
    pickup_zipcode VARCHAR(20),
    pickup_latitude DECIMAL(10, 8),
    pickup_longitude DECIMAL(11, 8),
    
    -- Insurance & Deposits
    security_deposit DECIMAL(10, 2) DEFAULT 500.00,
    insurance_required BOOLEAN DEFAULT TRUE,
    min_driver_age INT DEFAULT 25,
    
    -- Status
    status ENUM('active', 'inactive', 'maintenance', 'suspended') DEFAULT 'active',
    instant_book BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_owner_id (owner_id),
    INDEX idx_status (status),
    INDEX idx_location (pickup_city, pickup_state),
    INDEX idx_vehicle_type (vehicle_type),
    INDEX idx_year (year),
    INDEX idx_make_model (make, model)
);

-- Vehicle Photos
CREATE TABLE vehicle_photos (
    photo_id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    photo_url VARCHAR(500) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    INDEX idx_vehicle_id (vehicle_id)
);

-- Vehicle Availability Calendar
CREATE TABLE vehicle_availability (
    availability_id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('available', 'booked', 'blocked', 'maintenance') DEFAULT 'available',
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    UNIQUE KEY unique_vehicle_date (vehicle_id, date),
    INDEX idx_vehicle_date (vehicle_id, date)
);

-- Insurance Plans
CREATE TABLE insurance_plans (
    plan_id INT PRIMARY KEY AUTO_INCREMENT,
    plan_name VARCHAR(100) NOT NULL,
    plan_description TEXT,
    coverage_amount DECIMAL(12, 2) NOT NULL,
    daily_fee DECIMAL(10, 2) NOT NULL,
    deductible DECIMAL(10, 2) DEFAULT 0.00,
    coverage_details TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
);

-- Trips (Bookings)
CREATE TABLE trips (
    trip_id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id INT NOT NULL,
    renter_id INT NOT NULL,
    owner_id INT NOT NULL,
    
    -- Trip Details
    pickup_date DATE NOT NULL,
    return_date DATE NOT NULL,
    pickup_time TIME NOT NULL,
    return_time TIME NOT NULL,
    trip_duration_days INT NOT NULL,
    
    -- Pricing
    daily_rate DECIMAL(10, 2) NOT NULL,
    total_days_cost DECIMAL(10, 2) NOT NULL,
    
    -- Mileage
    mileage_limit INT NOT NULL,
    odometer_start INT,
    odometer_end INT,
    actual_miles_driven INT,
    extra_mileage_driven INT DEFAULT 0,
    extra_mileage_fee DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Insurance
    insurance_plan_id INT,
    insurance_fee DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Fees
    platform_fee DECIMAL(10, 2) NOT NULL,
    service_fee_percent DECIMAL(5, 2) DEFAULT 15.00,
    
    -- Deposit
    security_deposit DECIMAL(10, 2) NOT NULL,
    deposit_status ENUM('held', 'released', 'partial_released', 'forfeited') DEFAULT 'held',
    deposit_released_amount DECIMAL(10, 2) DEFAULT 0.00,
    deposit_deducted_amount DECIMAL(10, 2) DEFAULT 0.00,
    deposit_deduction_reason TEXT,
    
    -- Total Amount
    total_amount DECIMAL(10, 2) NOT NULL,
    
    -- Payment
    payment_credential_id INT NOT NULL,
    
    -- Status
    trip_status ENUM('pending', 'confirmed', 'active', 'completed', 'cancelled', 'disputed') DEFAULT 'pending',
    payment_status ENUM('pending', 'held', 'completed', 'refunded', 'partial_refund') DEFAULT 'pending',
    
    -- Vehicle Condition
    vehicle_condition_pickup TEXT,
    vehicle_condition_return TEXT,
    damage_reported BOOLEAN DEFAULT FALSE,
    damage_description TEXT,
    damage_photos TEXT,
    
    -- Cancellation
    cancellation_reason TEXT,
    cancelled_by INT,
    cancelled_at TIMESTAMP NULL,
    
    -- Late Return
    late_return_hours INT DEFAULT 0,
    late_return_fee DECIMAL(10, 2) DEFAULT 0.00,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
    FOREIGN KEY (renter_id) REFERENCES users(user_id),
    FOREIGN KEY (owner_id) REFERENCES users(user_id),
    FOREIGN KEY (insurance_plan_id) REFERENCES insurance_plans(plan_id),
    FOREIGN KEY (payment_credential_id) REFERENCES payment_credentials(credential_id),
    FOREIGN KEY (cancelled_by) REFERENCES users(user_id),
    
    INDEX idx_renter_id (renter_id),
    INDEX idx_owner_id (owner_id),
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_trip_status (trip_status),
    INDEX idx_dates (pickup_date, return_date),
    INDEX idx_created_at (created_at)
);

-- Platform Escrow (Holding Area for Trip Payments)
CREATE TABLE escrow (
    escrow_id INT PRIMARY KEY AUTO_INCREMENT,
    trip_id INT UNIQUE NOT NULL,
    trip_amount DECIMAL(10, 2) NOT NULL,
    deposit_amount DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('held', 'released', 'refunded', 'partial_released') DEFAULT 'held',
    held_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL,
    release_notes TEXT,
    FOREIGN KEY (trip_id) REFERENCES trips(trip_id) ON DELETE CASCADE,
    INDEX idx_trip_id (trip_id),
    INDEX idx_status (status)
);

-- Transactions (All balance movements)
CREATE TABLE transactions (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    transaction_type ENUM('deposit', 'deduction', 'earning', 'payout', 'refund', 'fee', 'hold', 'release', 'deposit_hold', 'deposit_release', 'damage_charge', 'late_fee') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    balance_before DECIMAL(10, 2) NOT NULL,
    balance_after DECIMAL(10, 2) NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_created_at (created_at),
    INDEX idx_reference (reference_type, reference_id)
);

-- Owner Payout Methods
CREATE TABLE payout_methods (
    payout_method_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    method_type ENUM('bank_account', 'crypto_wallet', 'business_account') NOT NULL,
    account_details TEXT NOT NULL,
    account_holder_name VARCHAR(255),
    routing_number VARCHAR(50),
    account_number_masked VARCHAR(50),
    crypto_address VARCHAR(255),
    is_default BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Payouts
CREATE TABLE payouts (
    payout_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    payout_method_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payout_type ENUM('manual', 'automatic') DEFAULT 'manual',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    failure_reason TEXT,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (payout_method_id) REFERENCES payout_methods(payout_method_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_requested_at (requested_at)
);

-- Reviews
CREATE TABLE reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    trip_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    review_type ENUM('renter_to_owner', 'owner_to_renter') NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    cleanliness_rating INT CHECK (cleanliness_rating BETWEEN 1 AND 5),
    communication_rating INT CHECK (communication_rating BETWEEN 1 AND 5),
    vehicle_condition_rating INT CHECK (vehicle_condition_rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(trip_id),
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id),
    FOREIGN KEY (reviewee_id) REFERENCES users(user_id),
    INDEX idx_trip_id (trip_id),
    INDEX idx_reviewee_id (reviewee_id)
);

-- Disputes
CREATE TABLE disputes (
    dispute_id INT PRIMARY KEY AUTO_INCREMENT,
    trip_id INT NOT NULL,
    raised_by INT NOT NULL,
    dispute_type ENUM('payment', 'vehicle_damage', 'mileage', 'cancellation', 'late_return', 'deposit', 'other') NOT NULL,
    description TEXT NOT NULL,
    evidence TEXT,
    status ENUM('open', 'investigating', 'resolved', 'closed') DEFAULT 'open',
    resolution TEXT,
    resolution_amount DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(trip_id),
    FOREIGN KEY (raised_by) REFERENCES users(user_id),
    INDEX idx_trip_id (trip_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Wishlist (Favorite Vehicles)
CREATE TABLE wishlists (
    wishlist_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_vehicle (user_id, vehicle_id),
    INDEX idx_user_id (user_id),
    INDEX idx_vehicle_id (vehicle_id)
);

-- Admin Actions Log
CREATE TABLE admin_actions (
    action_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at),
    INDEX idx_target (target_type, target_id)
);

-- Platform Settings
CREATE TABLE platform_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default platform settings
INSERT INTO platform_settings (setting_key, setting_value, description) VALUES
('platform_fee_percent', '15.00', 'Platform service fee percentage'),
('insurance_fee_percent', '10.00', 'Insurance fee percentage'),
('min_payout_amount', '50.00', 'Minimum payout amount for owners'),
('currency', 'USD', 'Platform currency'),
('platform_name', 'Car Trip Now', 'Platform name'),
('late_return_fee_per_hour', '25.00', 'Late return fee per hour'),
('default_security_deposit', '500.00', 'Default security deposit amount'),
('max_trip_duration_days', '30', 'Maximum trip duration in days'),
('min_driver_age', '25', 'Minimum driver age');

-- Insert default insurance plans
INSERT INTO insurance_plans (plan_name, plan_description, coverage_amount, daily_fee, deductible, coverage_details, status) VALUES
('Basic Protection', 'Basic coverage for your trip', 50000.00, 15.00, 1000.00, 'Covers collision and theft with $1,000 deductible', 'active'),
('Standard Protection', 'Enhanced coverage with lower deductible', 100000.00, 30.00, 500.00, 'Covers collision, theft, and liability with $500 deductible', 'active'),
('Premium Protection', 'Comprehensive coverage with zero deductible', 250000.00, 50.00, 0.00, 'Full coverage including collision, theft, liability, and roadside assistance with no deductible', 'active');

-- Insert default admin user
-- Password: admin123 (hashed using password_hash())
INSERT INTO users (email, password_hash, full_name, phone, user_type, status, driver_license_verified) VALUES
('admin@cartripnow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '+1-234-567-8900', 'admin', 'active', TRUE);
