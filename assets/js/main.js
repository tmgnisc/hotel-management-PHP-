// Modern JavaScript with enhanced UX
document.addEventListener('DOMContentLoaded', function() {
    initMobileMenu();
    initAutoHideMessages();
    initFormEnhancements();
    initSmoothScrolling();
    initTableEnhancements();
});

// Mobile menu toggle with animation
function initMobileMenu() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navMenu = document.getElementById('navMenu');
    
    if (mobileMenuToggle && navMenu) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            navMenu.classList.toggle('active');
            
            // Animate hamburger icon
            if (navMenu.classList.contains('active')) {
                mobileMenuToggle.innerHTML = '✕';
            } else {
                mobileMenuToggle.innerHTML = '☰';
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const isClickInsideNav = navMenu.contains(event.target);
            const isClickOnToggle = mobileMenuToggle.contains(event.target);
            
            if (!isClickInsideNav && !isClickOnToggle && navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
                mobileMenuToggle.innerHTML = '☰';
            }
        });
        
        // Close menu on window resize if desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768 && navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
                mobileMenuToggle.innerHTML = '☰';
            }
        });
    }
}

// Auto-hide messages with smooth animation
function initAutoHideMessages() {
    const messages = document.querySelectorAll('.message, .error-message');
    messages.forEach(function(message) {
        // Add close button
        const closeBtn = document.createElement('span');
        closeBtn.innerHTML = '×';
        closeBtn.style.cssText = 'float: right; cursor: pointer; font-size: 1.5em; font-weight: bold; opacity: 0.7;';
        closeBtn.addEventListener('click', function() {
            hideMessage(message);
        });
        message.appendChild(closeBtn);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            hideMessage(message);
        }, 5000);
    });
}

function hideMessage(message) {
    message.style.transition = 'all 0.5s ease-out';
    message.style.opacity = '0';
    message.style.transform = 'translateX(-100%)';
    setTimeout(function() {
        if (message.parentNode) {
            message.remove();
        }
    }, 500);
}

// Form enhancements with real-time validation
function initFormEnhancements() {
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(function(input) {
            // Add focus animation
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
                this.parentElement.style.transition = 'transform 0.2s ease';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
                validateField(this);
            });
            
            // Real-time validation for required fields
            if (input.hasAttribute('required')) {
                input.addEventListener('input', function() {
                    validateField(this);
                });
            }
        });
        
        // Form submission with loading state
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && validateForm(form.id)) {
                submitBtn.style.opacity = '0.7';
                submitBtn.style.cursor = 'not-allowed';
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';
            }
        });
    });
}

function validateField(field) {
    if (field.hasAttribute('required') && !field.value.trim()) {
        field.style.borderColor = '#ef4444';
        field.style.boxShadow = '0 0 0 0.2vw rgba(239, 68, 68, 0.1)';
        return false;
    } else {
        field.style.borderColor = '#10b981';
        field.style.boxShadow = '0 0 0 0.2vw rgba(16, 185, 129, 0.1)';
        return true;
    }
}

// Smooth scrolling for better UX
function initSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Table enhancements
function initTableEnhancements() {
    const tables = document.querySelectorAll('.data-table');
    tables.forEach(function(table) {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(row, index) {
            // Add staggered animation
            row.style.opacity = '0';
            row.style.transform = 'translateY(2vw)';
            setTimeout(function() {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 50);
        });
    });
}

// Form validation helper with enhanced feedback
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;
    
    requiredFields.forEach(function(field) {
        if (!validateField(field)) {
            isValid = false;
            if (!firstInvalidField) {
                firstInvalidField = field;
            }
        }
    });
    
    // Scroll to first invalid field
    if (!isValid && firstInvalidField) {
        firstInvalidField.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
        firstInvalidField.focus();
        
        // Shake animation
        firstInvalidField.style.animation = 'shake 0.5s';
        setTimeout(function() {
            firstInvalidField.style.animation = '';
        }, 500);
    }
    
    return isValid;
}

// Add shake animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-1vw); }
        75% { transform: translateX(1vw); }
    }
`;
document.head.appendChild(style);

// Number formatting helper
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Date formatting helper
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Modal enhancements
function enhanceModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            closeModal();
        }
    });
    
    // Prevent body scroll when modal is open
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (modal.style.display === 'block') {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
    });
    
    observer.observe(modal, {
        attributes: true,
        attributeFilter: ['style']
    });
}

// Button ripple effect
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn')) {
        const button = e.target;
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');
        
        button.appendChild(ripple);
        
        setTimeout(function() {
            ripple.remove();
        }, 600);
    }
});

// Add ripple CSS
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    .btn {
        position: relative;
        overflow: hidden;
    }
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyle);

