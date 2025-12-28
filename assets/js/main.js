// Car Trip Now - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Confirm before delete actions
    const deleteButtons = document.querySelectorAll('.btn-delete, [data-action="delete"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Format currency inputs
    const currencyInputs = document.querySelectorAll('input[type="number"][data-currency]');
    currencyInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
    
    // Preview image uploads
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const preview = document.getElementById(this.dataset.preview);
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    
    // Booking form - Dynamic price calculation
    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        const checkInInput = document.getElementById('pickup_date');
        const checkOutInput = document.getElementById('return_date');
        
        // Set minimum checkout date based on check-in
        checkInInput?.addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            checkInDate.setDate(checkInDate.getDate() ); // Minimum 1 night
            const minCheckOut = checkInDate.toISOString().split('T')[0];
            checkOutInput.setAttribute('min', minCheckOut);
            
            // If checkout is before new minimum, reset it
            if (checkOutInput.value && checkOutInput.value <= this.value) {
                checkOutInput.value = '';
            }
            
            calculateTotal();
        });
        
        checkOutInput?.addEventListener('change', calculateTotal);
        
        function calculateTotal() {
            const checkIn = checkInInput?.value;
            const checkOut = checkOutInput?.value;
            const pricePerNight = parseFloat(document.getElementById('price_per_night')?.value || 0);
            const cleaningFee = parseFloat(document.getElementById('cleaning_fee')?.value || 0);
            const serviceFeePercent = parseFloat(document.getElementById('service_fee_percent')?.value || 15);
            
            if (checkIn && checkOut) {
                const date1 = new Date(checkIn);
                const date2 = new Date(checkOut);
                const nights = Math.ceil((date2 - date1) / (1000 * 60 * 60 * 24));
                
                if (nights > 0) {
                    const nightsTotal = pricePerNight * nights;
                    const serviceFee = nightsTotal * (serviceFeePercent / 100);
                    const subtotal = nightsTotal + cleaningFee + serviceFee;
                    const taxAmount = subtotal * 0.10; // 10% tax
                    const total = subtotal + taxAmount;
                    
                    // Update display
                    document.getElementById('nights_display').textContent = nights;
                    document.getElementById('nights_total').textContent = formatCurrency(nightsTotal);
                    document.getElementById('service_fee_display').textContent = formatCurrency(serviceFee);
                    document.getElementById('tax_display').textContent = formatCurrency(taxAmount);
                    document.getElementById('total_display').textContent = formatCurrency(total);
                    
                    // Update hidden fields
                    document.getElementById('trip_duration_days').value = nights;
                    document.getElementById('total_amount').value = total.toFixed(2);
                    
                    // Show/hide insufficient balance warning
                    const guestBalance = parseFloat(document.getElementById('guest_balance')?.value || 0);
                    const balanceWarning = document.getElementById('balance_warning');
                    
                    if (balanceWarning) {
                        if (total > guestBalance) {
                            balanceWarning.style.display = 'block';
                            bookingForm.querySelector('button[type="submit"]').disabled = true;
                        } else {
                            balanceWarning.style.display = 'none';
                            bookingForm.querySelector('button[type="submit"]').disabled = false;
                        }
                    }
                } else {
                    // Reset if invalid dates
                    resetBookingDisplay();
                }
            } else {
                resetBookingDisplay();
            }
        }
        
        function resetBookingDisplay() {
            document.getElementById('nights_display').textContent = '0';
            document.getElementById('nights_total').textContent = formatCurrency(0);
            document.getElementById('service_fee_display').textContent = formatCurrency(0);
            document.getElementById('tax_display').textContent = formatCurrency(0);
            document.getElementById('total_display').textContent = formatCurrency(0);
        }
        
        // Validate form before submission
        bookingForm.addEventListener('submit', function(e) {
            const checkIn = checkInInput?.value;
            const checkOut = checkOutInput?.value;
            const nights = parseInt(document.getElementById('trip_duration_days')?.value || 0);
            const total = parseFloat(document.getElementById('total_amount')?.value || 0);
            const guestBalance = parseFloat(document.getElementById('guest_balance')?.value || 0);
            
            if (!checkIn || !checkOut) {
                e.preventDefault();
                alert('Please select check-in and check-out dates');
                return false;
            }
            
            // if (nights < 1) {
            //     e.preventDefault();
            //     alert('Check-out date must be at least 1 day after check-in date');
            //     return false;
            // }
            
            if (total > guestBalance) {
                e.preventDefault();
                alert('Insufficient balance. Please add funds to your account.');
                return false;
            }
            
            return true;
        });
    }
    
    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navMenu = document.querySelector('.nav-menu');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }
    
    // Search filters
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        const urlParams = new URLSearchParams(window.location.search);
        document.querySelectorAll('.form-control').forEach(input => {
            const value = urlParams.get(input.name);
            if (value) {
                input.value = value;
            }
        });
    }
    
    // Smooth scroll to sections
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Photo gallery lightbox (simple implementation)
    const galleryImages = document.querySelectorAll('.gallery-image');
    galleryImages.forEach(img => {
        img.addEventListener('click', function() {
            const lightbox = document.createElement('div');
            lightbox.className = 'lightbox';
            lightbox.innerHTML = `
                <div class="lightbox-content">
                    <span class="lightbox-close">&times;</span>
                    <img src="${this.src}" alt="Property Image">
                </div>
            `;
            document.body.appendChild(lightbox);
            
            lightbox.querySelector('.lightbox-close').addEventListener('click', function() {
                lightbox.remove();
            });
            
            lightbox.addEventListener('click', function(e) {
                if (e.target === lightbox) {
                    lightbox.remove();
                }
            });
        });
    });
    
    // Character counter for textareas
    const textareas = document.querySelectorAll('textarea[maxlength]');
    textareas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        counter.style.textAlign = 'right';
        counter.style.fontSize = '12px';
        counter.style.color = '#666';
        counter.style.marginTop = '5px';
        
        const updateCounter = () => {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${remaining} characters remaining`;
            if (remaining < 50) {
                counter.style.color = '#ff6b6b';
            } else {
                counter.style.color = '#666';
            }
        };
        
        textarea.parentNode.insertBefore(counter, textarea.nextSibling);
        updateCounter();
        textarea.addEventListener('input', updateCounter);
    });
    
});

// Helper function to format currency
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

// Helper function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Helper function to calculate nights between dates
function calculateNights(checkIn, checkOut) {
    const date1 = new Date(checkIn);
    const date2 = new Date(checkOut);
    return Math.ceil((date2 - date1) / (1000 * 60 * 60 * 24));
}
