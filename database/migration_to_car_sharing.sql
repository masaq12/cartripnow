-- Migration Script: Convert from Home-Sharing to Car-Sharing
-- Run this AFTER the main schema.sql has been executed

USE car_trip_now;

-- Drop old tables if they exist (from home-sharing system)
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS disputes;
DROP TABLE IF EXISTS escrow;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS listings;
DROP TABLE IF EXISTS renter_balances;
DROP TABLE IF EXISTS host_balances;
DROP TABLE IF EXISTS host_verification;

-- Note: The main schema already creates the correct tables (vehicles, trips, etc.)
-- This migration just ensures any old tables are removed

-- Verify correct tables exist
SELECT 'Checking if vehicles table exists' as status;
SELECT COUNT(*) as vehicle_count FROM vehicles;

SELECT 'Checking if trips table exists' as status;
SELECT COUNT(*) as trip_count FROM trips;

SELECT 'Checking if renter_balances table exists' as status;
SELECT COUNT(*) as renter_balance_count FROM renter_balances;

SELECT 'Checking if owner_balances table exists' as status;
SELECT COUNT(*) as owner_balance_count FROM owner_balances;

-- Add sample data for testing
INSERT INTO insurance_plans (plan_name, plan_description, coverage_amount, daily_fee, deductible, coverage_details, status) VALUES
('Basic Protection', 'Basic coverage for your trip', 50000.00, 15.00, 1000.00, 'Covers collision and theft with $1,000 deductible', 'active'),
('Standard Protection', 'Enhanced coverage with lower deductible', 100000.00, 30.00, 500.00, 'Covers collision, theft, and liability with $500 deductible', 'active'),
('Premium Protection', 'Comprehensive coverage with zero deductible', 250000.00, 50.00, 0.00, 'Full coverage including collision, theft, liability, and roadside assistance with no deductible', 'active')
ON DUPLICATE KEY UPDATE plan_name = plan_name;

SELECT 'Migration completed successfully!' as status;
