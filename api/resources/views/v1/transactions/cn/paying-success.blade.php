@php
    $returnUrl = data_get($transaction->to_channel_account, 'return_url');
@endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Successful</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f5f5f5;}.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px;width:90%}.check{font-size:48px;color:#52c41a;margin-bottom:10px}h2{color:#333;margin-bottom:10px}p{color:#999;font-size:14px}.redirect-info{margin-top:15px;font-size:13px;color:#666}.redirect-link{color:#1890ff;text-decoration:none}.redirect-link:hover{text-decoration:underline}</style></head>
<body><div class="card"><div class="check">✓</div><h2>Payment Successful</h2><p>Order No.: {{ $transaction->system_order_number }}</p>
@if($returnUrl)
<p class="redirect-info"><span id="countdown">3</span>s to redirect... <a class="redirect-link" href="{{ $returnUrl }}">Redirect now</a></p>
@endif
</div>
@if($returnUrl)
<script>
    let sec = 3;
    const el = document.getElementById('countdown');
    const url = @json($returnUrl);
    const timer = setInterval(() => {
        if (--sec <= 0) {
            clearInterval(timer);
            window.location.href = url;
        }
        el.textContent = sec;
    }, 1000);
</script>
@endif
</body></html>
