<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matching</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0d1117;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #30363d;
            border-top: 3px solid #38bdf8;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        h2 { color: #c9d1d9; margin-bottom: 10px; font-size: 18px; }
        p { color: #8b949e; font-size: 14px; }
        .amount { font-size: 24px; color: #38bdf8; font-weight: bold; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <h2>Matching payment account</h2>
        <div class="amount">{{ number_format($transaction->floating_amount, 2) }} USDT</div>
        <p>Order No.: {{ $transaction->system_order_number }}</p>
        <p style="margin-top: 10px;">Please wait...</p>
    </div>
    <script>
        const orderId = '{{ $transaction->system_order_number }}';
        const checkInterval = setInterval(async () => {
            try {
                const res = await fetch(`/api/v1/cashier/${orderId}`);
                if (res.redirected) {
                    window.location.href = res.url;
                    clearInterval(checkInterval);
                } else if (res.ok) {
                    window.location.reload();
                }
            } catch (e) {}
        }, 3000);
    </script>
</body>
</html>
