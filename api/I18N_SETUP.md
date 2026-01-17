# Laravel API i18n 設定指南

## 已完成項目

### 1. 語言檢測中間件
已創建 `app/Http/Middleware/SetLocale.php`，會自動檢測並設定語言。

**檢測優先順序：**
1. `X-Locale` Header（自訂 Header）
2. `Accept-Language` Header
3. `locale` Query Parameter
4. 已登入使用者的語言設定（如果 User 模型有 `language` 欄位）
5. 應用程式預設語言（`config/app.php` 中的 `locale`）

### 2. 中間件註冊
已在 `app/Http/Kernel.php` 中將 `SetLocale` 中間件註冊到 `api` 中間件群組。

### 3. 語言檔案
- 中文：`resources/lang/zh_CN/common.php`
- 英文：`resources/lang/en/common.php`

## 使用方式

### 在 Controller 中使用翻譯

```php
// 基本用法
return response()->json([
    'message' => __('common.Please check your input'),
], 400);

// 使用 abort_if
abort_if(!$condition, 400, __('common.No recipient specified'));

// 帶參數的翻譯
return response()->json([
    'message' => __('common.Please contact admin'),
], 400);

// 使用 trans() 函數（等同於 __()）
return response()->json([
    'message' => trans('common.Invalid Status'),
], 400);
```

### 客戶端如何指定語言

#### 方式 1：使用 X-Locale Header（推薦）
```javascript
fetch('/api/endpoint', {
    headers: {
        'X-Locale': 'en'  // 或 'zh_CN'
    }
})
```

#### 方式 2：使用 Accept-Language Header
```javascript
fetch('/api/endpoint', {
    headers: {
        'Accept-Language': 'zh-CN,zh;q=0.9,en;q=0.8'
    }
})
```

#### 方式 3：使用 Query Parameter
```
GET /api/endpoint?locale=en
```

## 新增翻譯訊息

### 步驟 1：在語言檔案中新增鍵值

**中文檔案** (`resources/lang/zh_CN/common.php`)：
```php
return [
    // ... 現有的鍵值
    'Your new message key' => '您的新訊息',
];
```

**英文檔案** (`resources/lang/en/common.php`)：
```php
return [
    // ... 現有的鍵值
    'Your new message key' => 'Your new message',
];
```

### 步驟 2：在 Controller 中使用
```php
abort_if(!$condition, 400, __('common.Your new message key'));
```

## 建議的檔案組織結構

可以根據功能模組建立不同的語言檔案：

```
resources/lang/
├── zh_CN/
│   ├── common.php      (通用訊息)
│   ├── auth.php        (認證相關)
│   ├── transaction.php (交易相關)
│   └── message.php     (訊息相關)
└── en/
    ├── common.php
    ├── auth.php
    ├── transaction.php
    └── message.php
```

使用時：
```php
__('auth.sign out')           // 使用 auth.php 檔案
__('transaction.created')      // 使用 transaction.php 檔案
__('common.Please check')      // 使用 common.php 檔案
```

## 注意事項

1. **鍵值命名建議**：使用 Pascal Case 或 snake_case，保持一致性
2. **避免硬編碼**：所有回應訊息都應該使用翻譯函數
3. **提供 fallback**：確保 `fallback_locale` 設定正確（已在 `config/app.php` 設為 `en`）
4. **測試不同語言**：確保所有支援的語言都有對應的翻譯

## 下一步建議

1. 逐步將現有硬編碼的訊息移到語言檔案
2. 根據功能模組組織語言檔案
3. 建立統一的 API 回應格式 Helper（可選）

