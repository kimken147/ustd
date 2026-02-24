<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USDT 付款</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; min-height: 100vh; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 440px; margin: 0 auto; }
        .header { text-align: center; padding-bottom: 16px; border-bottom: 1px solid #f0f0f0; }
        .header h2 { font-size: 18px; color: #333; }
        .chain-badge { display: inline-block; background: #e6f7ff; color: #1890ff; padding: 2px 10px; border-radius: 4px; font-size: 12px; margin-top: 6px; }
        .amount-section { text-align: center; padding: 20px 0; }
        .amount { font-size: 32px; font-weight: bold; color: #1890ff; }
        .amount-label { color: #999; font-size: 13px; margin-top: 4px; }
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
        .info-label { color: #999; font-size: 13px; }
        .info-value { color: #333; font-size: 13px; word-break: break-all; max-width: 65%; text-align: right; }
        .copy-btn { background: #1890ff; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; margin-left: 6px; }
        .copy-btn:active { background: #096dd9; }
        .address-box { background: #fafafa; border: 1px solid #e8e8e8; border-radius: 8px; padding: 12px; margin: 16px 0; word-break: break-all; font-family: monospace; font-size: 14px; text-align: center; }
        .note { background: #fffbe6; border: 1px solid #ffe58f; border-radius: 4px; padding: 10px; font-size: 13px; color: #d48806; margin-top: 16px; }
        .timer { text-align: center; color: #ff4d4f; font-size: 14px; margin-top: 12px; }
        .warn { text-align: center; color: #ff4d4f; font-size: 12px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h2>USDT 转账付款</h2>
            <span class="chain-badge">
                {{ strtoupper(data_get($transaction->from_channel_account, 'chain_network', 'TRC-20')) }}
            </span>
        </div>

        <div class="amount-section">
            <div class="amount">{{ $transaction->floating_amount }}</div>
            <div class="amount-label">USDT</div>
        </div>

        <div class="info-row">
            <span class="info-label">收款地址</span>
            <span class="info-value">
                <button class="copy-btn" onclick="copyText('{{ $transaction->fromChannelAccount->account ?? '' }}')">复制</button>
            </span>
        </div>
        <div class="address-box" id="wallet-address">
            {{ $transaction->fromChannelAccount->account ?? '' }}
        </div>

        <div class="info-row">
            <span class="info-label">订单号</span>
            <span class="info-value">{{ $transaction->system_order_number }}</span>
        </div>

        @if($note)
        <div class="note">备注：{{ $note }}</div>
        @endif

        <div class="warn">请务必转账精确金额，否则系统无法自动确认</div>

        @if($payingLimitEnabled)
        <div class="timer" id="timer"></div>
        <script>
            let seconds = {{ $payingLimitSeconds }};
            const timerEl = document.getElementById('timer');
            const countdown = setInterval(() => {
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                timerEl.textContent = `剩余支付时间: ${m}:${String(s).padStart(2, '0')}`;
                if (--seconds < 0) {
                    clearInterval(countdown);
                    timerEl.textContent = '支付超时';
                    window.location.reload();
                }
            }, 1000);
        </script>
        @endif
    </div>

    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('已复制');
            }).catch(() => {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                alert('已复制');
            });
        }
    </script>
</body>
</html>
