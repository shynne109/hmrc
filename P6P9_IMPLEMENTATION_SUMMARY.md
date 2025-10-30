# P6/P9 Tax Code Monitoring System - Implementation Summary

## What Was Implemented

A complete automated P6/P9 tax code monitoring system for HMRC PAYE RTI, enabling daily checks for employee tax code changes.

## Files Created

### Core Classes

1. **`src/PAYE/P6P9Monitor.php`** (434 lines)
   - Main monitoring class
   - Methods: `checkEmployeeTaxCode()`, `checkMultipleEmployees()`, `storeNotice()`, `parseP6P9Email()`, `importFromCSV()`, `generateChangeReport()`
   - Supports both API checking and email parsing
   - Change detection by comparing with cached records
   - PSR-3 logging integration

2. **`src/PAYE/P6P9EmailParser.php`** (285 lines)
   - IMAP email parsing service
   - Methods: `connect()`, `fetchTodaysNotices()`, `fetchNoticesSince()`, `fetchUnreadNotices()`
   - Automatic P6/P9 email detection
   - Email body parsing
   - Multi-format support (plain text, HTML)

### Laravel Integration

3. **`src/PAYE/Laravel/Jobs/CheckP6P9TaxCodesJob.php`** (352 lines)
   - Scheduled Laravel job
   - Implements `ShouldQueue` interface
   - Automatic retry on failure (3 attempts)
   - Email and API checking modes
   - Notification and export support
   - Database, CSV, or config-based employee lists

4. **`src/PAYE/Laravel/Commands/CheckP6P9Command.php`** (370 lines)
   - Artisan command: `php artisan hmrc:check-p6p9`
   - Options: `--method`, `--nino`, `--export`, `--notify`
   - Progress bars and formatted output
   - Table display of results
   - Manual checking capability

### Configuration & Templates

5. **`config/hmrc-p6p9.php`** (170 lines)
   - Complete configuration file
   - Email, API, scheduling, notification, export settings
   - Environment variable support
   - Comprehensive documentation in comments

6. **`resources/views/emails/p6p9-changes.blade.php`** (175 lines)
   - HTML email notification template
   - Professional HMRC-styled design
   - Change highlighting (old → new)
   - Responsive layout
   - Action steps for payroll team

7. **`.env.p6p9.example`** (76 lines)
   - Example environment configuration
   - All available settings documented
   - Copy-paste ready

### Documentation

8. **`README.md`** - P6/P9 Section Added (450+ lines)
   - Complete usage documentation
   - Installation instructions
   - Configuration examples
   - Troubleshooting guide
   - Code examples
   - Best practices

9. **`P6P9_SETUP_GUIDE.md`** (386 lines)
   - Step-by-step setup guide
   - Gmail/Outlook configuration
   - Laravel integration steps
   - Testing procedures
   - Production checklist
   - Setup script included

10. **`examples/p6p9_usage_examples.php`** (280 lines)
    - 7 comprehensive examples
    - Email parsing, API checking, CSV import
    - Multiple employee checking
    - Change report generation
    - Laravel integration examples

## Features Implemented

### ✅ Core Features

- **Email Parsing** - Automatic P6/P9 notice detection from HMRC emails via IMAP
- **API Checking** - Check employee tax codes via HMRC OAuth2 APIs
- **Change Detection** - Compare with previous records to identify what changed
- **Multi-Employee Support** - Bulk checking of employee lists
- **CSV Import/Export** - Import historical data, export new notices
- **Caching** - Laravel cache integration for data storage

### ✅ Laravel Integration

- **Scheduled Jobs** - Daily automated checks via Laravel scheduler
- **Queue Support** - Background processing with retry logic
- **Artisan Commands** - Manual checking via CLI
- **Email Notifications** - Formatted HTML emails to payroll team
- **Configuration Management** - Environment-based settings
- **Logging** - PSR-3 logging with Laravel channels

### ✅ Flexibility

- **Multiple Check Methods** - Email parsing OR API checking
- **Multiple Employee Sources** - Config, database, CSV file
- **Configurable Scheduling** - Daily, twice daily, hourly, custom cron
- **Fetch Methods** - Unread, today, or since specific date
- **Export Options** - Enable/disable, custom paths, retention policy

