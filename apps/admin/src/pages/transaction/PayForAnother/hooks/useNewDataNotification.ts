import { useEffect, useState } from 'react';
import { CrudFilter } from '@refinedev/core';
import { isEqual } from 'lodash';
import { useAudioPermission } from 'hooks/useAudioPermission';

interface UseNewDataNotificationProps<T extends { id: number | string }> {
  data: T[] | undefined;
  currentPage: number | undefined;
  filters: CrudFilter[] | undefined;
  audioSrc: string;
  enabled?: boolean;
}

interface UseNewDataNotificationReturn {
  enableNotice: boolean;
  setEnableNotice: (enabled: boolean) => void;
  showPermissionAlert: boolean;
  grantPermission: () => void;
  dismissPermissionAlert: () => void;
}

/**
 * Hook for detecting new data and playing notification sound
 *
 * @example
 * const { enableNotice, setEnableNotice, showPermissionAlert, ... } = useNewDataNotification({
 *   data: withdrawData,
 *   currentPage: pagination.current,
 *   filters,
 *   audioSrc: NoticeAudio,
 * });
 */
export function useNewDataNotification<T extends { id: number | string }>({
  data,
  currentPage,
  filters,
  audioSrc,
  enabled = true,
}: UseNewDataNotificationProps<T>): UseNewDataNotificationReturn {
  const [enableNotice, setEnableNotice] = useState(true);
  const [previousState, setPreviousState] = useState<{
    data?: T[];
    page?: number;
    filters?: CrudFilter[];
  }>({ page: 1, filters });

  const {
    showPermissionAlert,
    grantPermission,
    dismissPermissionAlert,
    playAudio,
  } = useAudioPermission(audioSrc);

  useEffect(() => {
    if (!enabled || !enableNotice) return;

    const hasNewData =
      previousState &&
      previousState.page === currentPage &&
      data?.[0]?.id &&
      previousState.data?.[0]?.id !== data?.[0]?.id &&
      isEqual(previousState.filters, filters);

    if (hasNewData) {
      playAudio();
      setPreviousState(prev => ({ ...prev, data }));
    }

    const filtersOrPageChanged =
      !isEqual(previousState.filters, filters) ||
      previousState.page !== currentPage;

    if (filtersOrPageChanged) {
      setPreviousState({
        data,
        page: currentPage,
        filters,
      });
    }
  }, [data, currentPage, previousState, enableNotice, filters, playAudio, enabled]);

  return {
    enableNotice,
    setEnableNotice,
    showPermissionAlert,
    grantPermission,
    dismissPermissionAlert,
  };
}

export default useNewDataNotification;
