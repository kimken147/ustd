# Admin App.tsx 重構設計

## 目標

1. 從 legacy router 遷移到 Refine v5 顯式路由
2. 移除 isPaufen 環境變數判斷（移除相關功能）
3. 移除 region 環境變數判斷（移除相關功能）
4. 使用 ThemedLayoutV2 替代自定義 Layout

## 架構變更

### 路由結構

```tsx
<BrowserRouter>
  <Refine routerProvider={routerProvider} authProvider={authProvider} ...>
    <Routes>
      <Route element={<Authenticated><ThemedLayoutV2><Outlet /></ThemedLayoutV2></Authenticated>}>
        {/* 認證後的路由 */}
      </Route>
      <Route path="/login" element={<AuthPage />} />
      <Route path="*" element={<ErrorComponent />} />
    </Routes>
  </Refine>
</BrowserRouter>
```

### AuthProvider 更新

| Legacy (v3/v4) | v5 |
|----------------|-----|
| `checkAuth` | `check` |
| `checkError` | `onError` |
| `getUserIdentity` | `getIdentity` |
| 回傳 Promise | 回傳 `{ success/authenticated, redirectTo?, error? }` |

### Resources 格式更新

| Legacy | v5 |
|--------|-----|
| `options.label` | `meta.label` |
| `options.icon` | `meta.icon` |
| `parentName` | `meta.parent` |
| `options.hide` | `meta.hide` |
| 元件作為值 | 路徑字串作為值 |

## 移除的功能

### isPaufen 相關（7 個資源）

- `providers` (ProvidersList, ProvidersCreate, ProviderShow)
- `providers/white-list` (ProviderWhiteList)
- `providers/wallet-histories` (ProviderWalletList)
- `providers/user-wallet-history` (ProviderUserWalletHistoryList)
- `deposit` (DepositList)
- `deposit/system-bank-cards` (SystemBankCardList, SystemBankCardsCreate, SystemBankCardShow)
- `deposit/matching-deposit-rewards` (DepositRewardList, DepositRewardCreate)

### region 相關（2 個資源）

- `notifications` (TransactionMessageList)
- `internal-transfers` (FundList, FundCreate)

## 保留的功能（20 個資源）

1. `home` - 首頁
2. `tags` - 標籤管理
3. `merchants` - 商戶管理
4. `merchants/white-list` - 商戶白名單
5. `merchants/api-white-list` - API 白名單
6. `merchants/banned-list` - 黑名單
7. `merchants/user-wallet-history` - 商戶錢包歷史
8. `merchants/wallet-histories` - 商戶餘額調整
9. `user-channel-accounts` - 支付帳戶管理
10. `transaction` - 交易管理（分組）
11. `transactions` - 代收
12. `withdraws` - 代付
13. `child-withdraws` - 子單拆分
14. `user-bank-cards` - 商戶銀行卡
15. `online-ready-for-matching-users` - 即時狀態
16. `statistics/v1` - 財務報表
17. `providers` - 群組管理（非 isPaufen 版本）
18. `merchant-transaction-groups` - 代收線路
19. `merchant-matching-deposit-groups` - 入款線路
20. `channels` - 通道管理
21. `thirdchannel` - 三方管理
22. `merchant-third-channel` - 三方設定
23. `feature-toggles` - 系統設定
24. `banks` - 支援銀行
25. `sub-accounts` - 權限管理
26. `login-white-list` - 登入白名單

## 移除的 Import

```tsx
// isPaufen 相關
import ProvidersList from 'pages/providers/list';
import ProvidersCreate from 'pages/providers/create';
import ProviderShow from 'pages/providers/show';
import ProviderWhiteList from 'pages/providers/whiteList';
import ProviderWalletList from 'pages/providers/wallet-history/list';
import ProviderUserWalletHistoryList from 'pages/providers/user-wallet-history/list';
import DepositList from 'pages/transaction/deposit/list';
import SystemBankCardList from 'pages/transaction/deposit/systemBankCard/list';
import SystemBankCardsCreate from 'pages/transaction/deposit/systemBankCard/create';
import SystemBankCardShow from 'pages/transaction/deposit/systemBankCard/show';
import DepositRewardList from 'pages/transaction/deposit/match-deposit-reward/list';
import DepositRewardCreate from 'pages/transaction/deposit/match-deposit-reward/create';

// region 相關
import TransactionMessageList from 'pages/transaction/message/list';
import FundList from 'pages/transaction/fund/list';
import FundCreate from 'pages/transaction/fund/create';

// 不再需要
import Env from 'lib/env';
import { CreditCardOutlined } from '@ant-design/icons';  // 只用於移除的功能
```

## 實作步驟

1. 更新 authProvider.ts 為 v5 格式
2. 重寫 App.tsx
   - 移除 legacy imports
   - 新增 BrowserRouter、Routes 結構
   - 使用 ThemedLayoutV2
   - 定義顯式路由
   - 更新 resources 為 v5 格式
3. 更新 components/layout/title 使用新的 Link
4. 驗證應用啟動正常
