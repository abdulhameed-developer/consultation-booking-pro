/**
 * Client-Side Validation Rules
 * Used by form-script.js and potentially the Admin Builder for preview
 */

const CBP_VALIDATION_RULES = {
    student_name: {
        required: true,
        minLength: 3,
        error: "Please enter your full name (at least 3 characters)."
    },
    student_email: {
        required: true,
        pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        error: "Please enter a valid professional email address."
    },
    student_phone: {
        required: true,
        pattern: /^[0-9\-\s\(\)\+]{7,15}$/,
        error: "Please enter a valid phone or WhatsApp number."
    },
    country: {
        required: true,
        error: "Please select your country."
    },
    booking_date: {
        required: true,
        error: "Please select a preferred date for your consultation."
    },
    booking_time: {
        required: true,
        error: "Please select an available time slot."
    },
    message: {
        required: false,
        maxLength: 500
    }
};

if (typeof module !== 'undefined') {
    module.exports = CBP_VALIDATION_RULES;
}
