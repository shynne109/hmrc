<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    | P6/P9 Monitoring Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the P6/P9 tax code monitoring system
    |
    */
    
    'enabled' => env('HMRC_P6P9_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Check Method
    |--------------------------------------------------------------------------
    |
    | Method to use for checking P6/P9 notices:
    | - 'email': Parse HMRC emails for P6/P9 notices (recommended)
    | - 'api': Use HMRC API to check employee tax codes (requires OAuth2)
    |
    */
    
    'check_method' => env('HMRC_P6P9_METHOD', 'email'),
    
    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for parsing P6/P9 notices from HMRC emails
    |
    */
    
    'email' => [
        'enabled' => env('HMRC_P6P9_EMAIL_ENABLED', true),
        
        // IMAP server settings
        'host' => env('HMRC_P6P9_EMAIL_HOST', 'imap.gmail.com'),
        'username' => env('HMRC_P6P9_EMAIL_USERNAME'),
        'password' => env('HMRC_P6P9_EMAIL_PASSWORD'),
        'folder' => env('HMRC_P6P9_EMAIL_FOLDER', 'INBOX'),
        'ssl' => env('HMRC_P6P9_EMAIL_SSL', true),
        
        // Fetch method: 'unread', 'today', or 'since'
        'fetch_method' => env('HMRC_P6P9_EMAIL_FETCH', 'unread'),
        
        // Date to fetch since (if fetch_method = 'since')
        // Format: 'd-M-Y' (e.g., '01-Jan-2025')
        'fetch_since' => env('HMRC_P6P9_EMAIL_SINCE', null),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for checking P6/P9 via HMRC API
    |
    */
    
    'api' => [
        'enabled' => env('HMRC_P6P9_API_ENABLED', false),
        
        // List of employee NINOs to check
        'employees' => env('HMRC_P6P9_EMPLOYEES') ? 
                       explode(',', env('HMRC_P6P9_EMPLOYEES')) : [],
        
        // Use database to fetch employees
        'use_database' => env('HMRC_P6P9_USE_DB', false),
        'employee_model' => env('HMRC_P6P9_MODEL', 'App\\Models\\Employee'),
        
        // Use CSV file for employee list
        'csv_file' => env('HMRC_P6P9_CSV', null),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure when the P6/P9 check job runs
    |
    */
    
    'schedule' => [
        // Cron expression (default: daily at 6 AM)
        'cron' => env('HMRC_P6P9_SCHEDULE', '0 6 * * *'),
        
        // Alternative: Use Laravel schedule methods
        // Options: 'daily', 'twiceDaily', 'hourly', 'everyFourHours'
        'frequency' => env('HMRC_P6P9_FREQUENCY', 'daily'),
        
        // Time for daily/twiceDaily (24-hour format)
        'time' => env('HMRC_P6P9_TIME', '06:00'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure email notifications for tax code changes
    |
    */
    
    'notifications' => [
        'enabled' => env('HMRC_P6P9_NOTIFY', true),
        
        // Email addresses to notify
        'recipients' => env('HMRC_P6P9_NOTIFY_TO') ? 
                        explode(',', env('HMRC_P6P9_NOTIFY_TO')) : [],
        
        // Send notification even if no changes found
        'send_on_no_changes' => env('HMRC_P6P9_NOTIFY_ALWAYS', false),
        
        // Send notification on job failure
        'send_on_failure' => env('HMRC_P6P9_NOTIFY_FAILURE', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configure CSV export of P6/P9 notices
    |
    */
    
    'export' => [
        'enabled' => env('HMRC_P6P9_EXPORT', true),
        
        // Path to export CSV files
        'path' => env('HMRC_P6P9_EXPORT_PATH', storage_path('app/hmrc/p6p9')),
        
        // Keep exports for X days
        'retention_days' => env('HMRC_P6P9_EXPORT_RETENTION', 90),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for P6/P9 notices
    |
    */
    
    'cache' => [
        // Cache driver (default: use app default)
        'driver' => env('HMRC_P6P9_CACHE_DRIVER', null),
        
        // Cache TTL in seconds (default: 7 days)
        'ttl' => env('HMRC_P6P9_CACHE_TTL', 604800),
        
        // Cache key prefix
        'prefix' => 'hmrc_p6p9_',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for P6/P9 monitoring
    |
    */
    
    'log_channel' => env('HMRC_P6P9_LOG_CHANNEL', 'daily'),
    
    'log_level' => env('HMRC_P6P9_LOG_LEVEL', 'info'),
    
];
