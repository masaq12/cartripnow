-- Car Trip Now - Test Data
-- Sample vehicles, users, and trips for testing

USE car_trip_now;

-- Insert test users (password for all: password123)
INSERT INTO users (email, password_hash, full_name, phone, user_type, driver_license_verified, status) VALUES
('john.renter@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Smith', '+1-555-0101', 'renter', TRUE, 'active'),
('sarah.renter@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Johnson', '+1-555-0102', 'renter', TRUE, 'active'),
('mike.owner@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Wilson', '+1-555-0201', 'owner', FALSE, 'active'),
('lisa.owner@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa Brown', '+1-555-0202', 'owner', FALSE, 'active'),
('david.owner@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David Martinez', '+1-555-0203', 'owner', FALSE, 'active');

-- Get user IDs for reference
SET @john_id = (SELECT user_id FROM users WHERE email = 'john.renter@example.com');
SET @sarah_id = (SELECT user_id FROM users WHERE email = 'sarah.renter@example.com');
SET @mike_id = (SELECT user_id FROM users WHERE email = 'mike.owner@example.com');
SET @lisa_id = (SELECT user_id FROM users WHERE email = 'lisa.owner@example.com');
SET @david_id = (SELECT user_id FROM users WHERE email = 'david.owner@example.com');

-- Create renter balances
INSERT INTO renter_balances (user_id, current_balance, pending_holds, total_spent) VALUES
(@john_id, 1500.00, 0.00, 500.00),
(@sarah_id, 2000.00, 0.00, 0.00);

-- Create payment credentials for renters
INSERT INTO payment_credentials (user_id, credential_type, credential_number, credential_name, status, expiry_date) VALUES
(@john_id, 'platform_card', 'PC-A1B2C3D4E5F6', 'John Smith', 'active', DATE_ADD(CURDATE(), INTERVAL 3 YEAR)),
(@sarah_id, 'platform_card', 'PC-G7H8I9J0K1L2', 'Sarah Johnson', 'active', DATE_ADD(CURDATE(), INTERVAL 3 YEAR));

-- Create owner balances
INSERT INTO owner_balances (user_id, available_balance, pending_balance, total_earned, total_paid_out) VALUES
(@mike_id, 450.00, 150.00, 1200.00, 600.00),
(@lisa_id, 780.00, 0.00, 2500.00, 1720.00),
(@david_id, 0.00, 0.00, 0.00, 0.00);

-- Create owner verification records
INSERT INTO owner_verification (user_id, verification_status, verified_at) VALUES
(@mike_id, 'verified', NOW()),
(@lisa_id, 'verified', NOW()),
(@david_id, 'pending', NULL);

-- Insert sample vehicles
INSERT INTO vehicles (owner_id, make, model, year, vin, license_plate, color, vehicle_type, transmission, fuel_type, seats, doors, odometer_reading, description, vehicle_features, vehicle_rules, daily_price, mileage_limit_per_day, extra_mileage_fee, pickup_location_address, pickup_city, pickup_state, pickup_country, pickup_zipcode, security_deposit, status, instant_book) VALUES
(@mike_id, 'Tesla', 'Model 3', 2023, '5YJ3E1EA1KF123456', 'CAL-2023', 'Pearl White', 'electric', 'automatic', 'electric', 5, 4, 8500, 'Brand new Tesla Model 3 with Autopilot. Perfect for city trips and long drives. Super clean and well-maintained.', 'Autopilot, Premium Sound System, Glass Roof, Heated Seats, Bluetooth, Backup Camera, Navigation', 'No smoking, No pets, Return with same charge level, Keep it clean', 89.00, 200, 0.50, '123 Tech Drive, Palo Alto', 'Palo Alto', 'California', 'USA', '94301', 500.00, 'active', TRUE),

(@mike_id, 'Honda', 'Civic', 2022, '19XFC2F59ME123456', 'CAL-2022', 'Silver', 'sedan', 'automatic', 'gasoline', 5, 4, 15200, 'Reliable Honda Civic, great gas mileage. Perfect for everyday use and road trips.', 'Backup Camera, Bluetooth, Apple CarPlay, Android Auto, Lane Keeping Assist', 'No smoking, Keep it clean, Fill tank before return', 45.00, 150, 0.40, '456 Main Street, San Jose', 'San Jose', 'California', 'USA', '95110', 300.00, 'active', TRUE),

