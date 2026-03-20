<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Expired</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f5f5f5;}.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px;width:90%}h2{color:#ff4d4f;margin-bottom:10px}p{color:#999;font-size:14px}</style></head>
<body><div class="card"><h2>Payment Expired</h2><p>Order No.: {{ $transaction->system_order_number }}</p><p>If you have already transferred, please contact support</p></div></body></html>
