<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>匹配中</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 400px; width: 90%; }
        .spinner { width: 40px; height: 40px; border: 3px solid #e0e0e0; border-top: 3px solid #1890ff; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h2 { color: #333; margin-bottom: 10px; font-size: 18px; }
        p { color: #999; font-size: 14px; }
        .amount { font-size: 24px; color: #1890ff; font-weight: bold; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <h2>正在匹配收款帐号</h2>
        <div class="amount">{{ number_format($transaction->floating_amount, 2) }} USDT</div>
        <p>订单号：{{ $transaction->system_order_number }}</p>
        <p style="margin-top: 10px;">请稍候...</p>
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