(@lisa_id, 'Ford', 'F-150', 2021, '1FTFW1E84MFA12345', 'TEX-2021', 'Blue', 'truck', 'automatic', 'gasoline', 6, 4, 28000, 'Powerful Ford F-150 pickup truck. Great for hauling and outdoor adventures. 4WD capability.', '4-Wheel Drive, Towing Package, Backup Camera, Bluetooth, Bed Liner, Running Boards', 'No smoking, Clean the bed after use, Report any damage immediately', 75.00, 180, 0.45, '789 Ranch Road, Austin', 'Austin', 'Texas', 'USA', '78701', 600.00, 'active', FALSE),

(@lisa_id, 'Toyota', 'Camry', 2023, '4T1C11AK8PU123456', 'TEX-2023', 'Black', 'sedan', 'automatic', 'hybrid', 5, 4, 5000, 'Brand new Toyota Camry Hybrid. Excellent fuel economy and comfortable ride.', 'Hybrid Engine, Lane Departure Warning, Adaptive Cruise Control, Heated Seats, Premium Sound', 'No smoking, No pets, Keep it clean', 55.00, 200, 0.35, '321 Downtown Blvd, Austin', 'Austin', 'Texas', 'USA', '78702', 400.00, 'active', TRUE),

(@david_id, 'Jeep', 'Wrangler', 2020, '1C4HJXDG0LW123456', 'COL-2020', 'Green', 'suv', 'manual', 'gasoline', 5, 2, 45000, 'Adventure-ready Jeep Wrangler. Perfect for off-road trips and mountain adventures.', 'Removable Top, 4-Wheel Drive, Off-Road Tires, Bluetooth, Heavy Duty Suspension', 'Off-road use allowed, Clean interior after muddy trips, No smoking', 85.00, 150, 0.55, '555 Mountain View, Denver', 'Denver', 'Colorado', 'USA', '80202', 700.00, 'active', FALSE);

-- Get vehicle IDs
SET @tesla_id = (SELECT vehicle_id FROM vehicles WHERE vin = '5YJ3E1EA1KF123456');
SET @civic_id = (SELECT vehicle_id FROM vehicles WHERE vin = '19XFC2F59ME123456');
SET @f150_id = (SELECT vehicle_id FROM vehicles WHERE vin = '1FTFW1E84MFA12345');
SET @camry_id = (SELECT vehicle_id FROM vehicles WHERE vin = '4T1C11AK8PU123456');
SET @jeep_id = (SELECT vehicle_id FROM vehicles WHERE vin = '1C4HJXDG0LW123456');

-- Add sample trips
INSERT INTO trips (vehicle_id, renter_id, owner_id, pickup_date, return_date, pickup_time, return_time, trip_duration_days, daily_rate, total_days_cost, mileage_limit, insurance_plan_id, insurance_fee, platform_fee, service_fee_percent, security_deposit, total_amount, payment_credential_id, trip_status, payment_status) VALUES
(@tesla_id, @john_id, @mike_id, DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY), '10:00:00', '10:00:00', 3, 89.00, 267.00, 600, 2, 90.00, 53.55, 15.00, 500.00, 910.55, (SELECT credential_id FROM payment_credentials WHERE user_id = @john_id LIMIT 1), 'confirmed', 'held'),

(@civic_id, @sarah_id, @mike_id, DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), '09:00:00', '18:00:00', 5, 45.00, 225.00, 750, 1, 75.00, 45.00, 15.00, 300.00, 645.00, (SELECT credential_id FROM payment_credentials WHERE user_id = @sarah_id LIMIT 1), 'confirmed', 'held'),

(@camry_id, @john_id, @lisa_id, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY), '14:00:00', '14:00:00', 3, 55.00, 165.00, 600, 2, 90.00, 38.25, 15.00, 400.00, 693.25, (SELECT credential_id FROM payment_credentials WHERE user_id = @john_id LIMIT 1), 'completed', 'completed');

-- Add vehicle availability (mark as booked for active trips)
-- Block dates for upcoming trips
INSERT INTO vehicle_availability (vehicle_id, date, status)
SELECT @tesla_id, DATE_ADD(CURDATE(), INTERVAL n DAY), 'booked'
FROM (SELECT 5 as n UNION SELECT 6 UNION SELECT 7 UNION SELECT 8) dates;

