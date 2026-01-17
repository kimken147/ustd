# common.php 重構總結

## ✅ 已完成重構

### 重構內容

1. **統一所有重複訊息** - 將在 3+ 個檔案中重複出現的硬編碼訊息統一整理到 `common.php`
2. **按分類組織** - 將訊息按功能分類，便於維護
3. **參數化訊息** - 使用 `:variable` 減少相似訊息的重複定義
4. **向後兼容** - 保留舊鍵值 `noRecord` 以確保相容性

### 新增的統一鍵值

#### 查詢結果類 (9 個)
- `No data found` - 查无资料
- `User not found` - 查无使用者 ⭐ (10+ 次重複)
- `Transaction not found` - 查無此交易
- `Third channel not found` - 查无三方通道
- 等等...

#### 驗證錯誤類 (7 個)
- `Signature error` - 签名错误 ⭐ (10+ 次重複)
- `Information is incorrect: :attribute` - 提交资料有误：:attribute ⭐ (7+ 次重複，參數化)
- `Format error` - 格式错误
- 等等...

#### 訪問控制類 (6 個)
- `IP access forbidden` - IP 禁止访问
- `Real name access forbidden` - 该实名禁止访问
- `Card holder access forbidden` - 该持卡人禁止访问 ⭐ (4+ 次重複)
- 等等...

#### 參數驗證類 (6 個)
- `Missing parameter: :attribute` - 缺少参数 :attribute ⭐ (參數化)
- `Amount below minimum: :amount` - 金额低于下限：:amount ⭐ (參數化)
- `Decimal amount not allowed` - 禁止提交小数点金额 ⭐ (4+ 次重複)
- 等等...

#### 操作狀態類 (12 個)
- `Success` - 成功
- `Match timeout, please change amount and retry` - 匹配超时，请更换金额重新发起
- `Payment timeout, please change amount and retry` - 支付超时，请更换金额重新发起
- 等等...

## 📊 統計

- **總共整理**: 約 80+ 個鍵值
- **新增統一鍵值**: 約 50+ 個
- **參數化訊息**: 約 10+ 個
- **重複訊息統一**: 約 40+ 個

## 🔑 主要改進

### 1. 參數化處理重複

**Before:**
```php
'金额低于下限：1'
'金额低于下限：100'
'金额低于下限：500'
```

**After:**
```php
'Amount below minimum: :amount' => '金额低于下限：:amount'
```

### 2. 統一查詢結果訊息

**Before:**
```php
// 分散在各處
'查无资料' (15+ 處)
'查无使用者' (10+ 處)
'查无订单' (5+ 處)
```

**After:**
```php
// common.php 統一管理
'No data found' => '查无资料'
'User not found' => '查无使用者'
'Order not found' => '查无订单'
```

### 3. 分類組織

所有訊息按以下分類組織：
- 查詢結果
- 驗證錯誤
- 參數驗證
- 訪問控制
- 操作狀態
- 通知狀態
- 重複/衝突
- 帳號/使用者
- 通道/銀行
- 系統訊息
- 操作錯誤
- 聯絡客服
- 錢包/餘額
- 交易狀態
- 時間相關
- 其他

## 📝 使用方式

### 基本使用
```php
__('common.User not found')
__('common.Signature error')
__('common.No data found')
```

### 參數化使用
```php
__('common.Missing parameter: :attribute', ['attribute' => 'username'])
__('common.Amount below minimum: :amount', ['amount' => 100])
__('common.IP not whitelisted: :ip', ['ip' => $ip])
```

### 向後兼容
```php
// 舊鍵值仍然可用
__('common.noRecord')  // 等同於 __('common.No data found')
```

## 🎯 下一步

1. ✅ 完成 common.php 重構
2. ⏳ 建立泰文語言檔案 (th/common.php)
3. ⏳ 更新 Controller 檔案，將硬編碼訊息替換為翻譯函數
4. ⏳ 清理業務檔案中的重複定義

## 📋 檔案結構

```
resources/lang/
├── zh_CN/
│   └── common.php  ✅ 已重構（80+ 鍵值）
├── en/
│   └── common.php  ✅ 已重構（80+ 鍵值）
└── th/
    └── common.php  ⏳ 待建立
```

