<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMRC P6/P9 Tax Code Changes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #005ea5;
            color: white;
            padding: 20px;
            border-radius: 5px 5px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background-color: #f8f8f8;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }
        .alert-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .alert-box strong {
            color: #856404;
        }
        .notice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: white;
        }
        .notice-table th {
            background-color: #005ea5;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        .notice-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        .notice-table tr:hover {
            background-color: #f5f5f5;
        }
        .changes-section {
            background-color: #fff;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 15px 0;
        }
        .changes-section h3 {
            margin-top: 0;
            color: #ff9800;
        }
        .change-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .change-item:last-child {
            border-bottom: none;
        }
        .old-value {
            color: #d32f2f;
            text-decoration: line-through;
        }
        .new-value {
            color: #388e3c;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f8f8;
            border-top: 2px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-p6 {
            background-color: #2196F3;
            color: white;
        }
        .badge-p9 {
            background-color: #9C27B0;
            color: white;
        }
        .badge-changed {
            background-color: #ff9800;
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔔 HMRC P6/P9 Tax Code Changes Detected</h1>
    </div>
    
    <div class="content">
        <div class="alert-box">
            <strong>⚠️ Action Required:</strong> 
            {{ count($notices) }} employee tax code change{{ count($notices) > 1 ? 's have' : ' has' }} been detected. 
            Please review and update your payroll system accordingly.
        </div>
        
        <p><strong>Check Date:</strong> {{ date('l, F j, Y \a\t g:i A') }}</p>
        
        <h2>Tax Code Changes Summary</h2>
        
        <table class="notice-table">
            <thead>
                <tr>
                    <th>NINO</th>
                    <th>New Tax Code</th>
                    <th>Effective Date</th>
                    <th>Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notices as $notice)
                <tr>
                    <td><strong>{{ $notice['nino'] ?? 'N/A' }}</strong></td>
                    <td>{{ $notice['taxCode'] ?? 'N/A' }}</td>
                    <td>{{ $notice['effectiveDate'] ?? 'N/A' }}</td>
                    <td>
                        <span class="badge badge-{{ strtolower($notice['noticeType'] ?? 'p6') }}">
                            {{ strtoupper($notice['noticeType'] ?? 'P6') }}
                        </span>
                    </td>
                    <td>
                        @if(!empty($notice['changes']))
                        <span class="badge badge-changed">CHANGED</span>
                        @else
                        <span class="badge" style="background-color: #4CAF50; color: white;">NEW</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <h2>Detailed Changes</h2>
        
        @foreach($notices as $notice)
            @if(!empty($notice['changes']))
            <div class="changes-section">
                <h3>{{ $notice['nino'] ?? 'Unknown NINO' }}</h3>
                
                @foreach($notice['changes'] as $field => $change)
                <div class="change-item">
                    <strong>{{ ucfirst(str_replace('_', ' ', $field)) }}:</strong><br>
                    <span class="old-value">{{ $change['old'] }}</span> 
                    → 
                    <span class="new-value">{{ $change['new'] }}</span>
                </div>
                @endforeach
            </div>
            @endif
        @endforeach
        
        @if(empty(array_filter($notices, function($n) { return !empty($n['changes']); })))
        <p><em>No changes detected from previous records. These are new P6/P9 notices.</em></p>
        @endif
        
        <h2>Next Steps</h2>
        
        <ol>
            <li><strong>Review</strong> each tax code change carefully</li>
            <li><strong>Update</strong> your payroll system with the new tax codes</li>
            <li><strong>Apply</strong> changes from the effective date shown</li>
            <li><strong>Notify</strong> affected employees of their new tax codes</li>
            <li><strong>Keep</strong> P6/P9 notices for your records</li>
        </ol>
        
        <div class="alert-box" style="background-color: #e3f2fd; border-color: #2196F3;">
            <strong>💡 Tip:</strong> 
            You can view the full P6/P9 notices in your HMRC online account or check your email for the original HMRC notifications.
        </div>
    </div>
    
    <div class="footer">
        <p><strong>About P6/P9 Notices:</strong></p>
        <ul>
            <li><strong>P6:</strong> Tax code change during the tax year</li>
            <li><strong>P9:</strong> Tax code for the new tax year</li>
        </ul>
        
        <p>
            This is an automated notification from your HMRC P6/P9 monitoring system. 
            The system checks for tax code changes daily to help you keep your payroll up to date.
        </p>
        
        <p style="margin-top: 20px; color: #999;">
            <small>
                This email was sent to you because you are configured as a recipient for HMRC P6/P9 notifications. 
                To change your notification preferences, please update your configuration settings.
            </small>
        </p>
    </div>
</body>
</html>
