<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — Smart Society</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            margin: 0;
            padding: 40px 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }
        .card {
            max-width: 480px;
            width: 100%;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }
        .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            font-size: 32px;
        }
        .icon-success { background-color: #d1fae5; color: #059669; }
        .icon-declined { background-color: #fee2e2; color: #dc2626; }
        .icon-neutral { background-color: #e0e7ff; color: #4f46e5; }
        h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 12px 0;
            color: #0f172a;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #475569;
            margin: 0 0 24px 0;
        }
        .footer-note {
            font-size: 13px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        @if(($isApproved ?? false))
            <div class="icon-circle icon-success">&#10003;</div>
        @elseif($status === 'REJECTED')
            <div class="icon-circle icon-declined">&#10005;</div>
        @else
            <div class="icon-circle icon-neutral">&#9432;</div>
        @endif

        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>

        <div class="footer-note">
            Smart Society Management & Gate Security System
        </div>
    </div>
</body>
</html>