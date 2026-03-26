import { useEffect, useRef, useCallback } from 'react';
import { notification } from 'antd';
import { useTranslation } from 'react-i18next';
import { createEcho } from 'providers/echo';
import { useAudioPermission } from './useAudioPermission';
import type Echo from 'laravel-echo';
import NoticeAudio from 'assets/notice.mp3';

interface WithdrawEvent {
  transactionId: number;
  systemOrderNumber: string;
  orderNumber: string;
  merchantName: string;
  amount: string;
  bankName: string;
  bankCardNumber: string;
  bankCardHolderName: string;
  subType: number;
  createdAt: string;
}

export function useWithdrawNotification() {
  const { t } = useTranslation();
  const echoRef = useRef<Echo | null>(null);
  const { playAudio } = useAudioPermission(NoticeAudio);

  const handleNewWithdraw = useCallback(
    (event: WithdrawEvent) => {
      // 根據 subType 判斷代付類型標籤
      const subTypeLabel =
        event.subType === 2
          ? t('transactions.agencyWithdraw', '代付')
          : t('transactions.withdraw', '下發');

      notification.info({
        message: t('notifications.newWithdraw', '新代付單'),
        description: `${subTypeLabel} | ${event.merchantName} | ${event.amount} | ${event.bankName}`,
        placement: 'topRight',
        duration: 10,
      });

      // 播放音效提醒
      playAudio();
    },
    [t, playAudio],
  );

  useEffect(() => {
    try {
      const echo = createEcho();
      echoRef.current = echo;

      // 使用 dot prefix 因為 broadcastAs() 回傳自定義事件名稱
      echo.private('admin.withdraws').listen('.withdraw.created', handleNewWithdraw);

      return () => {
        echo.private('admin.withdraws').stopListening('.withdraw.created');
        echo.disconnect();
        echoRef.current = null;
      };
    } catch (error) {
      console.error('WebSocket connection failed:', error);
    }
  }, [handleNewWithdraw]);
}