### ✅ User Experience

- **Progress Indicators** - Progress bars for bulk operations
- **Table Output** - Formatted CLI tables
- **Change Highlighting** - Old → New value visualization
- **Status Indicators** - ✅ ❌ ⚠️ emoji indicators
- **Detailed Logging** - Comprehensive debug information

### ✅ Security & Best Practices

- **Environment Variables** - Credentials stored in .env
- **App Passwords** - Support for Gmail/Outlook app passwords
- **Rate Limiting** - Automatic delays between API requests
- **Error Handling** - Graceful failure with retry logic
- **Validation** - Input validation and error checking

## How It Works

### Daily Automated Flow

```
06:00 AM (configurable)
    ↓
Laravel Scheduler triggers CheckP6P9TaxCodesJob
    ↓
Job checks configuration (email or API method)
    ↓
┌─────────────────────┬──────────────────────┐
│   Email Method      │     API Method       │
├─────────────────────┼──────────────────────┤
│ Connect to IMAP     │ Load employee list   │
│ Search HMRC emails  │ OAuth2 authenticate  │
│ Parse P6/P9 content │ Query PAYE API       │
│ Extract tax codes   │ Get current codes    │
└─────────────────────┴──────────────────────┘
    ↓
Compare with cached previous records
    ↓
Detect changes (tax code, effective date, etc.)
    ↓
Store new notices in cache
    ↓
┌─────────────────────────────────┐
│ If changes detected:            │
│ • Send email notifications      │
│ • Export to CSV                 │
│ • Log details                   │
└─────────────────────────────────┘
    ↓
Job completes successfully
```

### Email Parsing Flow

```
HMRC sends P6/P9 email
    ↓
Email arrives in configured inbox
    ↓
Parser connects via IMAP
    ↓
Searches for emails from noreply@tax.service.gov.uk
    ↓
Filters by P6/P9 keywords (tax code, P6, P9, coding notice)
    ↓
Extracts email body (handles HTML/plain text)
    ↓
Regex patterns extract:
• NINO (National Insurance Number)
• Tax Code (e.g., 1257L)
• Effective Date (e.g., 6 April 2025)
• Notice Type (P6 or P9)
• Week/Month number
    ↓
Stores parsed data in cache
    ↓
Marks email as read and flagged
```

## Configuration Examples

### Minimal Setup (Email Method)

```env
HMRC_P6P9_ENABLED=true
HMRC_P6P9_METHOD=email
HMRC_P6P9_EMAIL_HOST=imap.gmail.com
HMRC_P6P9_EMAIL_USERNAME=payroll@company.com
HMRC_P6P9_EMAIL_PASSWORD=app-password-here
HMRC_P6P9_NOTIFY_TO=payroll@company.com
```

### Full Setup (API Method)

```env
HMRC_P6P9_ENABLED=true
HMRC_P6P9_METHOD=api
HMRC_P6P9_API_ENABLED=true
HMRC_P6P9_USE_DB=true
HMRC_P6P9_MODEL=App\Models\Employee
HMRC_P6P9_NOTIFY_TO=payroll@company.com,admin@company.com
HMRC_P6P9_EXPORT=true
HMRC_P6P9_FREQUENCY=daily
HMRC_P6P9_TIME=06:00
```

## Usage Examples

### Artisan Commands

```bash
# Check via email
php artisan hmrc:check-p6p9 --method=email

# Check specific employee via API
php artisan hmrc:check-p6p9 --method=api --nino=AB123456C

# With notifications and export
php artisan hmrc:check-p6p9 --notify --export
```

### Programmatic Usage

```php
use HMRC\PAYE\P6P9Monitor;
use HMRC\PAYE\P6P9EmailParser;

$monitor = new P6P9Monitor($clientId, $clientSecret, $redirectUri);
$parser = new P6P9EmailParser($monitor);

$parser->connect('imap.gmail.com', 'user@example.com', 'password');
$notices = $parser->fetchUnreadNotices();

foreach ($notices as $notice) {
    if (!empty($notice['changes'])) {
        // Tax code changed!
        echo "{$notice['nino']}: {$notice['taxCode']}\n";
    }
}
```

