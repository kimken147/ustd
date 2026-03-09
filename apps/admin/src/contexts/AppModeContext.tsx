import { createContext, useContext, useEffect, useState, FC, PropsWithChildren, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

interface AppMode {
  isPaufen: boolean;
}

interface Terminology {
  /** 码商名称 / 群组名称 — used as column title and form label */
  providerName: string;
  /** 码商 / 群组 (short) — used in transaction show pages */
  groupLabel: string;
  /** 码商管理 / 群组管理 — used in permission groups */
  providerManagement: string;
  /** 码商账号 / 群组账号 — used in live status columns */
  providerAccount: string;
  /** 码商 / 群组 — used in deposit/transaction group pages */
  transactionGroup: string;
}

const AppModeContext = createContext<AppMode>({ isPaufen: true });

function getStoredIsPaufen(): boolean {
  try {
    const stored = JSON.parse(localStorage.getItem('payment-admin-profile') ?? '{}');
    return stored.is_paufen ?? true;
  } catch {
    return true;
  }
}

export const AppModeProvider: FC<PropsWithChildren> = ({ children }) => {
  const [isPaufen, setIsPaufen] = useState(getStoredIsPaufen);

  useEffect(() => {
    const handler = () => setIsPaufen(getStoredIsPaufen());
    window.addEventListener('auth-profile-updated', handler);
    return () => window.removeEventListener('auth-profile-updated', handler);
  }, []);

  const value = useMemo(() => ({ isPaufen }), [isPaufen]);

  return (
    <AppModeContext.Provider value={value}>
      {children}
    </AppModeContext.Provider>
  );
};

export const useAppMode = () => useContext(AppModeContext);

export function useTerminology(): Terminology {
  const { isPaufen } = useAppMode();
  const { t: tUC } = useTranslation('userChannel');
  const { t: tTx } = useTranslation('transaction');
  const { t: tCommon } = useTranslation();
  const { t: tLive } = useTranslation('live');
  const { t: tProv } = useTranslation('providers');

  return useMemo(() => ({
    providerName: isPaufen ? tUC('fields.providerName') : tUC('fields.groupName'),
    groupLabel: isPaufen ? tTx('fields.group') : tTx('fields.groupName'),
    providerManagement: isPaufen ? tCommon('navigation.providerManagement') : tCommon('navigation.groupManagement'),
    providerAccount: isPaufen ? tLive('fields.providerAccount') : tLive('fields.groupAccount'),
    transactionGroup: isPaufen ? tProv('transactionGroup.provider') : tProv('transactionGroup.group'),
  }), [isPaufen, tUC, tTx, tCommon, tLive, tProv]);
}
