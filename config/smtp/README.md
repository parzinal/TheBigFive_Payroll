# SMTP Configuration Guide

## Overview
The forgot password feature uses email-based OTP verification for secure password resets.

## Files Created
1. `config/smtp.php` - SMTP configuration and email functions
2. `forgotpass.php` - Password reset form with 3-step process

## Setup Instructions

### 1. Configure SMTP Settings

Edit `config/smtp.php` and update the following constants:

```php
define('SMTP_HOST', 'smtp.gmail.com');  // Your SMTP server
define('SMTP_PORT', 587);                // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email
define('SMTP_PASSWORD', 'your-app-password');    // App password
define('SMTP_SECURE', 'tls');            // 'tls' or 'ssl'
define('SMTP_FROM_EMAIL', 'noreply@thebigfive.com');
define('SMTP_FROM_NAME', 'TheBigFive Payroll System');
```

### 2. Gmail Setup (if using Gmail)

For Gmail accounts, you need to use an **App Password**:

1. Go to your Google Account settings
2. Navigate to Security
3. Enable 2-Factor Authentication
4. Go to "App passwords"
5. Generate a new app password for "Mail"
6. Copy the 16-character password
7. Use this password in `SMTP_PASSWORD`

### 3. Alternative SMTP Providers

**SendGrid:**
```php
define('SMTP_HOST', 'smtp.sendgrid.net');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'apikey');
define('SMTP_PASSWORD', 'your-sendgrid-api-key');
```

**Mailgun:**
```php
define('SMTP_HOST', 'smtp.mailgun.org');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'postmaster@your-domain.com');
define('SMTP_PASSWORD', 'your-mailgun-password');
```

**AWS SES:**
```php
define('SMTP_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-ses-username');
define('SMTP_PASSWORD', 'your-ses-password');
```

## Password Reset Process

### Step 1: Email Submission
- User enters their registered email address
- System checks if email exists in database
- Generates 6-digit OTP
- Sends OTP via email
- OTP expires in 10 minutes

### Step 2: OTP Verification
- User enters the 6-digit OTP received via email
- System validates OTP and expiry time
- Option to resend OTP if not received

### Step 3: Password Reset
- User enters new password
- Password must be at least 6 characters
- Confirmation password must match
- Password is hashed and saved
- Success email sent to user
- Redirects to login page

## Features

✅ **3-Step Process** - Email → OTP → New Password
✅ **OTP Expiry** - OTPs expire after 10 minutes
✅ **Resend OTP** - Users can request a new OTP
✅ **Session-based** - Secure session management
✅ **Email Templates** - Professional HTML email templates
✅ **Password Validation** - Client and server-side validation
✅ **Security** - OTP stored in session, not database
✅ **Success Notifications** - Confirmation emails sent

## Email Templates

### OTP Email
- Professional design matching brand
- Large, clear OTP display
- 10-minute expiry warning
- Security notice

### Success Email
- Password reset confirmation
- Direct login link
- Security alert if unauthorized

## Testing

### Local Testing (without real SMTP)
The current implementation uses PHP's `mail()` function. For local testing:

1. Use a tool like **MailHog** or **FakeSMTP**
2. Or modify the code to log emails to a file:

```php
// In smtp.php, replace mail() function with:
file_put_contents('emails.log', $message . "\n\n", FILE_APPEND);
return true;
```

### Production Deployment
For production, consider using:
- **PHPMailer** library (recommended)
- Cloud email services (SendGrid, Mailgun, AWS SES)
- SMTP authentication

## Security Best Practices

1. **Use App Passwords** - Never use your main email password
2. **Enable TLS/SSL** - Encrypt email transmission
3. **Rate Limiting** - Add rate limits to prevent abuse
4. **Log Attempts** - Log password reset attempts
5. **Secure Sessions** - Use secure session management
6. **HTTPS Only** - Always use HTTPS in production

## Troubleshooting

**Emails not sending:**
- Check SMTP credentials
- Verify firewall/port settings
- Enable less secure apps (Gmail)
- Check spam folder
- Review server error logs

**OTP expired:**
- OTP is valid for 10 minutes only
- Use "Resend OTP" to get a new code

**Session errors:**
- Ensure sessions are enabled
- Check session timeout settings

## Upgrading to PHPMailer (Recommended)

For better reliability, install PHPMailer:

```bash
composer require phpmailer/phpmailer
```

Then update `smtp.php` to use PHPMailer instead of `mail()`.

## Support

For issues or questions:
- Check error logs in your server
- Verify SMTP settings
- Test with a simple email first
- Contact your hosting provider for SMTP details
