# P6/P9 Monitoring - Quick Start (5 Minutes)

Get HMRC P6/P9 tax code monitoring running in 5 minutes!

## Step 1: Configure Email (2 minutes)

Add to your `.env` file:

```env
HMRC_P6P9_ENABLED=true
HMRC_P6P9_METHOD=email
HMRC_P6P9_EMAIL_HOST=imap.gmail.com
HMRC_P6P9_EMAIL_USERNAME=your-payroll@company.com
HMRC_P6P9_EMAIL_PASSWORD=your-app-password
HMRC_P6P9_NOTIFY_TO=payroll@company.com
```

**Gmail App Password**: Google Account → Security → 2FA → App Passwords → Mail

## Step 2: Register Job (1 minute)

Edit `app/Console/Kernel.php`:

```php
use HMRC\PAYE\Laravel\Jobs\CheckP6P9TaxCodesJob;

protected function schedule(Schedule $schedule)
{
    $schedule->job(new CheckP6P9TaxCodesJob)
             ->dailyAt('06:00');
}
```

## Step 3: Add Config (1 minute)

Create `config/hmrc-p6p9.php`:

```php
<?php
return require __DIR__.'/../vendor/shynne109/hmrc/config/hmrc-p6p9.php';
```

## Step 4: Test (1 minute)

```bash
php artisan hmrc:check-p6p9 --method=email
```

You should see:

```
🔍 Checking HMRC for P6/P9 tax code changes...

📧 Checking via email...
Connecting to imap.gmail.com...
✅ Connected to email server
📬 Total messages: 150, Recent: 2
Fetching P6/P9 notices...
✅ Found 3 P6/P9 notice(s)

📋 P6/P9 Tax Code Changes:

┌─────────────┬──────────┬────────────────┬──────┬─────────┐
│ NINO        │ Tax Code │ Effective Date │ Type │ Changed │
├─────────────┼──────────┼────────────────┼──────┼─────────┤
│ AB123456C   │ 1257L    │ 2025-04-06     │ P9   │ ✅ Yes  │
│ CD789012E   │ 1100L    │ 2025-01-15     │ P6   │ No      │
│ EF345678G   │ BR       │ 2025-02-01     │ P6   │ ✅ Yes  │
└─────────────┴──────────┴────────────────┴──────┴─────────┘

✅ P6/P9 check completed
```

## Done! 🎉

Your system will now automatically check for P6/P9 changes every morning at 6 AM and email your payroll team when changes are detected.

## What Happens Next?

Every day at 6 AM:
1. ✅ Connects to your email
2. ✅ Searches for HMRC P6/P9 notices
3. ✅ Detects tax code changes
4. ✅ Sends email alerts
5. ✅ Exports to CSV

## Optional: Register Command

Edit `app/Console/Kernel.php`:

```php
protected $commands = [
    \HMRC\PAYE\Laravel\Commands\CheckP6P9Command::class,
];
```

Now you can use all command options:

```bash
# Check with notifications
php artisan hmrc:check-p6p9 --notify

# Check with export
php artisan hmrc:check-p6p9 --export

# Check specific employee (API mode)
php artisan hmrc:check-p6p9 --method=api --nino=AB123456C
```

## Troubleshooting

### "Failed to connect to email"

Use App Password for Gmail, not your regular password.

**Gmail**: Account → Security → 2-Step Verification → App Passwords

### "No P6/P9 emails found"

- Check folder name (INBOX vs Inbox)
- Ensure HMRC emails aren't in spam
- Try different fetch method:

```env
HMRC_P6P9_EMAIL_FETCH=today  # or 'since'
HMRC_P6P9_EMAIL_SINCE=01-Jan-2025
```

### "Schedule not running"

Ensure cron job is configured:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Test manually:

```bash
php artisan schedule:run
php artisan schedule:list
```

## Full Documentation

- **Complete Guide**: `P6P9_SETUP_GUIDE.md`
- **Main Docs**: `README.md` - P6/P9 section
- **Examples**: `examples/p6p9_usage_examples.php`
- **Summary**: `P6P9_IMPLEMENTATION_SUMMARY.md`

## Support

📧 Check logs: `tail -f storage/logs/laravel.log | grep p6p9`

🔍 Test connection:
```bash
php artisan tinker
>>> $p = new \HMRC\PAYE\P6P9EmailParser(new \HMRC\PAYE\P6P9Monitor('','',''));
>>> $p->connect(config('hmrc-p6p9.email.host'), config('hmrc-p6p9.email.username'), config('hmrc-p6p9.email.password'));
```

---

**That's it!** Your P6/P9 monitoring is now running. ✅
