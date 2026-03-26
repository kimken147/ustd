import { useWithdrawNotification } from 'hooks/useWithdrawNotification';

// 全域代付單 WebSocket 通知監聽元件 — 不渲染任何 UI
export function WithdrawNotificationListener() {
  useWithdrawNotification();
  return null;
}