### Laravel Scheduling

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new CheckP6P9TaxCodesJob)
             ->dailyAt('06:00')
             ->name('check-hmrc-p6p9')
             ->withoutOverlapping();
}
```

## Testing

### Test Email Connection

```bash
php artisan tinker
>>> $parser = new \HMRC\PAYE\P6P9EmailParser(new \HMRC\PAYE\P6P9Monitor('','',''));
>>> $parser->connect('imap.gmail.com', 'user', 'password');
>>> $parser->getMailboxInfo();
```

### Test Manual Check

```bash
php artisan hmrc:check-p6p9 --method=email
```

### Test Scheduled Job

```bash
php artisan schedule:run
php artisan schedule:list
```

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| Email connection fails | Use App Password for Gmail, check IMAP settings |
| No emails found | Check folder name, sender address, fetch method |
| Job not running | Verify cron job configured, check Laravel logs |
| Notifications not sent | Check mail config, verify SMTP settings |
| API authentication fails | Verify OAuth2 credentials, check token expiry |

### Debug Commands

```bash
# Check logs
tail -f storage/logs/laravel.log | grep -i p6p9

# View scheduled jobs
php artisan schedule:list

# Clear cache
php artisan cache:clear

# Test notification
php artisan hmrc:check-p6p9 --notify
```

## File Structure

```
hmrc/
├── src/
│   └── PAYE/
│       ├── P6P9Monitor.php              # Core monitoring class
│       ├── P6P9EmailParser.php          # Email parsing service
│       └── Laravel/
│           ├── Jobs/
│           │   └── CheckP6P9TaxCodesJob.php  # Scheduled job
│           └── Commands/
│               └── CheckP6P9Command.php      # Artisan command
├── config/
│   └── hmrc-p6p9.php                    # Configuration file
├── resources/
│   └── views/
│       └── emails/
│           └── p6p9-changes.blade.php   # Email template
├── examples/
│   └── p6p9_usage_examples.php          # Usage examples
├── .env.p6p9.example                    # Example configuration
├── P6P9_SETUP_GUIDE.md                  # Setup guide
└── README.md                            # Main documentation (updated)
```

## Dependencies

- PHP 8.0+
- Laravel 8.x, 9.x, 10.x, or 11.x
- GuzzleHTTP 7.x
- PHP IMAP extension (php-imap)
- PSR-3 Logger interface

## Production Checklist

- [ ] `.env` configured with correct credentials
- [ ] Email account created for HMRC notices
- [ ] App Password generated (Gmail/Outlook)
- [ ] Cron job configured and running
- [ ] Laravel scheduler working
- [ ] Notification recipients verified
- [ ] Export directory writable
- [ ] Mail configuration tested
- [ ] First manual check successful
- [ ] Email template customized
- [ ] Logs monitored for errors
- [ ] Backup strategy for exports

## Next Steps for Users

1. **Install Package**: `composer require shynne109/hmrc`
2. **Configure**: Copy example `.env` settings
3. **Setup Email**: Create app password
4. **Test Connection**: Run manual check
5. **Schedule Job**: Add to Laravel Kernel
6. **Monitor**: Check logs after first scheduled run
7. **Customize**: Adjust email template if needed

## Benefits

- ⏱️ **Time Savings** - Automated daily checks eliminate manual work
- ✅ **Accuracy** - Never miss a tax code change
- 📧 **Instant Alerts** - Payroll team notified immediately
- 📊 **Audit Trail** - CSV exports for compliance
- 🔄 **Integration** - Seamless Laravel integration
- 🛡️ **Reliable** - Retry logic and error handling
- 📝 **Documentation** - Comprehensive guides and examples

## Support & Resources

- **Full Documentation**: See `README.md` - P6/P9 section
- **Setup Guide**: See `P6P9_SETUP_GUIDE.md`
- **Examples**: See `examples/p6p9_usage_examples.php`
- **HMRC P6/P9 Guide**: https://www.gov.uk/guidance/p6-and-p9-tax-codes
- **PAYE RTI Docs**: https://www.gov.uk/government/collections/real-time-information-online-internet-submissions

---

**Implementation Complete!** ✅

This system provides a complete, production-ready solution for automated P6/P9 tax code monitoring with Laravel integration.
