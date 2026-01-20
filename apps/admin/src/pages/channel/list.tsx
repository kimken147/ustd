import { FC } from 'react';
import { Input, InputNumber } from 'antd';
import { List, TextField, useTable } from '@refinedev/antd';
import { useApiUrl, useCan } from '@refinedev/core';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { ListPageLayout, Channel } from '@morgan-ustd/shared';
import useUpdateModal from 'hooks/useUpdateModal';
import { useColumns, type ColumnDependencies } from './columns';

const ChannelList: FC = () => {
  const { t } = useTranslation('channel');
  const apiUrl = useApiUrl();

  const { data: canEdit } = useCan({
    action: '15',
    resource: 'channels',
  });

  const {
    tableProps,
    tableQuery: { refetch },
  } = useTable<Channel>({
    resource: 'channels',
    syncWithLocation: true,
    pagination: {
      mode: 'off',
    },
  });

  const { show, modalProps, Modal: UpdateModal } = useUpdateModal({
    formItems: [
      {
        label: (
          <div>
            <TextField value={t('fields.amount')} />
            <TextField value={t('fields.amountPlaceholder')} />
          </div>
        ),
        name: 'amount',
        children: <Input />,
      },
      {
        label: t('fields.matchTimeout'),
        name: 'order_timeout',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.paymentTimeout'),
        name: 'transaction_timeout',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.floatingAmountRange'),
        name: 'floating',
        children: <InputNumber className="w-full" step={1} min={-9} max={9} />,
      },
    ],
    onSuccess: () => {
      refetch();
    },
  });

  const columnDeps: ColumnDependencies = {
    t,
    canEdit: canEdit?.can ?? false,
    apiUrl,
    show,
    Modal: UpdateModal,
    refetch,
  };

  const columns = useColumns(columnDeps);

  return (
    <>
      <Helmet>
        <title>{t('title')}</title>
      </Helmet>
      <List title={t('title')}>
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="code" />
      </List>
      <UpdateModal {...modalProps} />
    </>
  );
};

export default ChannelList;
