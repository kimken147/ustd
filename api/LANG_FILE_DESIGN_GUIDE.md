# 語言檔案設計指南

## 📁 檔案組織原則

### 1. 按功能模組分離（已採用）
```
resources/lang/{locale}/
├── common.php          # 跨模組通用訊息
├── transaction.php     # 交易相關
├── withdraw.php        # 代付/提現相關
├── user.php            # 使用者相關
├── channel.php         # 通道相關
├── auth.php            # 認證相關
├── wallet.php          # 錢包相關
└── ...
```

### 2. 檔案職責劃分

#### `common.php` - 通用訊息
**應包含：**
- ✅ 通用的錯誤訊息（查无资料、查无使用者、查无订单）
- ✅ 通用狀態訊息（成功、失败、超时）
- ✅ 通用的操作訊息（請稍候重试、系统繁忙）
- ✅ 通用的驗證訊息（提交资料有误、签名错误）
- ✅ 通用的格式化訊息（使用參數）

**不應包含：**
- ❌ 特定業務邏輯的訊息（應放在對應的業務檔案）
- ❌ 只在單一模組使用的訊息

#### 業務檔案（transaction.php, withdraw.php, etc.）
**應包含：**
- ✅ 該模組專屬的業務邏輯訊息
- ✅ 該模組特有的狀態和操作
- ✅ 可引用 common.php 的通用訊息

---

## 🔑 鍵值命名規範

### 1. 命名規則（優先順序）

#### 規則 A: 描述性命名（推薦）
```php
// ✅ 好的命名
'User not found'                    // 清楚描述錯誤
'Amount below minimum'              // 清楚描述驗證錯誤
'Channel under maintenance'         // 清楚描述狀態
'Please contact admin to add IP'    // 清楚描述操作

// ❌ 避免的命名
'error1'                            // 不清楚
'msg1'                              // 不清楚
'error_user'                        // 不夠描述性
```

#### 規則 B: 使用動詞開頭
```php
// ✅ 操作類訊息
'Please try again later'
'Please contact admin'
'Please check your input'

// ✅ 狀態類訊息
'Match successful'
'Transaction completed'
'Channel enabled'

// ✅ 錯誤類訊息
'User not found'
'Invalid signature'
'Insufficient balance'
```

#### 規則 C: 使用參數化訊息（減少重複）
```php
// ✅ 參數化（推薦）
'Missing parameter: :attribute'
'Amount below minimum: :amount'
'IP not whitelisted: :ip'

// ❌ 避免重複定義
'Missing parameter: username'
'Missing parameter: password'
'Missing parameter: amount'
```

---

## 🔄 重複訊息處理策略

### 問題分析

#### 重複類型 1: 完全相同的訊息
```php
// 在 10+ 個檔案中都有
'查无使用者'  // User not found
'签名错误'    // Signature error
'查无订单'    // No order found
```
**解決方案：** ✅ 放入 `common.php`

#### 重複類型 2: 相似但略有差異的訊息
```php
'查无资料'   // 用於多種查詢
'查无使用者'  // 特定查詢使用者
'查无订单'    // 特定查詢订单
```
**解決方案：** ✅ 使用參數化或統一名稱
```php
// common.php
'No data found'           => '查无资料'
'User not found'          => '查无使用者'
'Order not found'         => '查无订单'
```

#### 重複類型 3: 帶動態內容的訊息
```php
'金额低于下限：1'
'金额低于下限：100'
'缺少参数 username'
'缺少参数 password'
```
**解決方案：** ✅ 使用參數化翻譯
```php
// common.php
'Amount below minimum: :amount'     => '金额低于下限：:amount'
'Missing parameter: :attribute'     => '缺少参数 :attribute'
```

---

## 📝 重構建議

### 建議的檔案結構

#### `common.php` - 通用訊息（建議組織）

