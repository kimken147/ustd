<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API 对接文档</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* ── Login Page ── */
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f6f8fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
        }
        .login-box {
            background: #fff;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            padding: 40px;
            width: 360px;
            text-align: center;
        }
        .login-box h1 {
            font-size: 24px;
            margin-bottom: 24px;
            color: #24292f;
        }
        .login-box input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            margin-bottom: 16px;
            outline: none;
        }
        .login-box input[type="password"]:focus {
            border-color: #0969da;
            box-shadow: 0 0 0 3px rgba(9,105,218,.3);
        }
        .login-box button {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: #2da44e;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .login-box button:hover { background: #298e46; }
        .login-error {
            color: #cf222e;
            font-size: 13px;
            margin-bottom: 12px;
        }

        /* ── Markdown Document ── */
        body.doc-body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #24292f;
            background: #fff;
        }

        /* Sidebar TOC */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: 280px;
            height: 100vh;
            overflow-y: auto;
            background: #f6f8fa;
            border-right: 1px solid #d0d7de;
            padding: 20px 0;
            z-index: 10;
        }
        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            padding: 0 16px 12px;
            color: #24292f;
            border-bottom: 1px solid #d0d7de;
            margin-bottom: 8px;
        }
        .sidebar a {
            display: block;
            padding: 5px 16px;
            font-size: 13px;
            color: #57606a;
            text-decoration: none;
            line-height: 1.5;
        }
        .sidebar a:hover {
            color: #0969da;
            background: #eaeef2;
        }
        .sidebar a.l2 { padding-left: 28px; font-size: 12px; }

        /* Main content */
        .md-body {
            margin-left: 280px;
            max-width: 900px;
            padding: 32px 40px 80px;
        }

        /* Markdown-like headings */
        .md-body h1 {
            font-size: 2em;
            font-weight: 600;
            padding-bottom: 0.3em;
            border-bottom: 1px solid #d0d7de;
            margin: 0 0 16px;
        }
        .md-body h2 {
            font-size: 1.5em;
            font-weight: 600;
            padding-bottom: 0.3em;
            border-bottom: 1px solid #d0d7de;
            margin: 32px 0 16px;
        }
        .md-body h3 {
            font-size: 1.25em;
            font-weight: 600;
            margin: 24px 0 12px;
        }
        .md-body h4 {
            font-size: 1em;
            font-weight: 600;
            margin: 20px 0 8px;
        }

        .md-body p {
            margin: 0 0 16px;
        }

        .md-body ul, .md-body ol {
            padding-left: 2em;
            margin: 0 0 16px;
        }
        .md-body li { margin: 4px 0; }

        /* Inline code */
        .md-body code {
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 85%;
            background: rgba(175,184,193,0.2);
            padding: 0.2em 0.4em;
            border-radius: 6px;
        }

        /* Code blocks */
        .md-body pre {
            background: #f6f8fa;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            padding: 16px;
            overflow-x: auto;
            margin: 0 0 16px;
            line-height: 1.45;
        }
        .md-body pre code {
            background: none;
            padding: 0;
            font-size: 85%;
        }

        /* Tables */
        .md-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 16px;
            font-size: 14px;
        }
        .md-body th, .md-body td {
            border: 1px solid #d0d7de;
            padding: 8px 12px;
            text-align: left;
        }
        .md-body th {
            background: #f6f8fa;
            font-weight: 600;
        }
        .md-body tr:nth-child(even) td {
            background: #f6f8fa;
        }

        /* Blockquote (tip/warning) */
        .md-body blockquote {
            border-left: 4px solid #d0d7de;
            padding: 8px 16px;
            margin: 0 0 16px;
            color: #57606a;
            background: #f6f8fa;
        }
        .md-body blockquote.tip {
            border-left-color: #2da44e;
            background: #dafbe1;
            color: #1a7f37;
        }
        .md-body blockquote.warning {
            border-left-color: #bf8700;
            background: #fff8c5;
            color: #6f5600;
        }
        .md-body blockquote.danger {
            border-left-color: #cf222e;
            background: #ffebe9;
            color: #82071e;
        }

        /* Badge */
        .badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            color: #fff;
            vertical-align: middle;
            margin-right: 4px;
        }
        .badge-post { background: #2da44e; }
        .badge-get { background: #0969da; }
        .badge-required { background: #cf222e; font-size: 11px; }
        .badge-optional { background: #8b949e; font-size: 11px; }

        .endpoint-path {
            font-family: "SFMono-Regular", Consolas, monospace;
            font-size: 15px;
            font-weight: 600;
            color: #24292f;
            background: #f6f8fa;
            padding: 2px 6px;
            border-radius: 4px;
        }

        hr {
            border: none;
            border-top: 1px solid #d0d7de;
            margin: 32px 0;
        }

        /* Language switcher */
        .lang-switcher {
            display: flex;
            gap: 6px;
            padding: 12px 16px;
            border-bottom: 1px solid #d0d7de;
            margin-bottom: 8px;
        }
        .lang-btn {
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid #d0d7de;
            border-radius: 12px;
            background: #fff;
            color: #57606a;
            cursor: pointer;
            transition: all .15s;
        }
        .lang-btn:hover { border-color: #0969da; color: #0969da; }
        .lang-btn.active {
            background: #0969da;
            color: #fff;
            border-color: #0969da;
        }
        .login-lang-switcher {
            position: fixed;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 6px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .md-body { margin-left: 0; padding: 20px 16px 60px; }
        }
    </style>
</head>
<body class="{{ $verified ? 'doc-body' : '' }}">

@if(!$verified)
{{-- ── Password Form ── --}}
<div class="login-wrap">
    <div class="login-lang-switcher">
        <button class="lang-btn" onclick="switchLang('zh')">中文</button>
        <button class="lang-btn" onclick="switchLang('en')">English</button>
    </div>
    <form class="login-box" method="POST" action="">
        @csrf
        <h1 data-i18n="title">API 对接文档</h1>
        <p style="color:#57606a;font-size:14px;margin-bottom:20px;" data-i18n="loginPrompt">请输入访问密码</p>
        @if($errors->has('password'))
            <div class="login-error" data-i18n="loginError">{{ $errors->first('password') }}</div>
        @endif
        <input type="password" name="password" data-i18n-placeholder="loginPlaceholder" placeholder="请输入密码" autofocus>
        <button type="submit" data-i18n="loginBtn">确 认</button>
    </form>
</div>

@else
{{-- ── Sidebar TOC ── --}}
<nav class="sidebar">
    <div class="lang-switcher">
        <button class="lang-btn" onclick="switchLang('zh')">中文</button>
        <button class="lang-btn" onclick="switchLang('en')">English</button>
    </div>
    <div class="sidebar-title" data-i18n="title">API 对接文档</div>
    <a href="#overview" data-i18n="navOverview">概述</a>
    <a href="#sign-rule" class="l2" data-i18n="navSignRule">签名规则</a>
    <a href="#request-format" class="l2" data-i18n="navRequestFormat">请求格式</a>
    <a href="#ip-whitelist" class="l2" data-i18n="navIpWhitelist">IP 白名单</a>
    <a href="#deposit-create" data-i18n="navDepositCreate">1. 入金下单接口</a>
    <a href="#deposit-query" data-i18n="navDepositQuery">2. 入金查询接口</a>
    <a href="#withdraw-create" data-i18n="navWithdrawCreate">3. 代付下单接口</a>
    <a href="#withdraw-query" data-i18n="navWithdrawQuery">4. 代付查询接口</a>
    <a href="#profile-query" data-i18n="navProfileQuery">5. 商户资料查询</a>
    <a href="#batch-query" data-i18n="navBatchQuery">6. 批量查询订单</a>
    <a href="#callback" data-i18n="navCallback">7. 异步回调通知</a>
    <a href="#sign-detail" data-i18n="navSignDetail">8. 签名规则详解</a>
    <a href="#sign-verify" data-i18n="navSignVerify">9. 返回值 sign 验证</a>
    <a href="#appendix" data-i18n="navAppendix">附录</a>
    <a href="#status-codes" class="l2" data-i18n="navStatusCodes">订单状态代码</a>
    <a href="#error-codes" class="l2" data-i18n="navErrorCodes">错误代码</a>
    <a href="#bank-list" class="l2" data-i18n="navBankList">链网络列表</a>
</nav>

{{-- ── Document Content ── --}}
<main class="md-body">

<h1 id="overview" data-i18n="title">API 对接文档</h1>

<p data-i18n="overviewDesc">本文档描述商户与平台对接所需的全部接口，包含入金（代收）、代付、查询、回调等功能。</p>

<p data-i18n-html="overviewBaseUrl"><strong>Base URL</strong>：由平台提供，以下接口路径均为相对路径。</p>

<h3 id="sign-rule" data-i18n="signRule">签名规则</h3>

<p data-i18n-html="signRuleDesc">所有请求均需携带 <code>sign</code> 参数。签名规则详见 <a href="#sign-detail">第 8 节</a>。</p>

<h3 id="request-format" data-i18n="requestFormat">请求格式</h3>

<ul>
    <li data-i18n-html="reqMethod">请求方式：<code>POST</code></li>
    <li data-i18n-html="reqContentType">Content-Type：<code>application/json</code> 或 <code>application/x-www-form-urlencoded</code></li>
    <li data-i18n-html="reqSign">所有接口均需传入 <code>sign</code> 签名参数</li>
    <li data-i18n-html="reqResponse">返回格式：<code>application/json</code></li>
</ul>

<h3 id="ip-whitelist" data-i18n="ipWhitelist">IP 白名单</h3>

<blockquote class="warning">
    <span data-i18n-html="ipWhitelistDesc"><strong>注意：</strong>所有 API 请求需配置 IP 白名单，请联系客服配置服务器 IP 地址。未配置白名单的请求将返回错误代码 <code>18</code>。</span>
</blockquote>

<hr>

{{-- ══════════════ 1. 入金下单 ══════════════ --}}
<h2 id="deposit-create" data-i18n="depositCreateTitle">1. 入金下单接口</h2>

<p><span class="badge badge-post">POST</span> <span class="endpoint-path">/api/v1/merchant-api/deposits</span></p>

<p data-i18n="depositCreateDesc">商户通过此接口发起入金（代收）订单。</p>

<h4 data-i18n="reqParams">请求参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thRequired">必填</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>merchant_id</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descMerchantId">商户号</td></tr>
        <tr><td><code>channel</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descChannelCode">通道代码（由平台分配）</td></tr>
        <tr><td><code>amount</code></td><td>numeric</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descDepositAmount">入金金额</td></tr>
        <tr><td><code>callback_url</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descCallbackUrl">异步回调通知地址</td></tr>
        <tr><td><code>out_trade_no</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descOutTradeNoUnique">商户订单号（唯一）</td></tr>
        <tr><td><code>client_ip</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descClientIp">客户端 IP 地址</td></tr>
        <tr><td><code>sign</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descSign">签名</td></tr>
        <tr><td><code>payer_name</code></td><td>string</td><td><span class="badge badge-optional" data-i18n="badgeOptional">选填</span></td><td data-i18n="descPayerName">付款人真实姓名（部分通道必填）</td></tr>
        <tr><td><code>redirect_url</code></td><td>string</td><td><span class="badge badge-optional" data-i18n="badgeOptional">选填</span></td><td data-i18n="descRedirectUrl">支付完成后跳转地址</td></tr>
    </tbody>
</table>

<h4 data-i18n="resParams">返回参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>message</code></td><td>string</td><td data-i18n="descMessage">返回消息</td></tr>
        <tr><td><code>data.trade_no</code></td><td>string</td><td data-i18n="descTradeNo">系统订单号</td></tr>
        <tr><td><code>data.out_trade_no</code></td><td>string</td><td data-i18n="descOutTradeNo">商户订单号</td></tr>
        <tr><td><code>data.status</code></td><td>int</td><td data-i18n-html="descOrderStatus">订单状态（见<a href="#status-codes">状态代码</a>）</td></tr>
        <tr><td><code>data.amount</code></td><td>numeric</td><td data-i18n="descOrderAmount">订单金额</td></tr>
        <tr><td><code>data.cashier_url</code></td><td>string</td><td data-i18n="descCashierUrl">收银台 URL（引导用户跳转支付）</td></tr>
        <tr><td><code>data.pay_address</code></td><td>string</td><td data-i18n="descPayAddress">收款账号（USDT 钱包地址）</td></tr>
        <tr><td><code>data.payee_name</code></td><td>string</td><td data-i18n="descPayeeName2">收款人姓名</td></tr>
        <tr><td><code>data.note</code></td><td>string</td><td data-i18n="descNote">备注信息</td></tr>
        <tr><td><code>data.created_at</code></td><td>string</td><td data-i18n="descCreatedAt">创建时间（ISO 8601）</td></tr>
        <tr><td><code>data.sign</code></td><td>string</td><td data-i18n="descReturnSign">返回签名</td></tr>
    </tbody>
</table>

<h4 data-i18n="resExample">返回示例</h4>

<pre><code>{
    "message": "Match successful",
    "data": {
        "trade_no": "P202601010001",
        "out_trade_no": "M20260101001",
        "status": 0,
        "amount": 500.00,
        "merchant_id": "merchant001",
        "callback_url": "https://example.com/notify",
        "redirect_url": "https://example.com/return",
        "created_at": "2026-01-01T12:00:00.000000Z",
        "confirmed_at": null,
        "cashier_url": "https://pay.example.com/cashier/xxx",
        "pay_address": "TXxx...xxx",
        "payee_name": "",
        "note": "",
        "sign": "a1b2c3d4e5f6..."
    }
}</code></pre>

<hr>

{{-- ══════════════ 2. 入金查询 ══════════════ --}}
<h2 id="deposit-query" data-i18n="depositQueryTitle">2. 入金查询接口</h2>

<p><span class="badge badge-post">POST</span> <span class="endpoint-path">/api/v1/merchant-api/deposits/query</span></p>

<p data-i18n="depositQueryDesc">查询入金（代收）订单的当前状态。</p>

<h4 data-i18n="reqParams">请求参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thRequired">必填</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>merchant_id</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descMerchantId">商户号</td></tr>
        <tr><td><code>out_trade_no</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descOutTradeNo">商户订单号</td></tr>
        <tr><td><code>sign</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descSign">签名</td></tr>
    </tbody>
</table>

<h4 data-i18n="resParams">返回参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>message</code></td><td>string</td><td data-i18n="descMessage">返回消息</td></tr>
        <tr><td><code>data.trade_no</code></td><td>string</td><td data-i18n="descTradeNo">系统订单号</td></tr>
        <tr><td><code>data.out_trade_no</code></td><td>string</td><td data-i18n="descOutTradeNo">商户订单号</td></tr>
        <tr><td><code>data.status</code></td><td>int</td><td data-i18n="descOrderStatusShort">订单状态</td></tr>
        <tr><td><code>data.amount</code></td><td>numeric</td><td data-i18n="descOrderAmount">订单金额</td></tr>
        <tr><td><code>data.merchant_id</code></td><td>string</td><td data-i18n="descMerchantId">商户号</td></tr>
        <tr><td><code>data.created_at</code></td><td>string</td><td data-i18n="descCreatedAtShort">创建时间</td></tr>
        <tr><td><code>data.confirmed_at</code></td><td>string</td><td data-i18n="descConfirmedAt">完成时间（未完成为 null）</td></tr>
        <tr><td><code>data.sign</code></td><td>string</td><td data-i18n="descReturnSign">返回签名</td></tr>
    </tbody>
</table>

<h4 data-i18n="resExample">返回示例</h4>

<pre><code>{
    "message": "Query successful",
    "data": {
        "trade_no": "P202601010001",
        "out_trade_no": "M20260101001",
        "status": 1,
        "amount": 500.00,
        "merchant_id": "merchant001",
        "callback_url": "https://example.com/notify",
        "redirect_url": "https://example.com/return",
        "created_at": "2026-01-01T12:00:00.000000Z",
        "confirmed_at": "2026-01-01T12:05:00.000000Z",
        "sign": "a1b2c3d4e5f6..."
    }
}</code></pre>

<hr>

{{-- ══════════════ 3. 代付下单 ══════════════ --}}
<h2 id="withdraw-create" data-i18n="withdrawCreateTitle">3. 代付下单接口</h2>

<p><span class="badge badge-post">POST</span> <span class="endpoint-path">/api/v1/merchant-api/payouts</span></p>

<p data-i18n="withdrawCreateDesc">商户通过此接口发起代付（提款）请求。</p>

<blockquote class="danger">
    <span data-i18n-html="withdrawDangerNote"><strong>重要：</strong>代付请求提交后，如请求超时或返回异常，<strong>不代表订单失败</strong>。请务必通过查询接口或等待回调确认最终结果，切勿重复提交。</span>
</blockquote>

<h4 data-i18n="reqParams">请求参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thRequired">必填</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>merchant_id</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descMerchantId">商户号</td></tr>
        <tr><td><code>amount</code></td><td>numeric</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descWithdrawAmount">代付金额（≥ 1）</td></tr>
        <tr><td><code>pay_address</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descPayAddress">收款钱包地址</td></tr>
        <tr><td><code>network</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n-html="descNetwork">链网络名称（参照<a href="#bank-list">链网络列表</a>）</td></tr>
        <tr><td><code>out_trade_no</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descOutTradeNoUnique">商户订单号（唯一）</td></tr>
        <tr><td><code>sign</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descSign">签名</td></tr>
        <tr><td><code>payee_name</code></td><td>string</td><td><span class="badge badge-optional" data-i18n="badgeOptional">选填</span></td><td data-i18n="descPayeeName">持有人名称</td></tr>
        <tr><td><code>callback_url</code></td><td>string</td><td><span class="badge badge-optional" data-i18n="badgeOptional">选填</span></td><td data-i18n="descCallbackUrl">异步回调通知地址</td></tr>
    </tbody>
</table>

<h4 data-i18n="resParams">返回参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>message</code></td><td>string</td><td data-i18n="descMessage">返回消息</td></tr>
        <tr><td><code>data.trade_no</code></td><td>string</td><td data-i18n="descTradeNo">系统订单号</td></tr>
        <tr><td><code>data.out_trade_no</code></td><td>string</td><td data-i18n="descOutTradeNo">商户订单号</td></tr>
        <tr><td><code>data.status</code></td><td>int</td><td data-i18n-html="descWithdrawStatus">代付状态（见<a href="#status-codes">状态代码</a>）</td></tr>
        <tr><td><code>data.amount</code></td><td>numeric</td><td data-i18n="descWithdrawAmountShort">代付金额</td></tr>
        <tr><td><code>data.fee</code></td><td>numeric</td><td data-i18n="descFee">手续费</td></tr>
        <tr><td><code>data.payee_name</code></td><td>string</td><td data-i18n="descPayeeName">持有人名称</td></tr>
        <tr><td><code>data.network</code></td><td>string</td><td data-i18n="descNetworkShort">链网络名称</td></tr>
        <tr><td><code>data.pay_address</code></td><td>string</td><td data-i18n="descPayAddressShort">钱包地址</td></tr>
        <tr><td><code>data.created_at</code></td><td>string</td><td data-i18n="descCreatedAtShort">创建时间</td></tr>
        <tr><td><code>data.confirmed_at</code></td><td>string</td><td data-i18n="descConfirmedAtShort">完成时间</td></tr>
        <tr><td><code>data.sign</code></td><td>string</td><td data-i18n="descReturnSign">返回签名</td></tr>
    </tbody>
</table>

<h4 data-i18n="resExample">返回示例</h4>

<pre><code>{
    "message": "Submit successful",
    "data": {
        "trade_no": "W202601010001",
        "out_trade_no": "MW20260101001",
        "status": 1,
        "amount": 1000.00,
        "fee": 5.00,
        "merchant_id": "merchant001",
        "callback_url": "https://example.com/notify",
        "created_at": "2026-01-01T12:00:00.000000Z",
        "confirmed_at": null,
        "payee_name": "",
        "network": "TRC-20",
        "pay_address": "TXxx...xxx",
        "sign": "a1b2c3d4e5f6..."
    }
}</code></pre>

<hr>

{{-- ══════════════ 4. 代付查询 ══════════════ --}}
<h2 id="withdraw-query" data-i18n="withdrawQueryTitle">4. 代付查询接口</h2>

<p><span class="badge badge-post">POST</span> <span class="endpoint-path">/api/v1/merchant-api/payouts/query</span></p>

<p data-i18n="withdrawQueryDesc">查询代付（提款）订单的当前状态。</p>

<h4 data-i18n="reqParams">请求参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thRequired">必填</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>merchant_id</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descMerchantId">商户号</td></tr>
        <tr><td><code>out_trade_no</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descOutTradeNo">商户订单号</td></tr>
        <tr><td><code>sign</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descSign">签名</td></tr>
    </tbody>
</table>

<h4 data-i18n="resParams">返回参数</h4>

<p data-i18n-html="withdrawQueryResNote">返回格式与<a href="#withdraw-create">代付下单接口</a>相同。</p>

<h4 data-i18n="resExample">返回示例</h4>

<pre><code>{
    "message": "Query successful",
    "data": {
        "trade_no": "W202601010001",
        "out_trade_no": "MW20260101001",
        "status": 1,
        "amount": 1000.00,
        "fee": 5.00,
        "merchant_id": "merchant001",
        "callback_url": "https://example.com/notify",
        "created_at": "2026-01-01T12:00:00.000000Z",
        "confirmed_at": "2026-01-01T12:10:00.000000Z",
        "payee_name": "",
        "network": "TRC-20",
        "pay_address": "TXxx...xxx",
        "sign": "a1b2c3d4e5f6..."
    }
}</code></pre>

<hr>

{{-- ══════════════ 5. 商户资料查询 ══════════════ --}}
<h2 id="profile-query" data-i18n="profileQueryTitle">5. 商户资料查询接口</h2>

<p><span class="badge badge-post">POST</span> <span class="endpoint-path">/api/v1/merchant-api/balance</span></p>

<p data-i18n="profileQueryDesc">查询商户账户余额等基本信息。</p>

<h4 data-i18n="reqParams">请求参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thRequired">必填</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>merchant_id</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descMerchantId">商户号</td></tr>
        <tr><td><code>sign</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descSign">签名</td></tr>
    </tbody>
</table>

<h4 data-i18n="resParams">返回参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>message</code></td><td>string</td><td data-i18n="descMessage">返回消息</td></tr>
        <tr><td><code>data.merchant_id</code></td><td>string</td><td data-i18n="descMerchantId">商户号</td></tr>
        <tr><td><code>data.name</code></td><td>string</td><td data-i18n="descMerchantName">商户名称</td></tr>
        <tr><td><code>data.balance</code></td><td>numeric</td><td data-i18n="descBalance">总余额</td></tr>
        <tr><td><code>data.frozen_balance</code></td><td>numeric</td><td data-i18n="descFrozenBalance">冻结余额</td></tr>
        <tr><td><code>data.available_balance</code></td><td>numeric</td><td data-i18n="descAvailableBalance">可用余额</td></tr>
        <tr><td><code>data.sign</code></td><td>string</td><td data-i18n="descReturnSign">返回签名</td></tr>
    </tbody>
</table>

<h4 data-i18n="resExample">返回示例</h4>

<pre><code>{
    "message": "Query successful",
    "data": {
        "merchant_id": "merchant001",
        "name": "测试商户",
        "balance": 50000.00,
        "frozen_balance": 5000.00,
        "available_balance": 45000.00,
        "sign": "a1b2c3d4e5f6..."
    }
}</code></pre>

<hr>

{{-- ══════════════ 6. 批量查询 ══════════════ --}}
<h2 id="batch-query" data-i18n="batchQueryTitle">6. 批量查询订单接口</h2>

<p><span class="badge badge-post">POST</span> <span class="endpoint-path">/api/v1/merchant-api/transactions</span></p>

<p data-i18n="batchQueryDesc">批量查询指定时间范围内的入金订单。</p>

<blockquote>
    <span data-i18n-html="batchQueryLimit"><strong>限制：</strong>查询时间跨度不超过 1 个月，不可查询 2 个月前的数据。</span>
</blockquote>

<h4 data-i18n="reqParams">请求参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thRequired">必填</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>merchant_id</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descMerchantId">商户号</td></tr>
        <tr><td><code>page</code></td><td>int</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descPage">页码</td></tr>
        <tr><td><code>started_at</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descStartedAt">开始时间（ISO 8601）</td></tr>
        <tr><td><code>ended_at</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descEndedAt">结束时间（ISO 8601）</td></tr>
        <tr><td><code>sign</code></td><td>string</td><td><span class="badge badge-required" data-i18n="badgeRequired">必填</span></td><td data-i18n="descSign">签名</td></tr>
        <tr><td><code>per_page</code></td><td>int</td><td><span class="badge badge-optional" data-i18n="badgeOptional">选填</span></td><td data-i18n="descPerPage">每页条数（默认 20）</td></tr>
    </tbody>
</table>

<h4 data-i18n="resParams">返回参数</h4>

<pre><code>{
    "data": [
        {
            "trade_no": "P202601010001",
            "out_trade_no": "M20260101001",
            "status": 1,
            "amount": 500.00,
            "merchant_id": "merchant001",
            "created_at": "2026-01-01T12:00:00.000000Z"
        }
    ],
    "links": {
        "first": "...?page=1",
        "last": "...?page=5",
        "prev": null,
        "next": "...?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 20,
        "to": 20,
        "total": 100
    }
}</code></pre>

<hr>

{{-- ══════════════ 7. 异步回调 ══════════════ --}}
<h2 id="callback" data-i18n="callbackTitle">7. 异步回调通知</h2>

<p data-i18n-html="callbackDesc">当订单状态发生变更（成功或失败）时，系统会向商户的 <code>callback_url</code> 发送 <code>POST</code> 请求。</p>

<blockquote class="warning">
    <span data-i18n-html="callbackRetry"><strong>重试机制：</strong>若商户未正确响应，系统会进行多次重试通知。商户应做好幂等处理，避免重复业务操作。</span>
</blockquote>

<h4 data-i18n="callbackParams">回调参数</h4>

<table>
    <thead><tr><th data-i18n="thParamName">参数名</th><th data-i18n="thType">类型</th><th data-i18n="thDesc">说明</th></tr></thead>
    <tbody>
        <tr><td><code>message</code></td><td>string</td><td data-i18n="descMessage">返回消息</td></tr>
        <tr><td><code>data.merchant_id</code></td><td>string</td><td data-i18n="descMerchantId">商户号</td></tr>
        <tr><td><code>data.amount</code></td><td>string</td><td data-i18n="descAmountDecimal">金额（小数点后取2位）</td></tr>
        <tr><td><code>data.out_trade_no</code></td><td>string</td><td data-i18n="descOutTradeNo">商户订单号</td></tr>
        <tr><td><code>data.trade_no</code></td><td>string</td><td data-i18n="descPlatformOrderNum">平台订单号</td></tr>
        <tr><td><code>data.status</code></td><td>int</td><td data-i18n-html="descOrderStatus">订单状态（见<a href="#status-codes">状态代码</a>）</td></tr>
        <tr><td><code>data.sign</code></td><td>string</td><td data-i18n="descSign">签名</td></tr>
    </tbody>
</table>

<h4 data-i18n="resExample">返回示例</h4>

<pre><code>{
    "message": "Notify successful",
    "data": {
        "merchant_id": "merchant001",
        "amount": "500.00",
        "out_trade_no": "NO20230101001",
        "trade_no": "A0000001",
        "status": 1,
        "sign": "c4ca4238a0b923820dcc509a6f758..."
    }
}</code></pre>

<h4 data-i18n="callbackResponseTitle">商户响应要求</h4>

<p data-i18n-html="callbackResponseDesc">收到回调后，商户需返回纯文本 <code>success</code>（不含引号），系统收到此响应后将停止重试。</p>

<pre><code>success</code></pre>

<blockquote class="tip">
    <span data-i18n-html="callbackTip"><strong>提示：</strong>商户应先验证回调中的 <code>sign</code> 签名（见<a href="#sign-verify">第 9 节</a>），确认合法后再处理业务逻辑。</span>
</blockquote>

<hr>

{{-- ══════════════ 8. 签名规则 ══════════════ --}}
<h2 id="sign-detail" data-i18n="signDetailTitle">8. 签名规则详解</h2>

<p data-i18n="signDetailDesc">所有请求参数均需签名，签名算法如下：</p>

<h4 data-i18n="signStepsTitle">签名步骤</h4>

<ol>
    <li data-i18n-html="signStep1">将所有请求参数（<strong>不包含 <code>sign</code></strong>）按参数名 ASCII 升序排列</li>
    <li data-i18n-html="signStep2">将参数拼接成 <code>key=value</code> 格式的 query string（使用 <code>&amp;</code> 连接）</li>
    <li data-i18n-html="signStep3">在末尾追加 <code>&amp;secret_key={商户密钥}</code></li>
    <li data-i18n="signStep4">对整个字符串进行 URL decode</li>
    <li data-i18n-html="signStep5">对结果进行 <code>MD5</code> 哈希，转为小写即为 <code>sign</code> 值</li>
</ol>

<blockquote>
    <span data-i18n-html="signEmptyNote"><strong>注意：</strong>值为空的参数不参与签名。</span>
</blockquote>

<h4 data-i18n="signExampleTitle">签名示例</h4>

<p data-i18n="signExampleDesc">假设请求参数如下：</p>

<pre><code>merchant_id:    merchant001
out_trade_no:   T20260101001
amount:         500
callback_url:   https://example.com/notify
channel:        USDT
client_ip:      127.0.0.1</code></pre>

<p data-i18n-html="signExampleSecret">商户密钥（secret_key）：<code>abc123</code></p>

<p data-i18n-html="signExampleStep1"><strong>第一步：按参数名排序</strong></p>

<pre><code>amount=500
callback_url=https://example.com/notify
channel=USDT
client_ip=127.0.0.1
merchant_id=merchant001
out_trade_no=T20260101001</code></pre>

<p data-i18n-html="signExampleStep2"><strong>第二步：拼接 query string 并追加 secret_key</strong></p>

<pre><code>amount=500&amp;callback_url=https%3A%2F%2Fexample.com%2Fnotify&amp;channel=USDT&amp;client_ip=127.0.0.1&amp;merchant_id=merchant001&amp;out_trade_no=T20260101001&amp;secret_key=abc123</code></pre>

<p data-i18n-html="signExampleStep3"><strong>第三步：URL decode</strong></p>

<pre><code>amount=500&amp;callback_url=https://example.com/notify&amp;channel=USDT&amp;client_ip=127.0.0.1&amp;merchant_id=merchant001&amp;out_trade_no=T20260101001&amp;secret_key=abc123</code></pre>

<p data-i18n-html="signExampleStep4"><strong>第四步：MD5 哈希</strong></p>

<pre><code>sign = md5("amount=500&amp;callback_url=https://example.com/notify&amp;channel=USDT&amp;client_ip=127.0.0.1&amp;merchant_id=merchant001&amp;out_trade_no=T20260101001&amp;secret_key=abc123")</code></pre>

<h4 data-i18n="phpExample">PHP 示例代码</h4>

<pre><code id="code-php-sign">&lt;?php
// 1. 准备请求参数（不含 sign）
$params = [
    'merchant_id'     =&gt; 'merchant001',
    'out_trade_no' =&gt; 'T20260101001',
    'amount'       =&gt; 500,
    'callback_url'   =&gt; 'https://example.com/notify',
    'channel' =&gt; 'USDT',
    'client_ip'    =&gt; '127.0.0.1',
];

// 2. 过滤空值参数
$params = array_filter($params);

// 3. 按 key 排序
ksort($params);

// 4. 拼接 query string + secret_key
$queryString = http_build_query($params) . '&amp;secret_key=' . $secretKey;

// 5. URL decode 后取 MD5
$sign = md5(urldecode($queryString));

// 6. 将 sign 加入请求
$params['sign'] = $sign;</code></pre>

<h4 data-i18n="javaExample">Java 示例代码</h4>

<pre><code id="code-java-sign">// 1. 准备参数 Map（不含 sign）
TreeMap&lt;String, String&gt; params = new TreeMap&lt;&gt;();
params.put("merchant_id", "merchant001");
params.put("out_trade_no", "T20260101001");
params.put("amount", "500");
params.put("callback_url", "https://example.com/notify");
params.put("channel", "USDT");
params.put("client_ip", "127.0.0.1");

// 2. 拼接（TreeMap 已自动排序）
StringBuilder sb = new StringBuilder();
for (Map.Entry&lt;String, String&gt; entry : params.entrySet()) {
    if (entry.getValue() != null &amp;&amp; !entry.getValue().isEmpty()) {
        if (sb.length() &gt; 0) sb.append("&amp;");
        sb.append(entry.getKey()).append("=").append(entry.getValue());
    }
}
sb.append("&amp;secret_key=").append(secretKey);

// 3. URL decode + MD5
String decoded = URLDecoder.decode(sb.toString(), "UTF-8");
String sign = DigestUtils.md5Hex(decoded);</code></pre>

<h4 data-i18n="pythonExample">Python 示例代码</h4>

<pre><code id="code-python-sign">import hashlib
from urllib.parse import urlencode, unquote

# 1. 准备参数（不含 sign）
params = {
    "merchant_id": "merchant001",
    "out_trade_no": "T20260101001",
    "amount": "500",
    "callback_url": "https://example.com/notify",
    "channel": "USDT",
    "client_ip": "127.0.0.1",
}

# 2. 过滤空值 + 排序
params = {k: v for k, v in sorted(params.items()) if v}

# 3. 拼接 + URL decode + MD5
query_string = urlencode(params) + "&amp;secret_key=" + secret_key
sign = hashlib.md5(unquote(query_string).encode()).hexdigest()</code></pre>

<hr>

{{-- ══════════════ 9. sign 验证 ══════════════ --}}
<h2 id="sign-verify" data-i18n="signVerifyTitle">9. 返回值及回调的 sign 验证</h2>

<p data-i18n-html="signVerifyDesc">平台返回的数据和回调通知中均包含 <code>sign</code> 字段，商户应验证此签名以确保数据未被篡改。</p>

<h4 data-i18n="verifyStepsTitle">验证步骤</h4>

<ol>
    <li data-i18n-html="verifyStep1">从返回的 <code>data</code> 中提取所有字段（<strong>不包含 <code>sign</code></strong>）</li>
    <li data-i18n="verifyStep2">按参数名 ASCII 升序排列</li>
    <li data-i18n-html="verifyStep3">拼接为 query string，末尾追加 <code>&amp;secret_key={商户密钥}</code></li>
    <li data-i18n="verifyStep4">URL decode 后取 MD5</li>
    <li data-i18n-html="verifyStep5">将计算结果与返回的 <code>sign</code> 比较，一致则验证通过</li>
</ol>

<h4 data-i18n="phpVerifyExample">PHP 验证示例</h4>

<pre><code id="code-php-verify">&lt;?php
// $callbackData 为回调接收到的数据
$receivedSign = $callbackData['sign'];
unset($callbackData['sign']);

// 过滤空值 + 排序
$callbackData = array_filter($callbackData);
ksort($callbackData);

// 计算签名
$expectedSign = md5(urldecode(
    http_build_query($callbackData) . '&amp;secret_key=' . $secretKey
));

// 验证
if ($expectedSign === $receivedSign) {
    // 签名验证通过，处理业务逻辑
    echo 'success';
} else {
    // 签名验证失败
    echo 'sign error';
}</code></pre>

<hr>

{{-- ══════════════ 附录 ══════════════ --}}
<h2 id="appendix" data-i18n="appendixTitle">附录</h2>

<h3 id="status-codes" data-i18n="statusCodesTitle">订单状态代码</h3>

<table>
    <thead><tr><th data-i18n="thCode">代码</th><th data-i18n="thDescription">描述</th></tr></thead>
    <tbody>
        <tr><td><code>0</code></td><td data-i18n="statusPending">待处理（处理中）</td></tr>
        <tr><td><code>1</code></td><td data-i18n="statusSuccess">成功</td></tr>
        <tr><td><code>2</code></td><td data-i18n="statusFailed">失败</td></tr>
    </tbody>
</table>

<h3 id="error-codes" data-i18n="errorCodesTitle">错误代码</h3>

<table>
    <thead><tr><th data-i18n="thErrorCode">错误码</th><th data-i18n="thDesc">说明</th><th data-i18n="thScenario">常见场景</th></tr></thead>
    <tbody>
        <tr><td><code>1</code></td><td data-i18n="err1">余额不足</td><td data-i18n="err1s">代付时商户余额不足</td></tr>
        <tr><td><code>2</code></td><td data-i18n="err2">功能未启用</td><td data-i18n="err2s">代付未启用</td></tr>
        <tr><td><code>3</code></td><td data-i18n="err3">请求参数错误</td><td data-i18n="err3s">缺少必填参数或格式不正确</td></tr>
        <tr><td><code>4</code></td><td data-i18n="err4">商户不存在</td><td data-i18n="err4s">merchant_id 错误</td></tr>
        <tr><td><code>5</code></td><td data-i18n="err5">签名错误</td><td data-i18n="err5s">sign 验证失败</td></tr>
        <tr><td><code>6</code></td><td data-i18n="err6">并发冲突</td><td data-i18n="err6s">代付重复提交</td></tr>
        <tr><td><code>7</code></td><td data-i18n="err7">订单不存在</td><td data-i18n="err7s">查询时订单号不存在</td></tr>
        <tr><td><code>8</code></td><td data-i18n="err8">订单号重复</td><td data-i18n="err8s">下单时 out_trade_no 已存在</td></tr>
        <tr><td><code>10</code></td><td data-i18n="err10">金额低于最小值</td><td data-i18n="err10s">代付金额不满足最低要求</td></tr>
        <tr><td><code>11</code></td><td data-i18n="err11">金额超过最大值</td><td data-i18n="err11s">代付金额超过上限</td></tr>
        <tr><td><code>12</code></td><td data-i18n="err12">通道代码无效</td><td data-i18n="err12s">channel 不正确</td></tr>
        <tr><td><code>13</code></td><td data-i18n="err13">通道暂时不可用</td><td data-i18n="err13s">通道维护或暂停</td></tr>
        <tr><td><code>14</code></td><td data-i18n="err14">交易功能已禁用</td><td data-i18n="err14s">商户入金功能被关闭</td></tr>
        <tr><td><code>15</code></td><td data-i18n="err15">金额无效</td><td data-i18n="err15s">金额不在通道允许范围内</td></tr>
        <tr><td><code>16</code></td><td data-i18n="err16">频率限制</td><td data-i18n="err16s">短时间内重复请求</td></tr>
        <tr><td><code>17</code></td><td data-i18n="err17">暂无可用匹配</td><td data-i18n="err17s">当前无可用支付通道</td></tr>
        <tr><td><code>18</code></td><td data-i18n="err18">IP 未在白名单</td><td data-i18n="err18s">请求 IP 未配置白名单</td></tr>
        <tr><td><code>22</code></td><td data-i18n="err22">链网络不支持</td><td data-i18n="err22s">代付链网络名称不在支持列表</td></tr>
    </tbody>
</table>

<h3 id="bank-list" data-i18n="bankListTitle">链网络列表</h3>

<p data-i18n-html="bankListDesc">代付下单时 <code>network</code> 参数请使用以下链网络名称：</p>

<table>
    <thead><tr><th data-i18n="thBankName">链网络名称</th></tr></thead>
    <tbody>
        @foreach($banks as $bankName)
        <tr><td>{{ $bankName }}</td></tr>
        @endforeach
    </tbody>
</table>

<blockquote>
    <span data-i18n="bankListNote">实际支持的链网络列表可能因配置不同而有差异，具体请咨询平台客服。</span>
</blockquote>

</main>
@endif

<script>
const T = {
zh: {
    title: 'API 对接文档',
    loginPrompt: '请输入访问密码',
    loginError: '密码错误，请重新输入',
    loginPlaceholder: '请输入密码',
    loginBtn: '确 认',
    navOverview: '概述',
    navSignRule: '签名规则',
    navRequestFormat: '请求格式',
    navIpWhitelist: 'IP 白名单',
    navDepositCreate: '1. 入金下单接口',
    navDepositQuery: '2. 入金查询接口',
    navWithdrawCreate: '3. 代付下单接口',
    navWithdrawQuery: '4. 代付查询接口',
    navProfileQuery: '5. 商户资料查询',
    navBatchQuery: '6. 批量查询订单',
    navCallback: '7. 异步回调通知',
    navSignDetail: '8. 签名规则详解',
    navSignVerify: '9. 返回值 sign 验证',
    navAppendix: '附录',
    navStatusCodes: '订单状态代码',
    navErrorCodes: '错误代码',
    navBankList: '链网络列表',
    overviewDesc: '本文档描述商户与平台对接所需的全部接口，包含入金（代收）、代付、查询、回调等功能。',
    overviewBaseUrl: '<strong>Base URL</strong>：由平台提供，以下接口路径均为相对路径。',
    signRule: '签名规则',
    signRuleDesc: '所有请求均需携带 <code>sign</code> 参数。签名规则详见 <a href="#sign-detail">第 8 节</a>。',
    requestFormat: '请求格式',
    reqMethod: '请求方式：<code>POST</code>',
    reqContentType: 'Content-Type：<code>application/json</code> 或 <code>application/x-www-form-urlencoded</code>',
    reqSign: '所有接口均需传入 <code>sign</code> 签名参数',
    reqResponse: '返回格式：<code>application/json</code>',
    ipWhitelist: 'IP 白名单',
    ipWhitelistDesc: '<strong>注意：</strong>所有 API 请求需配置 IP 白名单，请联系客服配置服务器 IP 地址。未配置白名单的请求将返回错误代码 <code>18</code>。',
    depositCreateTitle: '1. 入金下单接口',
    depositCreateDesc: '商户通过此接口发起入金（代收）订单。',
    reqParams: '请求参数',
    resParams: '返回参数',
    resExample: '返回示例',
    thParamName: '参数名',
    thType: '类型',
    thRequired: '必填',
    thDesc: '说明',
    badgeRequired: '必填',
    badgeOptional: '选填',
    descMerchantId: '商户号',
    descChannelCode: '通道代码（由平台分配）',
    descDepositAmount: '入金金额',
    descCallbackUrl: '异步回调通知地址',
    descOutTradeNoUnique: '商户订单号（唯一）',
    descClientIp: '客户端 IP 地址',
    descSign: '签名',
    descPayerName: '付款人真实姓名（部分通道必填）',
    descRedirectUrl: '支付完成后跳转地址',
    descHttpStatus: 'HTTP 状态码，<code>200</code> 表示成功',
    descMessage: '返回消息',
    descTradeNo: '系统订单号',
    descOutTradeNo: '商户订单号',
    descOrderStatus: '订单状态（见<a href="#status-codes">状态代码</a>）',
    descOrderAmount: '订单金额',
    descCashierUrl: '收银台 URL（引导用户跳转支付）',
    descPayAddress: '收款地址（USDT 钱包地址）',
    descPayeeName2: '收款人姓名',
    descNote: '备注信息',
    descCreatedAt: '创建时间（ISO 8601）',
    descReturnSign: '返回签名',
    depositQueryTitle: '2. 入金查询接口',
    depositQueryDesc: '查询入金（代收）订单的当前状态。',
    descHttpStatusShort: 'HTTP 状态码',
    descOrderStatusShort: '订单状态',
    descCreatedAtShort: '创建时间',
    descConfirmedAt: '完成时间（未完成为 null）',
    descConfirmedAtShort: '完成时间',
    withdrawCreateTitle: '3. 代付下单接口',
    withdrawCreateDesc: '商户通过此接口发起代付（提款）请求。',
    withdrawDangerNote: '<strong>重要：</strong>代付请求提交后，如请求超时或返回异常，<strong>不代表订单失败</strong>。请务必通过查询接口或等待回调确认最终结果，切勿重复提交。',
    descWithdrawAmount: '代付金额（≥ 1）',
    descPayAddress: '收款钱包地址',
    descNetwork: '链网络名称（参照<a href="#bank-list">链网络列表</a>）',
    descPayeeName: '持有人名称',
    descWithdrawStatus: '代付状态（见<a href="#status-codes">状态代码</a>）',
    descWithdrawAmountShort: '代付金额',
    descFee: '手续费',
    descNetworkShort: '链网络名称',
    descPayAddressShort: '钱包地址',
    withdrawQueryTitle: '4. 代付查询接口',
    withdrawQueryDesc: '查询代付（提款）订单的当前状态。',
    withdrawQueryResNote: '返回格式与<a href="#withdraw-create">代付下单接口</a>相同。',
    profileQueryTitle: '5. 商户资料查询接口',
    profileQueryDesc: '查询商户账户余额等基本信息。',
    descMerchantName: '商户名称',
    descBalance: '总余额',
    descFrozenBalance: '冻结余额',
    descAvailableBalance: '可用余额',
    batchQueryTitle: '6. 批量查询订单接口',
    batchQueryDesc: '批量查询指定时间范围内的入金订单。',
    batchQueryLimit: '<strong>限制：</strong>查询时间跨度不超过 1 个月，不可查询 2 个月前的数据。',
    descPage: '页码',
    descStartedAt: '开始时间（ISO 8601）',
    descEndedAt: '结束时间（ISO 8601）',
    descPerPage: '每页条数（默认 20）',
    callbackTitle: '7. 异步回调通知',
    callbackDesc: '当订单状态发生变更（成功或失败）时，系统会向商户的 <code>callback_url</code> 发送 <code>POST</code> 请求。',
    callbackRetry: '<strong>重试机制：</strong>若商户未正确响应，系统会进行多次重试通知。商户应做好幂等处理，避免重复业务操作。',
    callbackParams: '回调参数',
    descAmountDecimal: '金额（小数点后取2位）',
    descPlatformOrderNum: '平台订单号',
    callbackResponseTitle: '商户响应要求',
    callbackResponseDesc: '收到回调后，商户需返回纯文本 <code>success</code>（不含引号），系统收到此响应后将停止重试。',
    callbackTip: '<strong>提示：</strong>商户应先验证回调中的 <code>sign</code> 签名（见<a href="#sign-verify">第 9 节</a>），确认合法后再处理业务逻辑。',
    signDetailTitle: '8. 签名规则详解',
    signDetailDesc: '所有请求参数均需签名，签名算法如下：',
    signStepsTitle: '签名步骤',
    signStep1: '将所有请求参数（<strong>不包含 <code>sign</code></strong>）按参数名 ASCII 升序排列',
    signStep2: '将参数拼接成 <code>key=value</code> 格式的 query string（使用 <code>&amp;</code> 连接）',
    signStep3: '在末尾追加 <code>&amp;secret_key={商户密钥}</code>',
    signStep4: '对整个字符串进行 URL decode',
    signStep5: '对结果进行 <code>MD5</code> 哈希，转为小写即为 <code>sign</code> 值',
    signEmptyNote: '<strong>注意：</strong>值为空的参数不参与签名。',
    signExampleTitle: '签名示例',
    signExampleDesc: '假设请求参数如下：',
    signExampleSecret: '商户密钥（secret_key）：<code>abc123</code>',
    signExampleStep1: '<strong>第一步：按参数名排序</strong>',
    signExampleStep2: '<strong>第二步：拼接 query string 并追加 secret_key</strong>',
    signExampleStep3: '<strong>第三步：URL decode</strong>',
    signExampleStep4: '<strong>第四步：MD5 哈希</strong>',
    phpExample: 'PHP 示例代码',
    javaExample: 'Java 示例代码',
    pythonExample: 'Python 示例代码',
    signVerifyTitle: '9. 返回值及回调的 sign 验证',
    signVerifyDesc: '平台返回的数据和回调通知中均包含 <code>sign</code> 字段，商户应验证此签名以确保数据未被篡改。',
    verifyStepsTitle: '验证步骤',
    verifyStep1: '从返回的 <code>data</code> 中提取所有字段（<strong>不包含 <code>sign</code></strong>）',
    verifyStep2: '按参数名 ASCII 升序排列',
    verifyStep3: '拼接为 query string，末尾追加 <code>&amp;secret_key={商户密钥}</code>',
    verifyStep4: 'URL decode 后取 MD5',
    verifyStep5: '将计算结果与返回的 <code>sign</code> 比较，一致则验证通过',
    phpVerifyExample: 'PHP 验证示例',
    appendixTitle: '附录',
    statusCodesTitle: '订单状态代码',
    thCode: '代码',
    thDescription: '描述',
    statusPending: '待处理（处理中）',
    statusSuccess: '成功',
    statusFailed: '失败',
    errorCodesTitle: '错误代码',
    thErrorCode: '错误码',
    thScenario: '常见场景',
    err1: '余额不足', err1s: '代付时商户余额不足',
    err2: '功能未启用', err2s: '代付未启用',
    err3: '请求参数错误', err3s: '缺少必填参数或格式不正确',
    err4: '商户不存在', err4s: 'merchant_id 错误',
    err5: '签名错误', err5s: 'sign 验证失败',
    err6: '并发冲突', err6s: '代付重复提交',
    err7: '订单不存在', err7s: '查询时订单号不存在',
    err8: '订单号重复', err8s: '下单时 out_trade_no 已存在',
    err10: '金额低于最小值', err10s: '代付金额不满足最低要求',
    err11: '金额超过最大值', err11s: '代付金额超过上限',
    err12: '通道代码无效', err12s: 'channel 不正确',
    err13: '通道暂时不可用', err13s: '通道维护或暂停',
    err14: '交易功能已禁用', err14s: '商户入金功能被关闭',
    err15: '金额无效', err15s: '金额不在通道允许范围内',
    err16: '频率限制', err16s: '短时间内重复请求',
    err17: '暂无可用匹配', err17s: '当前无可用支付通道',
    err18: 'IP 未在白名单', err18s: '请求 IP 未配置白名单',
    err22: '链网络不支持', err22s: '代付链网络名称不在支持列表',
    bankListTitle: '链网络列表',
    bankListDesc: '代付下单时 <code>network</code> 参数请使用以下链网络名称：',
    thBankName: '链网络名称',
    bankListNote: '实际支持的链网络列表可能因配置不同而有差异，具体请咨询平台客服。'
},
en: {
    title: 'API Integration Document',
    loginPrompt: 'Please enter the access password',
    loginError: 'Incorrect password, please try again',
    loginPlaceholder: 'Enter password',
    loginBtn: 'Confirm',
    navOverview: 'Overview',
    navSignRule: 'Signature Rule',
    navRequestFormat: 'Request Format',
    navIpWhitelist: 'IP Whitelist',
    navDepositCreate: '1. Create Deposit',
    navDepositQuery: '2. Query Deposit',
    navWithdrawCreate: '3. Create Payout',
    navWithdrawQuery: '4. Query Payout',
    navProfileQuery: '5. Merchant Profile',
    navBatchQuery: '6. Batch Query',
    navCallback: '7. Async Callback',
    navSignDetail: '8. Signature Details',
    navSignVerify: '9. Verify Return Sign',
    navAppendix: 'Appendix',
    navStatusCodes: 'Order Status Codes',
    navErrorCodes: 'Error Codes',
    navBankList: 'Chain Network List',
    overviewDesc: 'This document describes all APIs required for merchant integration, including deposit, payout, query, and callback functions.',
    overviewBaseUrl: '<strong>Base URL</strong>: Provided by the platform. All API paths below are relative paths.',
    signRule: 'Signature Rule',
    signRuleDesc: 'All requests must include the <code>sign</code> parameter. See <a href="#sign-detail">Section 8</a> for signature rules.',
    requestFormat: 'Request Format',
    reqMethod: 'Method: <code>POST</code>',
    reqContentType: 'Content-Type: <code>application/json</code> or <code>application/x-www-form-urlencoded</code>',
    reqSign: 'All APIs require the <code>sign</code> parameter',
    reqResponse: 'Response format: <code>application/json</code>',
    ipWhitelist: 'IP Whitelist',
    ipWhitelistDesc: '<strong>Note:</strong> All API requests require IP whitelist configuration. Please contact support to configure your server IP. Requests from non-whitelisted IPs will return error code <code>18</code>.',
    depositCreateTitle: '1. Create Deposit',
    depositCreateDesc: 'Merchants use this API to create a deposit order.',
    reqParams: 'Request Parameters',
    resParams: 'Response Parameters',
    resExample: 'Response Example',
    thParamName: 'Parameter',
    thType: 'Type',
    thRequired: 'Required',
    thDesc: 'Description',
    badgeRequired: 'Required',
    badgeOptional: 'Optional',
    descMerchantId: 'Merchant ID',
    descChannelCode: 'Channel code (assigned by platform)',
    descDepositAmount: 'Deposit amount',
    descCallbackUrl: 'Async callback URL',
    descOutTradeNoUnique: 'Merchant order number (unique)',
    descClientIp: 'Client IP address',
    descSign: 'Signature',
    descPayerName: 'Payer\'s real name (required by some channels)',
    descRedirectUrl: 'Redirect URL after payment',
    descHttpStatus: 'HTTP status code, <code>200</code> means success',
    descMessage: 'Response message',
    descTradeNo: 'System order number',
    descOutTradeNo: 'Merchant order number',
    descOrderStatus: 'Order status (see <a href="#status-codes">Status Codes</a>)',
    descOrderAmount: 'Order amount',
    descCashierUrl: 'Cashier URL (redirect user to pay)',
    descPayAddress: 'Receiver address (USDT wallet address)',
    descPayeeName2: 'Receiver name',
    descNote: 'Note',
    descCreatedAt: 'Created time (ISO 8601)',
    descReturnSign: 'Response signature',
    depositQueryTitle: '2. Query Deposit',
    depositQueryDesc: 'Query the current status of a deposit order.',
    descHttpStatusShort: 'HTTP status code',
    descOrderStatusShort: 'Order status',
    descCreatedAtShort: 'Created time',
    descConfirmedAt: 'Completed time (null if pending)',
    descConfirmedAtShort: 'Completed time',
    withdrawCreateTitle: '3. Create Payout',
    withdrawCreateDesc: 'Merchants use this API to create a payout (withdrawal) request.',
    withdrawDangerNote: '<strong>Important:</strong> After submitting a payout request, a timeout or abnormal response <strong>does not mean the order failed</strong>. Always confirm the final result via the query API or callback. Do not resubmit.',
    descWithdrawAmount: 'Payout amount (\u2265 1)',
    descPayAddress: 'Receiver wallet address',
    descNetwork: 'Chain network name (see <a href="#bank-list">Chain Network List</a>)',
    descPayeeName: 'Holder name',
    descWithdrawStatus: 'Payout status (see <a href="#status-codes">Status Codes</a>)',
    descWithdrawAmountShort: 'Payout amount',
    descFee: 'Fee',
    descNetworkShort: 'Chain network name',
    descPayAddressShort: 'Wallet address',
    withdrawQueryTitle: '4. Query Payout',
    withdrawQueryDesc: 'Query the current status of a payout order.',
    withdrawQueryResNote: 'Response format is the same as <a href="#withdraw-create">Create Payout</a>.',
    profileQueryTitle: '5. Merchant Profile Query',
    profileQueryDesc: 'Query merchant account balance and basic information.',
    descMerchantName: 'Merchant name',
    descBalance: 'Total balance',
    descFrozenBalance: 'Frozen balance',
    descAvailableBalance: 'Available balance',
    batchQueryTitle: '6. Batch Order Query',
    batchQueryDesc: 'Batch query deposit orders within a specified time range.',
    batchQueryLimit: '<strong>Limit:</strong> Query time span must not exceed 1 month and cannot query data older than 2 months.',
    descPage: 'Page number',
    descStartedAt: 'Start time (ISO 8601)',
    descEndedAt: 'End time (ISO 8601)',
    descPerPage: 'Items per page (default 20)',
    callbackTitle: '7. Async Callback Notification',
    callbackDesc: 'When an order status changes (success or failure), the system sends a <code>POST</code> request to the merchant\'s <code>callback_url</code>.',
    callbackRetry: '<strong>Retry mechanism:</strong> If the merchant does not respond correctly, the system will retry multiple times. Merchants should implement idempotent processing to avoid duplicate operations.',
    callbackParams: 'Callback Parameters',
    descAmountDecimal: 'Amount (2 decimal places)',
    descPlatformOrderNum: 'Platform order number',
    callbackResponseTitle: 'Merchant Response Requirement',
    callbackResponseDesc: 'After receiving the callback, the merchant must return plain text <code>success</code> (without quotes). The system will stop retrying upon receiving this response.',
    callbackTip: '<strong>Tip:</strong> Merchants should first verify the <code>sign</code> in the callback (see <a href="#sign-verify">Section 9</a>) before processing business logic.',
    signDetailTitle: '8. Signature Rule Details',
    signDetailDesc: 'All request parameters require signing. The signature algorithm is as follows:',
    signStepsTitle: 'Signature Steps',
    signStep1: 'Sort all request parameters (<strong>excluding <code>sign</code></strong>) by parameter name in ASCII ascending order',
    signStep2: 'Concatenate parameters into <code>key=value</code> query string format (joined with <code>&amp;</code>)',
    signStep3: 'Append <code>&amp;secret_key={merchant_secret}</code> at the end',
    signStep4: 'URL decode the entire string',
    signStep5: 'Apply <code>MD5</code> hash to the result, convert to lowercase to get the <code>sign</code> value',
    signEmptyNote: '<strong>Note:</strong> Parameters with empty values are excluded from the signature.',
    signExampleTitle: 'Signature Example',
    signExampleDesc: 'Assuming the request parameters are as follows:',
    signExampleSecret: 'Merchant secret (secret_key): <code>abc123</code>',
    signExampleStep1: '<strong>Step 1: Sort parameters by name</strong>',
    signExampleStep2: '<strong>Step 2: Concatenate query string and append secret_key</strong>',
    signExampleStep3: '<strong>Step 3: URL decode</strong>',
    signExampleStep4: '<strong>Step 4: MD5 hash</strong>',
    phpExample: 'PHP Example',
    javaExample: 'Java Example',
    pythonExample: 'Python Example',
    signVerifyTitle: '9. Verify Return & Callback Signature',
    signVerifyDesc: 'Both platform responses and callback notifications contain a <code>sign</code> field. Merchants should verify this signature to ensure data integrity.',
    verifyStepsTitle: 'Verification Steps',
    verifyStep1: 'Extract all fields from the returned <code>data</code> (<strong>excluding <code>sign</code></strong>)',
    verifyStep2: 'Sort by parameter name in ASCII ascending order',
    verifyStep3: 'Concatenate into query string, append <code>&amp;secret_key={merchant_secret}</code>',
    verifyStep4: 'URL decode and apply MD5',
    verifyStep5: 'Compare the result with the returned <code>sign</code>; if they match, verification passes',
    phpVerifyExample: 'PHP Verification Example',
    appendixTitle: 'Appendix',
    statusCodesTitle: 'Order Status Codes',
    thCode: 'Code',
    thDescription: 'Description',
    statusPending: 'Pending (Processing)',
    statusSuccess: 'Success',
    statusFailed: 'Failed',
    errorCodesTitle: 'Error Codes',
    thErrorCode: 'Error Code',
    thScenario: 'Common Scenario',
    err1: 'Insufficient balance', err1s: 'Merchant balance insufficient for payout',
    err2: 'Feature not enabled', err2s: 'Payout not enabled',
    err3: 'Invalid parameters', err3s: 'Missing required params or incorrect format',
    err4: 'Merchant not found', err4s: 'Incorrect merchant_id',
    err5: 'Signature error', err5s: 'Sign verification failed',
    err6: 'Concurrency conflict', err6s: 'Duplicate payout submission',
    err7: 'Order not found', err7s: 'Order number does not exist',
    err8: 'Duplicate order number', err8s: 'out_trade_no already exists',
    err10: 'Amount below minimum', err10s: 'Payout amount below minimum',
    err11: 'Amount exceeds maximum', err11s: 'Payout amount exceeds limit',
    err12: 'Invalid channel code', err12s: 'Incorrect channel',
    err13: 'Channel temporarily unavailable', err13s: 'Channel maintenance or suspended',
    err14: 'Trading function disabled', err14s: 'Merchant deposit function disabled',
    err15: 'Invalid amount', err15s: 'Amount not within channel range',
    err16: 'Rate limited', err16s: 'Repeated requests in short time',
    err17: 'No available match', err17s: 'No available payment channel',
    err18: 'IP not whitelisted', err18s: 'Request IP not in whitelist',
    err22: 'Chain network not supported', err22s: 'Payout chain network name not in supported list',
    bankListTitle: 'Chain Network List',
    bankListDesc: 'Use the following chain network names for the <code>network</code> parameter when creating payouts:',
    thBankName: 'Chain Network Name',
    bankListNote: 'The actual supported chain network list may vary depending on configuration. Please contact platform support for details.'
}
};

var CODE = {
'code-php-sign': {
zh: `&lt;?php
// 1. 准备请求参数（不含 sign）
$params = [
    'merchant_id'     =&gt; 'merchant001',
    'out_trade_no' =&gt; 'T20260101001',
    'amount'       =&gt; 500,
    'callback_url'   =&gt; 'https://example.com/notify',
    'channel' =&gt; 'USDT',
    'client_ip'    =&gt; '127.0.0.1',
];

// 2. 过滤空值参数
$params = array_filter($params);

// 3. 按 key 排序
ksort($params);

// 4. 拼接 query string + secret_key
$queryString = http_build_query($params) . '&amp;secret_key=' . $secretKey;

// 5. URL decode 后取 MD5
$sign = md5(urldecode($queryString));

// 6. 将 sign 加入请求
$params['sign'] = $sign;`,
en: `&lt;?php
// 1. Prepare request parameters (excluding sign)
$params = [
    'merchant_id'     =&gt; 'merchant001',
    'out_trade_no' =&gt; 'T20260101001',
    'amount'       =&gt; 500,
    'callback_url'   =&gt; 'https://example.com/notify',
    'channel' =&gt; 'USDT',
    'client_ip'    =&gt; '127.0.0.1',
];

// 2. Filter out empty values
$params = array_filter($params);

// 3. Sort by key
ksort($params);

// 4. Build query string + append secret_key
$queryString = http_build_query($params) . '&amp;secret_key=' . $secretKey;

// 5. URL decode then MD5 hash
$sign = md5(urldecode($queryString));

// 6. Add sign to request
$params['sign'] = $sign;`
},
'code-java-sign': {
zh: `// 1. 准备参数 Map（不含 sign）
TreeMap&lt;String, String&gt; params = new TreeMap&lt;&gt;();
params.put("merchant_id", "merchant001");
params.put("out_trade_no", "T20260101001");
params.put("amount", "500");
params.put("callback_url", "https://example.com/notify");
params.put("channel", "USDT");
params.put("client_ip", "127.0.0.1");

// 2. 拼接（TreeMap 已自动排序）
StringBuilder sb = new StringBuilder();
for (Map.Entry&lt;String, String&gt; entry : params.entrySet()) {
    if (entry.getValue() != null &amp;&amp; !entry.getValue().isEmpty()) {
        if (sb.length() &gt; 0) sb.append("&amp;");
        sb.append(entry.getKey()).append("=").append(entry.getValue());
    }
}
sb.append("&amp;secret_key=").append(secretKey);

// 3. URL decode + MD5
String decoded = URLDecoder.decode(sb.toString(), "UTF-8");
String sign = DigestUtils.md5Hex(decoded);`,
en: `// 1. Prepare parameter Map (excluding sign)
TreeMap&lt;String, String&gt; params = new TreeMap&lt;&gt;();
params.put("merchant_id", "merchant001");
params.put("out_trade_no", "T20260101001");
params.put("amount", "500");
params.put("callback_url", "https://example.com/notify");
params.put("channel", "USDT");
params.put("client_ip", "127.0.0.1");

// 2. Concatenate (TreeMap is auto-sorted)
StringBuilder sb = new StringBuilder();
for (Map.Entry&lt;String, String&gt; entry : params.entrySet()) {
    if (entry.getValue() != null &amp;&amp; !entry.getValue().isEmpty()) {
        if (sb.length() &gt; 0) sb.append("&amp;");
        sb.append(entry.getKey()).append("=").append(entry.getValue());
    }
}
sb.append("&amp;secret_key=").append(secretKey);

// 3. URL decode + MD5
String decoded = URLDecoder.decode(sb.toString(), "UTF-8");
String sign = DigestUtils.md5Hex(decoded);`
},
'code-python-sign': {
zh: `import hashlib
from urllib.parse import urlencode, unquote

# 1. 准备参数（不含 sign）
params = {
    "merchant_id": "merchant001",
    "out_trade_no": "T20260101001",
    "amount": "500",
    "callback_url": "https://example.com/notify",
    "channel": "USDT",
    "client_ip": "127.0.0.1",
}

# 2. 过滤空值 + 排序
params = {k: v for k, v in sorted(params.items()) if v}

# 3. 拼接 + URL decode + MD5
query_string = urlencode(params) + "&amp;secret_key=" + secret_key
sign = hashlib.md5(unquote(query_string).encode()).hexdigest()`,
en: `import hashlib
from urllib.parse import urlencode, unquote

# 1. Prepare parameters (excluding sign)
params = {
    "merchant_id": "merchant001",
    "out_trade_no": "T20260101001",
    "amount": "500",
    "callback_url": "https://example.com/notify",
    "channel": "USDT",
    "client_ip": "127.0.0.1",
}

# 2. Filter empty values + sort
params = {k: v for k, v in sorted(params.items()) if v}

# 3. Concatenate + URL decode + MD5
query_string = urlencode(params) + "&amp;secret_key=" + secret_key
sign = hashlib.md5(unquote(query_string).encode()).hexdigest()`
},
'code-php-verify': {
zh: `&lt;?php
// $callbackData 为回调接收到的数据
$receivedSign = $callbackData['sign'];
unset($callbackData['sign']);

// 过滤空值 + 排序
$callbackData = array_filter($callbackData);
ksort($callbackData);

// 计算签名
$expectedSign = md5(urldecode(
    http_build_query($callbackData) . '&amp;secret_key=' . $secretKey
));

// 验证
if ($expectedSign === $receivedSign) {
    // 签名验证通过，处理业务逻辑
    echo 'success';
} else {
    // 签名验证失败
    echo 'sign error';
}`,
en: `&lt;?php
// $callbackData is the data received from callback
$receivedSign = $callbackData['sign'];
unset($callbackData['sign']);

// Filter empty values + sort
$callbackData = array_filter($callbackData);
ksort($callbackData);

// Calculate signature
$expectedSign = md5(urldecode(
    http_build_query($callbackData) . '&amp;secret_key=' . $secretKey
));

// Verify
if ($expectedSign === $receivedSign) {
    // Signature verified, process business logic
    echo 'success';
} else {
    // Signature verification failed
    echo 'sign error';
}`
}
};

function switchLang(lang) {
    document.querySelectorAll('[data-i18n]').forEach(function(el) {
        var key = el.getAttribute('data-i18n');
        if (T[lang] && T[lang][key]) el.textContent = T[lang][key];
    });
    document.querySelectorAll('[data-i18n-html]').forEach(function(el) {
        var key = el.getAttribute('data-i18n-html');
        if (T[lang] && T[lang][key]) el.innerHTML = T[lang][key];
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(function(el) {
        var key = el.getAttribute('data-i18n-placeholder');
        if (T[lang] && T[lang][key]) el.placeholder = T[lang][key];
    });
    Object.keys(CODE).forEach(function(id) {
        var el = document.getElementById(id);
        if (el && CODE[id][lang]) el.innerHTML = CODE[id][lang];
    });
    document.documentElement.lang = lang === 'zh' ? 'zh-CN' : lang;
    document.title = T[lang] ? T[lang].title : document.title;
    localStorage.setItem('api_doc_lang', lang);
    document.querySelectorAll('.lang-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.textContent.trim() === ({zh:'中文',en:'English'})[lang]);
    });
}

(function() {
    var defaultLang = 'zh';
    var lang = localStorage.getItem('api_doc_lang') || defaultLang;
    if (!T[lang]) lang = defaultLang;
    switchLang(lang);
})();
</script>
</body>
</html>