INSERT INTO vehicle_availability (vehicle_id, date, status)
SELECT @civic_id, DATE_ADD(CURDATE(), INTERVAL n DAY), 'booked'
FROM (SELECT 10 as n UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15) dates;

-- Add some reviews
INSERT INTO reviews (trip_id, reviewer_id, reviewee_id, review_type, rating, cleanliness_rating, communication_rating, vehicle_condition_rating, comment) VALUES
((SELECT trip_id FROM trips WHERE vehicle_id = @camry_id AND trip_status = 'completed' LIMIT 1), @john_id, @lisa_id, 'renter_to_owner', 5, 5, 5, 5, 'Excellent vehicle! Super clean and drove perfectly. Lisa was very responsive and helpful. Highly recommend!');

-- Add to escrow for active trips
INSERT INTO escrow (trip_id, trip_amount, deposit_amount, total_amount, status)
SELECT trip_id, total_days_cost + insurance_fee + platform_fee, security_deposit, total_amount, 'held'
FROM trips
WHERE trip_status IN ('confirmed', 'active');

-- Add transaction records
INSERT INTO transactions (user_id, transaction_type, amount, balance_before, balance_after, reference_type, reference_id, description)
VALUES
-- John's initial balance
(@john_id, 'deposit', 1000.00, 0.00, 1000.00, NULL, NULL, 'Welcome bonus - Starting balance'),
-- John's first trip payment
(@john_id, 'deduction', 910.55, 1500.00, 589.45, 'trip', (SELECT trip_id FROM trips WHERE vehicle_id = @tesla_id LIMIT 1), 'Payment for Tesla Model 3 trip'),
-- John's second trip payment (completed)
(@john_id, 'deduction', 693.25, 1589.45, 896.20, 'trip', (SELECT trip_id FROM trips WHERE vehicle_id = @camry_id AND trip_status = 'completed' LIMIT 1), 'Payment for Toyota Camry trip'),
-- John topped up balance
(@john_id, 'deposit', 1000.00, 896.20, 1896.20, NULL, NULL, 'Balance top-up'),

-- Sarah's initial balance
(@sarah_id, 'deposit', 1000.00, 0.00, 1000.00, NULL, NULL, 'Welcome bonus - Starting balance'),
-- Sarah's trip payment
(@sarah_id, 'deduction', 645.00, 2000.00, 1355.00, 'trip', (SELECT trip_id FROM trips WHERE vehicle_id = @civic_id LIMIT 1), 'Payment for Honda Civic trip'),
-- Sarah topped up
(@sarah_id, 'deposit', 1000.00, 1355.00, 2355.00, NULL, NULL, 'Balance top-up'),

-- Lisa's earnings from completed trip
(@lisa_id, 'earning', 140.23, 640.00, 780.23, 'trip', (SELECT trip_id FROM trips WHERE vehicle_id = @camry_id AND trip_status = 'completed' LIMIT 1), 'Earnings from Toyota Camry trip'),

-- Mike's earnings (past payouts)
(@mike_id, 'payout', 600.00, 1050.00, 450.00, 'payout', NULL, 'Payout to bank account');

-- Add payout methods
INSERT INTO payout_methods (user_id, method_type, account_details, account_holder_name, is_default, status) VALUES
(@mike_id, 'bank_account', '{"bank_name":"Chase Bank","account_number":"****1234","routing_number":"021000021","account_type":"checking"}', 'Mike Wilson', TRUE, 'active'),
(@lisa_id, 'bank_account', '{"bank_name":"Bank of America","account_number":"****5678","routing_number":"026009593","account_type":"savings"}', 'Lisa Brown', TRUE, 'active');

-- Add some wishlists
INSERT INTO wishlists (user_id, vehicle_id, notes) VALUES
(@john_id, @jeep_id, 'Want to rent for mountain trip in summer'),
(@sarah_id, @tesla_id, 'Always wanted to try electric!'),
(@sarah_id, @f150_id, 'Need for moving furniture');

SELECT 'Test data inserted successfully!' as status;
SELECT 'Users created (password for all: password123):' as note;
SELECT email, user_type, full_name FROM users WHERE user_type != 'admin';
SELECT '' as separator;
SELECT 'Vehicles available:' as note;
SELECT CONCAT(year, ' ', make, ' ', model) as vehicle, pickup_city, daily_price, status FROM vehicles;
