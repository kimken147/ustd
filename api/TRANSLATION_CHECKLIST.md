# 需要翻譯的訊息清單

## 分類索引

1. [通用錯誤訊息](#通用錯誤訊息)
2. [交易相關 (Transaction)](#交易相關-transaction)
3. [代付相關 (Withdraw)](#代付相關-withdraw)
4. [使用者相關 (User)](#使用者相關-user)
5. [通道相關 (Channel)](#通道相關-channel)
6. [銀行相關 (Bank)](#銀行相關-bank)
7. [認證相關 (Auth)](#認證相關-auth)
8. [中間件 (Middleware)](#中間件-middleware)
9. [第三方 API 回應 (ThirdParty)](#第三方-api-回應-thirdparty)

---

## 通用錯誤訊息

### common.php

#### 已在 common.php 中但需確認完整性
- ✅ `Duplicate username`
- ✅ `Agent not found`
- ✅ `Agent functionality is not enabled`
- ✅ `Username can only be alphanumeric`
- ✅ `Wallet update conflicts, please try again later`
- ✅ `Please check your input`
- ✅ `Invalid format of system order number`
- ✅ `Please contact admin`
- ✅ `Invalid qr-code`
- ✅ `Conflict! Please try again later`
- ✅ `Transaction already refunded`
- ✅ `Invalid Status`
- ✅ `noRecord`
- ✅ `Please try again later`
- ✅ `System is busy`
- ✅ `Existed`
- ✅ `Unable to send`
- ✅ `FailedToAdd`
- ✅ `Information is incorrect`
- ✅ `Match successful`
- ✅ `No order found`
- ✅ `Status cannot be retried`
- ✅ `Duplicate number`

#### 需要新增到 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `查无资料` | Admin/TransactionController.php | 64, 298, 424 | `noRecord` (已存在，需確認) |
| `查无资料` | Admin/WithdrawController.php | 74, 468 | `noRecord` (已存在，需確認) |
| `查无资料` | Provider/TransactionController.php | 52, 59 | `noRecord` (已存在，需確認) |
| `查无资料` | Provider/WithdrawController.php | 48, 55 | `noRecord` (已存在，需確認) |
| `查无资料` | Provider/WalletHistoryController.php | 29 | `noRecord` (已存在，需確認) |
| `查无资料` | Provider/NotificationController.php | 40 | `noRecord` (已存在，需確認) |
| `查无资料` | Provider/GenericSearchController.php | 56, 88, 148, 186 | `noRecord` (已存在，需確認) |
| `查无资料` | Provider/DepositController.php | 103, 110 | `noRecord` (已存在，需確認) |
| `查无资料` | ThirdParty/GetTransactionsController.php | 53 | `noRecord` (已存在，需確認) |
| `查无使用者` | Admin/WithdrawController.php | 149 | `User not found` |
| `查无使用者` | ThirdParty/CreateTransactionController.php | 222 | `User not found` |
| `查无使用者` | ThirdParty/AgencyWithdrawController.php | 114 | `User not found` |
| `查无使用者` | ThirdParty/WithdrawController.php | 101 | `User not found` |
| `查无使用者` | ThirdParty/WithdrawQueriesController.php | 43 | `User not found` |
| `查无使用者` | ThirdParty/TransactionQueriesController.php | 42 | `User not found` |
| `查无使用者` | ThirdParty/RetryTransactionController.php | 62 | `User not found` |
| `查无使用者` | ThirdParty/ProfileQueriesController.php | 49 | `User not found` |
| `查无使用者` | ThirdParty/InitTransactionController.php | 129 | `User not found` |
| `查无使用者` | ThirdParty/GetTransactionsController.php | 75 | `User not found` |
| `查无使用者` | Provider/UserController.php | 26 | `User not found` |
| `查无使用者` | CreateTransactionController.php | 191 | `User not found` |
| `查无订单` | ThirdParty/WithdrawQueriesController.php | 81 | `No order found` (已存在) |
| `查无订单` | ThirdParty/TransactionQueriesController.php | 78 | `No order found` (已存在) |
| `查无订单` | ThirdParty/RetryTransactionController.php | 99 | `No order found` (已存在) |
| `查无订单` | ThirdParty/GetTransactionsController.php | 118 | `No order found` (已存在) |
| `查无三方通道` | Admin/WithdrawController.php | 355 | `Third channel not found` |
| `查無此交易` | MayaController.php | 60, 93 | `Transaction not found` |
| `此筆交易無法付款` | MayaController.php | 136 | `Transaction cannot be paid` |

---

## 交易相關 (Transaction)

### 需要新增到 transaction.php 或 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `已重複` | Admin/TransactionController.php | 109 | `Already duplicated` |
| `已补单` | Admin/TransactionController.php | 110, 215 | `Already manually processed` |
| `交易已销单` | Admin/TransactionController.php | 133 | `Transaction already refunded` (已存在，需確認) |
| `成功` | Admin/TransactionController.php | 323, 324 | `Success` |
| `匹配超时` | Admin/TransactionController.php | 325, 493 | `Match timeout` |
| `支付超时` | Admin/TransactionController.php | 326, 494 | `Payment timeout` |
| `失败` | Admin/TransactionController.php | 327, 495 | `Failed` |
| `未通知` | Admin/TransactionController.php | 330, 498 | `Not notified` |
| `等待发送` | Admin/TransactionController.php | 330, 498 | `Waiting to send` |
| `发送中` | Admin/TransactionController.php | 330, 498 | `Sending` |
| `成功时间` | Admin/TransactionController.php | 346, 528 | `Success time` |
| `匹配成功` | ThirdParty/CreateTransactionController.php | 666 | `Match successful` (已存在) |
| `匹配成功` | ThirdParty/MatchedJsonResponse.php | 105 | `Match successful` (已存在) |
| `匹配超时，请更换金额重新发起` | ThirdParty/CreateTransactionController.php | 706, 733 | `Match timeout, please change amount and retry` |
| `匹配超时，请更换金额重新发起` | ThirdParty/RetryTransactionController.php | 111 | `Match timeout, please change amount and retry` |
| `支付超时，请更换金额重新发起` | ThirdParty/RetryTransactionController.php | 119 | `Payment timeout, please change amount and retry` |
| `订单已超时，请联络客服补单` | Provider/TransactionController.php | 222 | `Order timeout, please contact customer service` |
| `订单号：{order_id}已存在` | Merchant/AgencyWithdrawController.php | 87 | `Order number already exists: :order_id` |
| `订单号重复` | ThirdParty/InitTransactionController.php | 233 | `Duplicate number` (已存在) |

---

## 代付相關 (Withdraw)

### 需要新增到 withdraw.php 或 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `代付失败` | Merchant/WithdrawController.php | 577 | `Withdraw failed` |
| `建立代付失败` | Merchant/AgencyWithdrawController.php | 342 | `Create withdraw failed` |
| `三方代付失败，请稍候后试` | Admin/WithdrawController.php | 429 | `Third party withdraw failed, please try again later` |
| `请先开启并设定跑分提现超时设定，否则跑分提现无法转为一般提现` | Admin/WithdrawController.php | 182 | `Please enable and configure paufen withdraw timeout settings` |
| `该笔跑分提现码商已抢单，请使用「充值管理」确认订单资讯` | Admin/WithdrawController.php | 190 | `Paufen withdraw already claimed by provider` |
| `三方提现禁止锁定` | Admin/WithdrawController.php | 196 | `Third party withdraw cannot be locked` |
| `操作错误，与锁定人不符` | Admin/WithdrawController.php | 207, 223 | `Operation error, locked by different user` |
| `目前状态无法转为三方出` | Admin/WithdrawController.php | 361 | `Current status cannot convert to third party` |
| `提交成功` | ThirdParty/AgencyWithdrawController.php | 489 | `Submit successful` |
| `提交成功` | ThirdParty/WithdrawController.php | 358 | `Submit successful` |

---

## 使用者相關 (User)

### 需要新增到 user.php 或 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `商户号错误` | Admin/TransactionController.php | 480 | `Merchant number error` |
| `商户号错误` | WithdrawDemoController.php | 47 | `Merchant number error` |
| `商户号错误` | TransactionDemoController.php | 55 | `Merchant number error` |
| `商户未配置该通道` | Admin/TransactionController.php | 486 | `Merchant channel not configured` |
| `该通道未启用` | Admin/TransactionController.php | 488 | `Channel not enabled` |
| `最高权限管理员才能设定` | Admin/SubAccountController.php | 181, 256 | `Only root admin can configure` |

---

## 通道相關 (Channel)

### 需要新增到 channel.php 或 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `通道不存在` | ThirdParty/CreateTransactionController.php | 134, 162, 334 | `Channel not found` |
| `通道不存在` | CreateTransactionController.php | 952, 1074 | `Channel not found` |
| `无对应通道` | ThirdParty/CreateTransactionController.php | 198 | `No matching channel` |
| `通道维护中` | ThirdParty/CreateTransactionController.php | 207 | `Channel under maintenance` |
| `通道维护中` | ThirdParty/InitTransactionController.php | 115 | `Channel under maintenance` |
| `通道费率未设定` | Provider/UserChannelAccountController.php | 213 | `Channel fee rate not set` |

---

## 銀行相關 (Bank)

### 需要新增到 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `不支援此银行` | ThirdParty/AgencyWithdrawController.php | 183 | `Bank not supported` |
| `不支援此银行` | ThirdParty/WithdrawController.php | 164 | `Bank not supported` |
| `不支援此银行` | Merchant/AgencyWithdrawController.php | 126 | `Bank not supported` |
| `銀行設定錯誤` | Provider/UserChannelAccountController.php | 251, 634, 668 | `Bank setting error` |

---

## 認證相關 (Auth)

### 需要新增到 auth.php 或 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `登入失败` | ExchangeModeEnabled.php | 25 | `Login failed` |
| `旧密码错误` | Provider/AuthController.php | 40 | `Old password incorrect` |
| `旧密码错误` | Merchant/AuthController.php | 33 | `Old password incorrect` |
| `帐号或密码错误，登入失败次数过多将会被系统封锁，请再次确认帐号密码！` | Provider/AuthController.php | 80, 314 | `Account or password incorrect, too many failed attempts will be blocked` |
| `帐号或密码错误，登入失败次数过多将会被系统封锁，请再次确认帐号密码！` | Merchant/AuthController.php | 73 | `Account or password incorrect, too many failed attempts will be blocked` |
| `谷歌验证码错误，失败次数过多将会被系统封锁，请务必再次确认！` | Provider/AuthController.php | 106 | `Google verification code incorrect, too many failed attempts will be blocked` |

---

## 中間件 (Middleware)

### 需要新增到 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `IP 未加入白名单 :ip` | CheckWhitelistedIp.php | 38 | `IP not whitelisted: :ip` |

---

## 第三方 API 回應 (ThirdParty)

### 需要新增到 common.php 或建立 thirdparty.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `缺少参数 {attribute}` | ThirdParty/CreateTransactionController.php | 123 | `Missing parameter: :attribute` |
| `缺少参数 bank_name` | ThirdParty/CreateTransactionController.php | 150 | `Missing parameter: bank_name` |
| `缺少参数 real_name` | ThirdParty/CreateTransactionController.php | 378 | `Missing parameter: real_name` |
| `缺少参数 {attribute}` | ThirdParty/InitTransactionController.php | 95 | `Missing parameter: :attribute` |
| `缺少参数 real_name` | ThirdParty/InitTransactionController.php | 219 | `Missing parameter: real_name` |
| `IP 禁止访问` | ThirdParty/CreateTransactionController.php | 176 | `IP access forbidden` |
| `IP 禁止访问` | CreateTransactionController.php | 159 | `IP access forbidden` |
| `该实名禁止访问` | ThirdParty/CreateTransactionController.php | 189 | `Real name access forbidden` |
| `该实名禁止访问` | CreateTransactionController.php | 172 | `Real name access forbidden` |
| `该持卡人禁止访问` | ThirdParty/WithdrawController.php | 60 | `Card holder access forbidden` |
| `该持卡人禁止访问` | ThirdParty/AgencyWithdrawController.php | 71 | `Card holder access forbidden` |
| `该持卡人禁止访问` | Provider/WithdrawController.php | 218 | `Card holder access forbidden` |
| `该持卡人禁止访问` | Merchant/WithdrawController.php | 371 | `Card holder access forbidden` |
| `签名错误` | ThirdParty/CreateTransactionController.php | 251 | `Signature error` |
| `签名错误` | ThirdParty/AgencyWithdrawController.php | 128 | `Signature error` |
| `签名错误` | ThirdParty/WithdrawController.php | 115 | `Signature error` |
| `签名错误` | ThirdParty/WithdrawQueriesController.php | 57 | `Signature error` |
| `签名错误` | ThirdParty/TransactionQueriesController.php | 56 | `Signature error` |
| `签名错误` | ThirdParty/RetryTransactionController.php | 76 | `Signature error` |
| `签名错误` | ThirdParty/ProfileQueriesController.php | 63 | `Signature error` |
| `签名错误` | ThirdParty/InitTransactionController.php | 151 | `Signature error` |
| `签名错误` | ThirdParty/GetTransactionsController.php | 89 | `Signature error` |
| `签名错误` | CreateTransactionController.php | 219 | `Signature error` |
| `请联系管理员加入 API 白名单` | ThirdParty/CreateTransactionController.php | 264 | `Please contact admin to add IP to whitelist` |
| `请联系管理员加入 API 白名单` | ThirdParty/AgencyWithdrawController.php | 136 | `Please contact admin to add IP to whitelist` |
| `请联系管理员加入 API 白名单` | ThirdParty/WithdrawController.php | 123 | `Please contact admin to add IP to whitelist` |
| `请联系管理员加入 API 白名单` | ThirdParty/WithdrawQueriesController.php | 65 | `Please contact admin to add IP to whitelist` |
| `请联系管理员加入 API 白名单` | ThirdParty/TransactionQueriesController.php | 64 | `Please contact admin to add IP to whitelist` |
| `请联系管理员加入 API 白名单` | ThirdParty/RetryTransactionController.php | 84 | `Please contact admin to add IP to whitelist` |
| `请联系管理员加入 API 白名单` | ThirdParty/InitTransactionController.php | 159 | `Please contact admin to add IP to whitelist` |
| `请联系管理员加入 API 白名单` | ThirdParty/GetTransactionsController.php | 97 | `Please contact admin to add IP to whitelist` |
| `金额错误，请更换金额重新发起` | ThirdParty/CreateTransactionController.php | 318 | `Amount error, please change amount and retry` |
| `金额错误，请更换金额重新发起` | ThirdParty/InitTransactionController.php | 184 | `Amount error, please change amount and retry` |
| `金额错误` | CreateTransactionController.php | 258 | `Amount error` |
| `金额低于下限：{amount}` | ThirdParty/AgencyWithdrawController.php | 79 | `Amount below minimum: :amount` |
| `金额低于下限：{amount}` | ThirdParty/WithdrawController.php | 76 | `Amount below minimum: :amount` |
| `金额低于下限：{min_amount}` | ThirdParty/AgencyWithdrawController.php | 194 | `Amount below minimum: :min_amount` |
| `金额低于下限：{min_amount}` | ThirdParty/WithdrawController.php | 175 | `Amount below minimum: :min_amount` |
| `金额高于上限：{max_amount}` | ThirdParty/AgencyWithdrawController.php | 205 | `Amount above maximum: :max_amount` |
| `金额高于上限：{max_amount}` | ThirdParty/WithdrawController.php | 186 | `Amount above maximum: :max_amount` |
| `禁止提交小数点金额` | ThirdParty/AgencyWithdrawController.php | 90 | `Decimal amount not allowed` |
| `禁止提交小数点金额` | ThirdParty/WithdrawController.php | 87 | `Decimal amount not allowed` |
| `禁止提交小数点金额` | Provider/WithdrawController.php | 182 | `Decimal amount not allowed` |
| `禁止提交小数点金额` | Merchant/AgencyWithdrawController.php | 107 | `Decimal amount not allowed` |
| `提交资料有误：{attribute}` | ThirdParty/AgencyWithdrawController.php | 99 | `Information is incorrect: :attribute` |
| `提交资料有误：{attribute}` | ThirdParty/WithdrawController.php | 67 | `Information is incorrect: :attribute` |
| `提交资料有误：{attribute}` | ThirdParty/WithdrawQueriesController.php | 28 | `Information is incorrect: :attribute` |
| `提交资料有误：{attribute}` | ThirdParty/TransactionQueriesController.php | 27 | `Information is incorrect: :attribute` |
| `提交资料有误：{attribute}` | ThirdParty/RetryTransactionController.php | 47 | `Information is incorrect: :attribute` |
| `提交资料有误：{attribute}` | ThirdParty/ProfileQueriesController.php | 34 | `Information is incorrect: :attribute` |
| `提交资料有误：{attribute}` | ThirdParty/GetTransactionsController.php | 34 | `Information is incorrect: :attribute` |
| `单号重复` | ThirdParty/AgencyWithdrawController.php | 151 | `Duplicate number` (已存在) |
| `单号重复` | ThirdParty/WithdrawController.php | 137 | `Duplicate number` (已存在) |
| `查询成功` | ThirdParty/UsdtController.php | 23 | `Query successful` |
| `查询成功` | ThirdParty/WithdrawQueriesController.php | 88 | `Query successful` |
| `查询成功` | ThirdParty/TransactionQueriesController.php | 85 | `Query successful` |
| `查询成功` | ThirdParty/ProfileQueriesController.php | 70 | `Query successful` |
| `余额超过设定上限，请先下发。` | ThirdParty/CreateTransactionController.php | 290 | `Balance exceeds limit, please withdraw first` |
| `请重新发起金额大于 {amount} 的订单` | ThirdParty/InitTransactionController.php | 199 | `Please retry with amount greater than :amount` |
| `请重新发起金额小于 {amount} 的订单` | ThirdParty/InitTransactionController.php | 211 | `Please retry with amount less than :amount` |
| `请勿频繁发起交易，请稍候再重新发起` | ThirdParty/CreateTransactionController.php | 789 | `Please do not submit transactions too frequently` |
| `请勿频繁发起交易，请稍候再重新发起` | ThirdParty/InitTransactionController.php | 325, 343, 352 | `Please do not submit transactions too frequently` |
| `请稍候重试` | ThirdParty/RetryTransactionController.php | 180 | `Please try again later` (已存在) |
| `请稍候重试` | ThirdParty/InitTransactionController.php | 291 | `Please try again later` (已存在) |
| `时间区间最多一次筛选一个月，请重新调整时间` | ThirdParty/GetTransactionsController.php | 61 | `Time range cannot exceed one month` |
| `IP未加白` | ThirdParty/CreateTransactionController.php | 975 | `IP not whitelisted` |

---

## Provider 相關

### 需要新增到 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `禁止转点给自己` | Provider/BalanceTransferController.php | 45 | `Cannot transfer to yourself` |
| `请先移除底下收付款账号，再尝试删除` | Admin/ProviderController.php | 607 | `Please remove all payment accounts before deleting` |
| `档案名称错误` | Provider/TransactionController.php | 375, 392 | `File name error` |
| `档案名称错误` | Provider/DepositController.php | 415, 432 | `File name error` |
| `档案名称错误` | Provider/DepositCertificateFileController.php | 25, 62 | `File name error` |

---

## Telegram Commands

### 需要新增到 common.php

| 硬編碼訊息 | 檔案位置 | 行號 | 建議鍵值 |
|-----------|---------|------|---------|
| `你是谁？` | TelegramCommands/AddLoginIpCommand.php | 21 | `Who are you?` |
| `格式错误` | TelegramCommands/AddLoginIpCommand.php | 27 | `Format error` |
| `IP 格式错误` | TelegramCommands/AddLoginIpCommand.php | 41 | `IP format error` |
| `IP 设定成功` | TelegramCommands/AddLoginIpCommand.php | 54 | `IP set successfully` |

---

## 總結統計

- **總共發現**: 約 150+ 個硬編碼訊息需要翻譯
- **已翻譯**: 約 25 個（已在 common.php）
- **待翻譯**: 約 125+ 個

## 建議優先順序

1. **高優先級** - 第三方 API 回應訊息（ThirdParty Controllers）
2. **中優先級** - Admin 和 Provider 的錯誤訊息
3. **低優先級** - Telegram Commands 和內部訊息

## 下一步行動

1. 將所有訊息鍵值新增到 `resources/lang/zh_CN/common.php`
2. 將所有訊息鍵值新增到 `resources/lang/en/common.php`
3. 將所有訊息鍵值新增到 `resources/lang/th/common.php`（新建立）
4. 逐一更新 Controller 檔案，將硬編碼訊息替換為 `__('common.Key')`

