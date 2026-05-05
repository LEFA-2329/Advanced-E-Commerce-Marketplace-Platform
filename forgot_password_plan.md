# Forgot Password Implementation Plan

## Database Changes Needed
1. Create `password_reset_tokens` table to store OTP tokens
2. Create `password_reset_attempts` table to track reset attempts

## Files to Create/Modify
1. `unified_login.php` - Add forgot password link and form
2. `forgot_password.php` - Handle password reset request
3. `reset_password.php` - Handle password reset with OTP
4. `send_otp.php` - Send OTP email functionality
5. `verify_otp.php` - Verify OTP functionality

## Implementation Steps

### 1. Database Schema Updates
```sql
-- Password Reset Tokens Table
CREATE TABLE password_reset_tokens (
    token_id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(6) NOT NULL,
    user_type VARCHAR(20) NOT NULL, -- 'customer' or 'user' (for owners/managers)
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Password Reset Attempts Table
CREATE TABLE password_reset_attempts (
    attempt_id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    attempt_count INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2. Email Configuration
- Use PHP's mail() function for simplicity
- Configure SMTP settings if needed

### 3. Security Considerations
- OTP expiration (15 minutes)
- Rate limiting (max 3 attempts per hour)
- Token validation
- CSRF protection

### 4. User Flow
1. User clicks "Forgot Password" on login page
2. Enters email address
3. System sends OTP to email
4. User enters OTP and new password
5. System verifies OTP and updates password
6. User redirected to login page

### 5. Testing
- Test with both customer and owner accounts
- Test OTP expiration
- Test rate limiting
- Test invalid OTP handling
