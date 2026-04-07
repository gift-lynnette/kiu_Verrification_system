// KIU Automated Verification System - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Dropdown menus
    initDropdowns();
    
    // Form validation
    initFormValidation();
    
    // Auto-dismiss alerts
    autoDissmissAlerts();
    
    // File upload preview
    initFilePreview();
});

// Initialize dropdown menus
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                menu.classList.remove('show');
            });
        }
    });
}

// Form validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showError(field, 'This field is required');
            isValid = false;
        } else {
            clearError(field);
        }
    });
    
    return isValid;
}

function showError(field, message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    
    const existingError = field.parentElement.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    field.parentElement.appendChild(errorDiv);
    field.classList.add('is-invalid');
}

function clearError(field) {
    const errorDiv = field.parentElement.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
    field.classList.remove('is-invalid');
}

// Auto-dismiss alerts
function autoDissmissAlerts() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
}

// File upload preview
function initFilePreview() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const fileName = file.name;
                const fileSize = formatFileSize(file.size);
                const preview = document.createElement('div');
                preview.className = 'file-preview';
                preview.textContent = `${fileName} (${fileSize})`;
                
                const existingPreview = this.parentElement.querySelector('.file-preview');
                if (existingPreview) {
                    existingPreview.remove();
                }
                
                this.parentElement.appendChild(preview);
            }
        });
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// AJAX helper function
function ajax(url, method, data, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            callback(null, response);
        } else {
            callback(new Error('Request failed'), null);
        }
    };
    
    xhr.onerror = function() {
        callback(new Error('Request failed'), null);
    };
    
    xhr.send(JSON.stringify(data));
}

// Show loading indicator
function showLoading() {
    const loader = document.createElement('div');
    loader.id = 'loading-overlay';
    loader.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(loader);
}

function hideLoading() {
    const loader = document.getElementById('loading-overlay');
    if (loader) {
        loader.remove();
    }
}

// Confirmation dialog
function confirm(message, callback) {
    if (window.confirm(message)) {
        callback();
    }
}

// Format currency
function formatCurrency(amount) {
    return 'UGX ' + parseFloat(amount).toLocaleString('en-UG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB');
}

// Export functions
window.kiuApp = {
    ajax,
    showLoading,
    hideLoading,
    confirm,
    formatCurrency,
    formatDate
};