```php
<?php

return [
    // ========== 查詢結果 ==========
    'No data found'                 => '查无资料',
    'User not found'                => '查无使用者',
    'Order not found'               => '查无订单',
    'Channel not found'             => '通道不存在',
    'Third channel not found'       => '查无三方通道',
    'Transaction not found'         => '查無此交易',
    
    // ========== 驗證錯誤 ==========
    'Signature error'               => '签名错误',
    'Information is incorrect'      => '提交资料有误',
    'Information is incorrect: :attribute' => '提交资料有误：:attribute',
    'Format error'                  => '格式错误',
    'IP format error'               => 'IP 格式错误',
    
    // ========== 參數驗證 ==========
    'Missing parameter: :attribute' => '缺少参数 :attribute',
    'Amount error'                  => '金额错误',
    'Amount below minimum: :amount' => '金额低于下限：:amount',
    'Amount above maximum: :amount' => '金额高于上限：:amount',
    'Decimal amount not allowed'    => '禁止提交小数点金额',
    
    // ========== 訪問控制 ==========
    'IP access forbidden'           => 'IP 禁止访问',
    'IP not whitelisted: :ip'       => 'IP 未加入白名单 :ip',
    'IP not whitelisted'            => 'IP未加白',
    'Real name access forbidden'    => '该实名禁止访问',
    'Card holder access forbidden'  => '该持卡人禁止访问',
    'Please contact admin to add IP to whitelist' => '请联系管理员加入 API 白名单',
    
    // ========== 操作狀態 ==========
    'Success'                       => '成功',
    'Failed'                        => '失败',
    'Submit successful'             => '提交成功',
    'Query successful'              => '查询成功',
    'Match successful'              => '匹配成功',
    'Match timeout'                 => '匹配超时',
    'Payment timeout'               => '支付超时',
    'Please try again later'        => '请稍候重试',
    'System is busy'                => '系统繁忙，请重试',
    
    // ========== 重複/衝突 ==========
    'Duplicate number'              => '单号重复',
    'Duplicate number: :number'     => '订单号：:number 已存在',
    'Already duplicated'            => '已重複',
    'Already exists'                => '已存在',
    'Existed'                       => '已存在',
    'Conflict! Please try again later' => '冲突，请刷新后重试！',
    
    // ========== 系統訊息 ==========
    'Login failed'                  => '登入失败',
    'Old password incorrect'        => '旧密码错误',
    'Account or password incorrect' => '帐号或密码错误',
    'Account blocked after too many failed attempts' => '登入失败次数过多将会被系统封锁',
    
    // ========== 通知狀態 ==========
    'Not notified'                  => '未通知',
    'Waiting to send'               => '等待发送',
    'Sending'                       => '发送中',
    'Success time'                  => '成功时间',
];
```

#### `transaction.php` - 交易相關

```php
<?php

return [
    // 交易特定訊息（已在 transaction.php 中）
    'Match timeout, please change amount and retry' => '匹配超时，请更换金额重新发起',
    'Payment timeout, please change amount and retry' => '支付超时，请更换金额重新发起',
    'Amount error, please change amount and retry' => '金额错误，请更换金额重新发起',
    
    // 引用 common.php 的訊息
    // 在程式碼中使用: __('common.Success')
];
```

#### `withdraw.php` - 代付相關

```php
<?php

return [
    // 代付特定訊息
    'Withdraw failed'                              => '代付失败',
    'Create withdraw failed'                       => '建立代付失败',
    'Third party withdraw failed, please try again later' => '三方代付失败，请稍候后试',
    
    // 跑分提現特定訊息
    'Please enable paufen withdraw timeout settings' => '请先开启并设定跑分提现超时设定',
    'Paufen withdraw cannot convert without timeout' => '否则跑分提现无法转为一般提现',
    'Paufen withdraw already claimed by provider'    => '该笔跑分提现码商已抢单',
    'Third party withdraw cannot be locked'          => '三方提现禁止锁定',
    
    // 引用 common.php
];
```

#### `channel.php` - 通道相關

