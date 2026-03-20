<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USDT Payment</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0d1117;
            color: #c9d1d9;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        .container { max-width: 420px; width: 100%; }

        /* Header */
        .header {
            text-align: center;
            padding: 20px 0 16px;
        }
        .header h2 {
            font-size: 20px;
            color: #fff;
            margin-bottom: 8px;
        }
        .chain-badge {
            display: inline-block;
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* Card */
        .card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 12px;
        }

        /* Amount */
        .amount-section { text-align: center; padding: 8px 0 16px; }
        .amount {
            font-size: 36px;
            font-weight: 700;
            color: #38bdf8;
            font-variant-numeric: tabular-nums;
        }
        .amount-unit {
            font-size: 14px;
            color: #8b949e;
            margin-top: 4px;
        }

        /* QR Code */
        .qr-section {
            text-align: center;
            padding: 16px 0;
            border-top: 1px solid #30363d;
            border-bottom: 1px solid #30363d;
        }
        .qr-section svg {
            border-radius: 8px;
            display: inline-block;
        }

        /* Address */
        .address-section { margin-top: 16px; }
        .address-label {
            font-size: 12px;
            color: #8b949e;
            margin-bottom: 6px;
        }
        .address-box {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 12px;
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 13px;
            color: #c9d1d9;
            word-break: break-all;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .address-text { flex: 1; }

        /* Info rows */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #21262d;
            font-size: 13px;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #8b949e; }
        .info-value { color: #c9d1d9; text-align: right; max-width: 60%; word-break: break-all; }

        /* Buttons */
        .copy-btn {
            background: #238636;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .copy-btn:hover { background: #2ea043; }
        .copy-btn:active { background: #1a7f37; }
        .copy-btn.copied { background: #1a7f37; }

        /* Timer */
        .timer-section {
            text-align: center;
            padding: 12px 0;
        }
        .timer {
            font-size: 28px;
            font-weight: 700;
            color: #f85149;
            font-variant-numeric: tabular-nums;
        }
        .timer-label {
            font-size: 12px;
            color: #8b949e;
            margin-top: 4px;
        }

        /* tx_hash form */
        .txhash-section { margin-top: 0; }
        .txhash-label {
            font-size: 13px;
            color: #8b949e;
            margin-bottom: 8px;
        }
        .txhash-input-group {
            display: flex;
            gap: 8px;
        }
        .txhash-input {
            flex: 1;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 10px 12px;
            color: #c9d1d9;
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        .txhash-input::placeholder { color: #484f58; }
        .txhash-input:focus { border-color: #38bdf8; }
        .txhash-submit {
            background: #38bdf8;
            color: #0d1117;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .txhash-submit:hover { background: #56ccf2; }
        .txhash-submit:disabled { background: #21262d; color: #484f58; cursor: not-allowed; }
        .txhash-msg {
            font-size: 12px;
            margin-top: 6px;
            min-height: 18px;
        }
        .txhash-msg.success { color: #3fb950; }
        .txhash-msg.error { color: #f85149; }

        /* Notice */
        .notice {
            background: rgba(248, 81, 73, 0.1);
            border: 1px solid rgba(248, 81, 73, 0.3);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #f85149;
            line-height: 1.6;
        }
        .notice-title {
            font-weight: 600;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .notice ul {
            list-style: none;
            padding: 0;
        }
        .notice li {
            padding: 2px 0;
            padding-left: 16px;
            position: relative;
        }
        .notice li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #f85149;
        }

        /* Providers */
        .providers-section {
            text-align: center;
            padding: 16px 0 8px;
        }
        .providers-label {
            font-size: 12px;
            color: #484f58;
            margin-bottom: 10px;
        }
        .providers {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .provider-link {
            color: #8b949e;
            text-decoration: none;
            font-size: 12px;
            transition: color 0.2s;
        }
        .provider-link:hover { color: #38bdf8; }

        /* Note */
        .order-note {
            background: rgba(210, 153, 34, 0.1);
            border: 1px solid rgba(210, 153, 34, 0.3);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: #d29922;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>USDT Payment</h2>
            <span class="chain-badge">
                {{ strtoupper(data_get($transaction->from_channel_account, 'chain_network', 'TRC-20')) }}
            </span>
        </div>

        <!-- Amount & Timer -->
        <div class="card">
            <div class="amount-section">
                <div class="amount">{{ $transaction->floating_amount }}</div>
                <div class="amount-unit">USDT</div>
            </div>

            @if($payingLimitEnabled)
            <div class="timer-section">
                <div class="timer" id="timer">--:--</div>
                <div class="timer-label">Time Remaining</div>
            </div>
            @endif
        </div>

        <!-- QR Code & Address -->
        <div class="card">
            <div class="qr-section">
                <div id="qrcode"></div>
            </div>

            <div class="address-section">
                <div class="address-label">Payment Address</div>
                <div class="address-box">
                    <span class="address-text" id="wallet-address">{{ $transaction->fromChannelAccount->account ?? '' }}</span>
                    <button class="copy-btn" onclick="copyText('{{ $transaction->fromChannelAccount->account ?? '' }}', this)">Copy</button>
                </div>
            </div>

            <div style="margin-top: 16px;">
                <div class="info-row">
                    <span class="info-label">Order No.</span>
                    <span class="info-value">{{ $transaction->system_order_number }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Amount</span>
                    <span class="info-value" style="color: #38bdf8; font-weight: 600;">
                        {{ $transaction->floating_amount }} USDT
                        <button class="copy-btn" style="margin-left: 8px; font-size: 11px; padding: 4px 10px;" onclick="copyText('{{ $transaction->floating_amount }}', this)">Copy</button>
                    </span>
                </div>
            </div>
        </div>

        <!-- tx_hash input -->
        <div class="card txhash-section">
            <div class="txhash-label">Completed transfer? Enter tx hash to speed up confirmation:</div>
            <div class="txhash-input-group">
                <input type="text" class="txhash-input" id="txHashInput" placeholder="Enter transaction hash (tx_hash)" maxlength="100">
                <button class="txhash-submit" id="txHashSubmit" onclick="submitTxHash()">Submit</button>
            </div>
            <div class="txhash-msg" id="txHashMsg"></div>
        </div>

        <!-- Note -->
        @if($noteEnabled && $note)
        <div class="card">
            <div class="order-note">Note: {{ $note }}</div>
        </div>
        @endif

        <!-- Notice -->
        <div class="card">
            <div class="notice">
                <div class="notice-title">
                    <span>&#9888;</span> Important Notes
                </div>
                <ul>
                    <li>Please transfer the exact amount, otherwise the system cannot auto-confirm</li>
                    <li>Please confirm you are using the correct chain network</li>
                    <li>After transfer, enter tx_hash to speed up confirmation</li>
                    <li>If you have any issues, contact support with your order number</li>
                </ul>
            </div>
        </div>

        <!-- Blockchain providers -->
        <div class="providers-section">
            <div class="providers-label">Blockchain Explorers</div>
            <div class="providers">
                <a href="https://tronscan.org/" target="_blank" rel="noopener" class="provider-link">TRONSCAN</a>
                <a href="https://www.oklink.com/trx" target="_blank" rel="noopener" class="provider-link">OKLink</a>
                <a href="https://tokenview.io/" target="_blank" rel="noopener" class="provider-link">Tokenview</a>
            </div>
        </div>
    </div>

    <!-- QR Code library (qrcode-generator - reliable browser lib) -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
        // Generate QR code
        const walletAddress = '{{ $transaction->fromChannelAccount->account ?? '' }}';
        if (walletAddress) {
            var qr = qrcode(0, 'M');
            qr.addData(walletAddress);
            qr.make();
            document.getElementById('qrcode').innerHTML = qr.createSvgTag(8, 0);
            // Style the SVG
            var svg = document.querySelector('#qrcode svg');
            if (svg) {
                svg.style.borderRadius = '8px';
                svg.style.background = '#fff';
                svg.style.padding = '8px';
            }
        }

        // Copy function
        function copyText(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                btn.textContent = 'Copied';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = 'Copy';
                    btn.classList.remove('copied');
                }, 2000);
            }).catch(() => {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btn.textContent = 'Copied';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = 'Copy';
                    btn.classList.remove('copied');
                }, 2000);
            });
        }

        // Submit tx_hash
        function submitTxHash() {
            const input = document.getElementById('txHashInput');
            const btn = document.getElementById('txHashSubmit');
            const msg = document.getElementById('txHashMsg');
            const txHash = input.value.trim();

            if (!txHash) {
                msg.textContent = 'Please enter transaction hash';
                msg.className = 'txhash-msg error';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Submitting...';
            msg.textContent = '';

            fetch('/api/v1/cashier/{{ $transaction->system_order_number }}/tx-hash', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tx_hash: txHash })
            })
            .then(res => {
                if (!res.ok) throw new Error('Submission failed');
                return res.json();
            })
            .then(() => {
                msg.textContent = 'Submitted, confirmation will be accelerated';
                msg.className = 'txhash-msg success';
                input.disabled = true;
                btn.textContent = 'Submitted';
            })
            .catch(() => {
                msg.textContent = 'Submission failed, please retry';
                msg.className = 'txhash-msg error';
                btn.disabled = false;
                btn.textContent = 'Submit';
            });
        }

        // Countdown timer
        @if($payingLimitEnabled)
        let seconds = {{ $payingLimitSeconds }};
        const timerEl = document.getElementById('timer');
        function updateTimer() {
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            timerEl.textContent = `${m}:${String(s).padStart(2, '0')}`;
            if (--seconds < 0) {
                clearInterval(countdown);
                timerEl.textContent = 'Expired';
                timerEl.style.opacity = '0.5';
                setTimeout(() => window.location.reload(), 2000);
            }
        }
        updateTimer();
        const countdown = setInterval(updateTimer, 1000);
        @endif

        // Poll payment status — redirect when paid or timed out
        const pollStatus = setInterval(async () => {
            try {
                const res = await fetch('/api/v1/cashier/{{ $transaction->system_order_number }}/status');
                const data = await res.json();
                if (data.status !== 'paying') {
                    clearInterval(pollStatus);
                    window.location.reload();
                }
            } catch (e) {}
        }, 5000);
    </script>
</body>
</html>
