import { Col, Divider, Input } from 'antd';
import { useTable } from '@refinedev/antd';
import useUpdateModal from 'hooks/useUpdateModal';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { Banned } from 'interfaces/banned';
import { FC } from 'react';
import { useTranslation } from 'react-i18next';
import { useIPColumns, type IPColumnDependencies } from './columns';

interface IPTableProps {
  onRefetchChange?: (refetch: () => void) => void;
}

const IPTable: FC<IPTableProps> = ({ onRefetchChange }) => {
  const { t } = useTranslation('merchant');
  const resource = 'banned/ip';

  const {
    tableProps,
    searchFormProps,
    tableQuery: { refetch },
  } = useTable<Banned>({
    resource,
    syncWithLocation: false,
  });

  // Notify parent of refetch function
  if (onRefetchChange) {
    onRefetchChange(refetch);
  }

  const { show, Modal } = useUpdateModal({
    formItems: [
      {
        label: t('banned.note'),
        name: 'note',
        children: <Input />,
        rules: [{ required: true }],
      },
    ],
    resource,
  });

  const columnDeps: IPColumnDependencies = { t, show, Modal };
  const columns = useIPColumns(columnDeps);

  return (
    <>
      <ListPageLayout>
        <ListPageLayout.Filter formProps={searchFormProps}>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item label={t('banned.collectionIp')} name="ipv4">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
        </ListPageLayout.Filter>
      </ListPageLayout>

      <Divider />
      <h2>{t('banned.blockedIpList')}</h2>
      <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      <Modal />
    </>
  );
};

export default IPTable;
