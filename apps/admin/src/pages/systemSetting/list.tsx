import { List, ListButton, useTable } from '@refinedev/antd';
import { Divider, Input, Table, Typography } from 'antd';
import useUpdateModal from 'hooks/useUpdateModal';
import { SystemSetting } from 'interfaces/systemSetting';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { useColumns, type ColumnDependencies } from './columns';

const SystemSettingList: FC = () => {
  const { t } = useTranslation('systemSettings');

  const {
    tableQuery: { data: tableData },
  } = useTable<SystemSetting>({
    resource: 'system-settings',
    pagination: { mode: 'off' },
  });

  const data = tableData?.data;

  const { Modal, show } = useUpdateModal({
    formItems: [
      { label: t('systemSetting.fields.settingValue'), name: 'value', children: <Input /> },
    ],
  });

  const columnDeps: ColumnDependencies = { t, show, Modal };
  const columns = useColumns(columnDeps);

  const filterData = (ids: number[]) => {
    const items: SystemSetting[] = [];
    ids.forEach(id => {
      const item = data?.find(x => x.id === id);
      if (item) items.push(item);
    });
    return items;
  };

  const agencyCollection = filterData([1, 4, 5, 7, 15, 22, 25, 28, 29, 31, 33, 34, 37, 38, 40, 65, 41, 44, 46, 52, 64]);
  const agencyPayment = filterData([3, 6, 13, 16, 19, 20, 23, 32, 36, 49, 53, 43]);
  const accounts = filterData([35, 45, 39]);
  const thirdChannel = filterData([48, 49, 50]);
  const admin = filterData([2, 8, 9, 10, 11, 14, 17, 26, 39, 42, 47, 54]);
  const channel = filterData([12, 18, 21, 24, 30, 51]);

  return (
    <>
      <Helmet>
        <title>{t('systemSetting.title')}</title>
      </Helmet>
      <List
        title={t('systemSetting.listTitle')}
        headerButtons={
          <ListButton resource="banks">{t('systemSetting.buttons.systemSupportBanks')}</ListButton>
        }
      >
        <Typography.Title level={4}>{t('systemSetting.categories.collection')}</Typography.Title>
        <Table dataSource={agencyCollection} columns={columns} rowKey="id" pagination={false} />

        <Divider />
        <Typography.Title level={4}>{t('systemSetting.categories.payout')}</Typography.Title>
        <Table dataSource={agencyPayment} columns={columns} rowKey="id" pagination={false} />

        <Divider />
        <Typography.Title level={4}>{t('systemSetting.categories.accounts')}</Typography.Title>
        <Table
          dataSource={accounts.map<SystemSetting>(x => (x.id === 39 ? { ...x, value: null } : x))}
          columns={columns}
          rowKey="id"
          pagination={false}
        />

        <Divider />
        <Typography.Title level={4}>{t('systemSetting.categories.thirdParty')}</Typography.Title>
        <Table dataSource={thirdChannel} columns={columns} rowKey="id" pagination={false} />

        <Divider />
        <Typography.Title level={4}>{t('systemSetting.categories.admin')}</Typography.Title>
        <Table dataSource={admin} columns={columns} rowKey="id" pagination={false} />

        <Divider />
        <Typography.Title level={4}>{t('systemSetting.categories.channel')}</Typography.Title>
        <Table dataSource={channel} columns={columns} rowKey="id" pagination={false} />
      </List>
      <Modal />
    </>
  );
};

export default SystemSettingList;
