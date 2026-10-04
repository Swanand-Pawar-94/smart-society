<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Approval Request</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 24px 12px;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            padding: 24px;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .header p {
            margin: 6px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 24px;
        }
        .greeting {
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .info-row:first-child {
            padding-top: 0;
        }
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        .info-value {
            font-weight: 600;
            color: #0f172a;
            text-align: right;
        }
        .status-badge {
            display: inline-block;
            background-color: #fef3c7;
            color: #b45309;
            font-weight: 700;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 9999px;
        }
        .button-group {
            display: flex;
            gap: 12px;
            margin: 24px 0;
        }
        .btn {
            display: inline-block;
            flex: 1;
            text-align: center;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-approve {
            background-color: #10b981;
            color: #ffffff !important;
        }
        .btn-reject {
            background-color: #ef4444;
            color: #ffffff !important;
        }
        .action-box {
            background-color: #eef2ff;
            border-left: 4px solid #6366f1;
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            margin-top: 20px;
            font-size: 13px;
            line-height: 1.5;
            color: #3730a3;
        }
        .footer {
            background-color: #f8fafc;
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Visitor Approval Request</h1>
            <p>Smart Society Security Gate</p>
        </div>
        <div class="content">
            <div class="greeting">
                Hello <strong>{{ $residentName }}</strong>,<br><br>
                A visitor is requesting entry to your residence.
            </div>

            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Visitor:</span>
                    <span class="info-value">{{ $visitorName }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Type:</span>
                    <span class="info-value">{{ $visitorType }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Flat:</span>
                    <span class="info-value">Flat {{ $flatNumber }} - Building {{ $building }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value"><span class="status-badge">{{ $requestStatus }}</span></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Requested At:</span>
                    <span class="info-value">{{ $requestedAt }}</span>
                </div>
            </div>

            @if(!empty($approveUrl) && !empty($rejectUrl))
            <p style="font-weight: 600; font-size: 14px; margin-bottom: 8px;">Please choose an action:</p>
            <table width="100%" cellspacing="0" cellpadding="0" style="margin: 16px 0;">
                <tr>
                    <td align="center" style="padding-right: 6px;">
                        <a href="{{ $approveUrl }}" style="background-color: #10b981; color: #ffffff; display: block; padding: 12px 20px; border-radius: 8px; font-weight: 700; text-decoration: none; text-align: center; font-size: 14px;">APPROVE VISITOR</a>
                    </td>
                    <td align="center" style="padding-left: 6px;">
                        <a href="{{ $rejectUrl }}" style="background-color: #ef4444; color: #ffffff; display: block; padding: 12px 20px; border-radius: 8px; font-weight: 700; text-decoration: none; text-align: center; font-size: 14px;">REJECT VISITOR</a>
                    </td>
                </tr>
            </table>
            @endif

            <div class="action-box">
                <strong>Or use the Mobile App:</strong><br>
                Open Smart Society &rarr; Gate &rarr; Visitor Request to approve or reject directly from your phone.
            </div>
        </div>
        <div class="footer">
            Regards,<br>
            <strong>Smart Society Security Team</strong>
        </div>
    </div>
</body>
</html>