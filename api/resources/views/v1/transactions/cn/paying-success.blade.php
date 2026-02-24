<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>支付成功</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f5f5f5;}.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px;width:90%}.check{font-size:48px;color:#52c41a;margin-bottom:10px}h2{color:#333;margin-bottom:10px}p{color:#999;font-size:14px}</style></head>
<body><div class="card"><div class="check">✓</div><h2>支付成功</h2><p>订单号：{{ $transaction->system_order_number }}</p></div></body></html>
