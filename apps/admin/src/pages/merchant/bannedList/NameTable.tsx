import { Col, Divider, Input } from 'antd';
import { useTable } from '@refinedev/antd';
import useUpdateModal from 'hooks/useUpdateModal';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { Banned } from 'interfaces/banned';
import { FC } from 'react';
import { useTranslation } from 'react-i18next';
import { useNameColumns, type NameColumnDependencies } from './columns';

interface NameTableProps {
  type: number;
  onRefetchChange?: (refetch: () => void) => void;
}

const NameTable: FC<NameTableProps> = ({ type, onRefetchChange }) => {
  const { t } = useTranslation('merchant');
  const resource = 'banned/realname';

  const {
    tableProps,
    searchFormProps,
    tableQuery: { refetch },
  } = useTable<Banned>({
    resource,
    syncWithLocation: false,
    filters: {
      permanent: [{ field: 'type', value: type, operator: 'eq' }],
    },
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

  const columnDeps: NameColumnDependencies = { t, type, show, Modal };
  const columns = useNameColumns(columnDeps);

  return (
    <>
      <ListPageLayout>
        <ListPageLayout.Filter formProps={searchFormProps}>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item label={t('banned.realName')} name="realname">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
        </ListPageLayout.Filter>
      </ListPageLayout>

      <Divider />
      <h2>{type === 1 ? t('banned.blockedRealNameList') : t('banned.blockedCardHolderList')}</h2>
      <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      <Modal />
    </>
  );
};

export default NameTable;
