import { EditOutlined } from '@ant-design/icons';
import {
  DateField,
  List,
  ListButton,
  TextField,
} from '@refinedev/antd';
import {
  Divider,
  Input,
  Space,
  Switch,
  TableColumnProps,
  Typography,
} from 'antd';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { SystemSetting } from 'interfaces/systemSetting';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const SystemSettingList: FC = () => {
  const { t } = useTranslation('systemSettings');
  const { Table, data } = useTable<SystemSetting>({
    hasPagination: false,
  });

  const { Modal, show } = useUpdateModal({
    formItems: [
      {
        label: t('systemSetting.fields.settingValue'),
        name: 'value',
        children: <Input />,
      },
    ],
  });

  const filterData = (ids: number[]) => {
    const items: SystemSetting[] = [];
    ids.forEach(id => {
      const item = data?.find(x => x.id === id);
      if (item) {
        items.push(item);
      }
    });
    return items;
  };
  const agencyColletiion = filterData([
    1, 4, 5, 7, 15, 22, 25, 28, 29, 31, 33, 34, 37, 38, 40, 65, 41, 44, 46, 52,
    64,
  ]);
  const agencyPayment = filterData([
    3, 6, 13, 16, 19, 20, 23, 32, 36, 49, 53, 43,
  ]);
  const accounts = filterData([35, 45, 39]);
  const thirdChannel = filterData([48, 49, 50]);
  // const app = filterData([]);
  const admin = filterData([2, 8, 9, 10, 11, 14, 17, 26, 39, 42, 47, 54]);
  const channel = filterData([12, 18, 21, 24, 30, 51]);

  const columns: TableColumnProps<SystemSetting>[] = [
    {
      title: t('systemSetting.fields.name'),
      dataIndex: 'label',
      width: 300,
    },
    {
      title: t('systemSetting.fields.note'),
      dataIndex: 'note',
      width: 450,
      render: value => <TextField value={value} italic />,
    },
    {
      title: t('systemSetting.fields.switch'),
      dataIndex: 'enabled',
      render: (value, record) => (
        <Switch
          checked={value}
          onChange={value =>
            Modal.confirm({
              title: t('systemSetting.messages.confirmModify'),
              id: record.id,
              values: {
                enabled: value,
              },
            })
          }
        />
      ),
      width: 150,
    },
    {
      title: t('systemSetting.fields.settingValue'),
      dataIndex: 'value',
      render: (value, record) => {
        if (value === null) return null;
        if (record.type === 'boolean' && record.id !== 34) return null;
        else
          return (
            <Space>
              <TextField value={value} />
              <EditOutlined
                style={{
                  color: '#6eb9ff',
                }}
                onClick={() =>
                  show({
                    id: record.id,
                    initialValues: {
                      value: record.value,
                    },
                    filterFormItems: ['value'],
                    title: t('systemSetting.actions.editSettingValue'),
                  })
                }
              />
            </Space>
          );
      },
      width: 300,
    },
    {
      title: t('systemSetting.fields.unit'),
      dataIndex: 'unit',
    },
    {
      title: t('systemSetting.fields.updateTime'),
      dataIndex: 'updated_at',
      render: value => {
        return <DateField value={value} format="YYYY-MM-DD HH:mm:ss" />;
      },
    },
  ];

  return (
    <>
      <Helmet>
        <title>{t('systemSetting.title')}</title>
      </Helmet>
      <List
        title={t('systemSetting.listTitle')}
        headerButtons={
          <>
            <ListButton resourceNameOrRouteName="banks">
              {t('systemSetting.buttons.systemSupportBanks')}
            </ListButton>
          </>
        }
      >
        <Typography.Title level={4}>
          {t('systemSetting.categories.collection')}
        </Typography.Title>
        <Table dataSource={agencyColletiion} columns={columns}></Table>
        <Divider />
        <Typography.Title level={4}>
          {t('systemSetting.categories.payout')}
        </Typography.Title>
        <Table dataSource={agencyPayment} columns={columns} />
        <Divider />
        <Typography.Title level={4}>
          {t('systemSetting.categories.accounts')}
        </Typography.Title>
        <Table
          dataSource={accounts.map<SystemSetting>(x => {
            if (x.id === 39)
              return {
                ...x,
                value: null,
              };
            return x;
          })}
          columns={columns}
        />
        <Divider />
        <Typography.Title level={4}>
          {t('systemSetting.categories.thirdParty')}
        </Typography.Title>
        <Table dataSource={thirdChannel} columns={columns} />
        {/* <Typography.Title level={4}>APP相关</Typography.Title>
                <Table dataSource={app} columns={columns} /> */}
        <Divider />
        <Typography.Title level={4}>
          {t('systemSetting.categories.admin')}
        </Typography.Title>
        <Table dataSource={admin} columns={columns} />
        <Divider />
        <Typography.Title level={4}>
          {t('systemSetting.categories.channel')}
        </Typography.Title>
        <Table dataSource={channel} columns={columns} />
      </List>
      <Modal />
    </>
  );
};

export default SystemSettingList;