```php
<?php

return [
    // 通道特定訊息
    'Channel under maintenance'    => '通道维护中',
    'No matching channel'          => '无对应通道',
    'Channel fee rate not set'     => '通道费率未设定',
    'Channel not enabled'          => '该通道未启用',
    'Merchant channel not configured' => '商户未配置该通道',
    
    // 銀行相關
    'Bank not supported'           => '不支援此银行',
    'Bank setting error'           => '銀行設定錯誤',
];
```

---

## 🎯 優先級建議

### Phase 1: 建立通用訊息庫（common.php）
1. ✅ 收集所有重複 3 次以上的訊息
2. ✅ 統一鍵值命名
3. ✅ 使用參數化減少重複

### Phase 2: 按模組分類
1. ✅ 將業務特定訊息移到對應檔案
2. ✅ 保留 common.php 作為共享庫

### Phase 3: 優化命名
1. ✅ 統一命名風格
2. ✅ 建立命名規範文檔
3. ✅ 重構現有不一致的鍵值

---

## 📋 實作檢查清單

### 當新增新訊息時：
- [ ] 檢查是否已有相同或相似的訊息
- [ ] 確認應該放在哪個檔案（common 或業務檔案）
- [ ] 使用描述性的鍵值名稱
- [ ] 如果訊息包含動態內容，使用參數化（`:variable`）
- [ ] 在所有語言檔案（zh_CN, en, th）中新增

### 重複訊息處理流程：
1. **識別重複** → 找出所有相同的硬編碼字串
2. **選擇位置** → 決定放在 common.php 或業務檔案
3. **統一命名** → 使用一致的鍵值名稱
4. **參數化** → 如果有多個變體，使用參數
5. **更新引用** → 在所有 Controller 中更新為 `__('key')`
6. **多語言** → 確保所有語言檔案都有對應翻譯

---

## 🔍 重複訊息對照表

| 現有重複訊息 | 建議統一鍵值 | 建議位置 |
|------------|------------|---------|
| `查无资料` (15+ 次) | `No data found` | common.php |
| `查无使用者` (10+ 次) | `User not found` | common.php |
| `签名错误` (10+ 次) | `Signature error` | common.php |
| `查无订单` (5+ 次) | `Order not found` | common.php |
| `提交资料有误` (7+ 次) | `Information is incorrect: :attribute` | common.php |
| `该持卡人禁止访问` (4+ 次) | `Card holder access forbidden` | common.php |
| `禁止提交小数点金额` (4+ 次) | `Decimal amount not allowed` | common.php |
| `金额低于下限：X` (多變體) | `Amount below minimum: :amount` | common.php |
| `缺少参数 X` (多變體) | `Missing parameter: :attribute` | common.php |
| `通道不存在` (4+ 次) | `Channel not found` | channel.php |
| `匹配超时，请更换金额重新发起` (3+ 次) | `Match timeout, please change amount and retry` | transaction.php |

---

## 💡 最佳實踐

1. **DRY 原則**：不要重複定義相同的訊息
2. **參數化優先**：使用 `:variable` 處理相似但不同的訊息
3. **命名清晰**：鍵值應該清楚表達訊息的用途
4. **模組化**：按業務邏輯分離，保持 common.php 簡潔
5. **一致性**：所有語言檔案使用相同的鍵值結構

---

## 📚 範例：完整重構流程

### Before（重複）
```php
// Controller 1
abort_if(!$user, 400, '查无使用者');

// Controller 2  
return response()->json(['message' => '查无使用者']);

// Controller 3
if (!$merchant) {
    return ['message' => '查无使用者'];
}
```

### After（統一）
```php
// common.php
'User not found' => '查无使用者',

// Controller 1, 2, 3
abort_if(!$user, 400, __('common.User not found'));
return response()->json(['message' => __('common.User not found')]);
```

### 參數化範例
```php
// Before
'金额低于下限：1'
'金额低于下限：100'
'金额低于下限：500'

// After
// common.php
'Amount below minimum: :amount' => '金额低于下限：:amount'

// Usage
__('common.Amount below minimum: :amount', ['amount' => 1])
__('common.Amount below minimum: :amount', ['amount' => 100])
```

