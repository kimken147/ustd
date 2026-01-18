import { EditOutlined, DeleteOutlined } from '@ant-design/icons';
import {
  Button,
  Divider,
  Input,
  InputNumber,
  List,
  Modal,
  Select,
  Space,
  Switch,
  Table,
  TableColumnProps,
  TextField,
} from '@pankod/refine-antd';
import ContentHeader from 'components/contentHeader';
import useSelector from 'hooks/useSelector';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { Channel, Gray } from '@morgan-ustd/shared';
import {
  MerchantThirdChannel,
  ThirdChannelsList,
} from 'interfaces/merchantThirdChannel';
import { ThirdChannel } from 'interfaces/thirdChannel';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const ThirdChannelSettingList: FC = () => {
  const { t } = useTranslation('thirdParty');
  const { Select: ChannelSelect, data: channels } = useSelector<Channel>({
    resource: 'channels',
    valueField: 'code',
  });
  const { data: thirdChannels } = useSelector<ThirdChannel>({
    resource: 'thirdchannel',
    valueField: 'name',
  });

  const [selectedThirdChannelId, setSelectedThirdChannelId] = useState(0);
  const selectedThirdChannels = thirdChannels?.filter(
    third => third.id === selectedThirdChannelId
  );
  const { Form, tableProps, refetch } = useTable({
    formItems: [
      {
        label: t('filters.user'),
        name: 'name_or_username',
        children: <Input />,
      },
      {
        label: t('filters.thirdPartyName'),
        name: 'thirdchannel_name',
        children: <Input />,
      },
      {
        label: t('filters.channelType'),
        name: 'channel_code[]',
        children: <ChannelSelect mode="multiple" />,
      },
    ],
  });

  const {
    modalProps,
    show,
    Modal: UpdateModal,
  } = useUpdateModal({
    onCancel() {
      setSelectedThirdChannelId(0);
    },
    formItems: [
      {
        label: t('fields.thirdChannel'),
        name: 'thirdChannel',
        children: (
          <Select
            options={thirdChannels?.map(t => ({
              label: `${t.thirdChannel}(${t.channel})`,
              value: t.id,
            }))}
            value={selectedThirdChannelId}
            onChange={value => setSelectedThirdChannelId(value)}
          />
        ),
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('filters.channelType'),
        name: 'channel_code',
        children: (
          <Select
            options={channels
              ?.filter(channel => {
                return selectedThirdChannels?.find(
                  s => s.channel === channel.name
                );
              })
              .map(channel => ({
                label: channel.name,
                value: channel.code,
              }))}
          />
        ),
        hidden: !selectedThirdChannelId,
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('fields.collectionMinLimit'),
        name: 'deposit_min',
        children: <InputNumber className="w-full" />,
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('fields.collectionMaxLimit'),
        name: 'deposit_max',
        children: <InputNumber className="w-full" />,
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('fields.feePercent'),
        name: 'deposit_fee_percent',
        children: <InputNumber className="w-full" />,
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('fields.payoutMinLimit'),
        name: 'daifu_min',
        children: <InputNumber className="w-full" />,
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('fields.payoutMaxLimit'),
        name: 'daifu_max',
        children: <InputNumber className="w-full" />,
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('fields.feeAmount'),
        name: 'withdraw_fee',
        children: <InputNumber className="w-full" />,
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('fields.feePercent'),
        name: 'daifu_fee_percent',
        children: <InputNumber className="w-full" />,
        rules: [
          {
            required: true,
          },
        ],
      },
    ],
    transferFormValues(record) {
      const channel = channels?.find(
        channel => channel.code === record.channel_code
      );
      if (channel) {
        const thirdChannelId = thirdChannels?.find(
          t =>
            t.id === record.thirdChannel &&
            channel.code.toUpperCase() === record.channel_code.toUpperCase()
        )?.id;
        return {
          ...record,
          thirdchannel_id: thirdChannelId,
        };
      }
      return record;
    },
  });
  const columns: TableColumnProps<MerchantThirdChannel>[] = [
    {
      title: t('fields.merchantName'),
      dataIndex: 'name',
    },
    {
      title: t('fields.loginAccount'),
      dataIndex: 'username',
    },
    {
      title: t('fields.sharedThirdPartyLine'),
      dataIndex: 'include_self_providers',
      render(value, record, index) {
        return (
          <Switch
            checked={value}
            onChange={checked =>
              UpdateModal.confirm({
                title: t('messages.confirmModifyChannel'),
                id: record.id,
                resource: 'merchants',
                values: {
                  include_self_providers: checked,
                  id: record.id,
                },
                onSuccess() {
                  refetch();
                },
              })
            }
          />
        );
      },
    },
    {
      title: t('fields.thirdChannel'),
      dataIndex: 'thirdChannelsList',
      render(value: ThirdChannelsList[], record, index) {
        return (
          <Space>
            {value.map(thirdChannel => {
              return (
                <Space key={thirdChannel.id}>
                  <TextField
                    value={`${thirdChannel.thirdChannel}(${thirdChannel.channel_code})`}
                    style={{ background: Gray, padding: '5px 10px' }}
                  />
                  <EditOutlined
                    style={{
                      color: '#6eb9ff',
                    }}
                    onClick={() => {
                      setSelectedThirdChannelId(thirdChannel.id);
                      show({
                        title: t('actions.editChannel'),
                        id: thirdChannel.id,
                        initialValues: {
                          ...thirdChannel,
                        },
                      });
                    }}
                  />
                  <DeleteOutlined
                    style={{
                      color: '#ff4d4f',
                    }}
                    onClick={() => {
                      UpdateModal.confirm({
                        title: t('messages.confirmDeleteThirdChannel'),
                        id: thirdChannel.id,
                        mode: 'delete',
                        // onSuccess: () => {
                        //     refetch();
                        // },
                      });
                    }}
                  />
                </Space>
              );
            })}
          </Space>
        );
      },
    },
    {
      title: t('actions.operation'),
      render(value, record, index) {
        return (
          <Space>
            <Button
              type="primary"
              onClick={() =>
                show({
                  title: t('actions.addThirdChannel'),
                  formValues: {
                    merchant_id: record.id,
                  },
                  mode: 'create',
                })
              }
            >
              {t('actions.add')}
            </Button>
          </Space>
        );
      },
    },
  ];
  return (
    <List
      title={
        <ContentHeader
          title={t('buttons.thirdChannelSettings')}
          resource="thirdchannel"
        />
      }
    >
      <Helmet>
        <title>{t('buttons.thirdChannelSettings')}</title>
      </Helmet>
      <Form />
      <Divider />
      <Table {...tableProps} columns={columns} />
      <Modal {...modalProps} />
    </List>
  );
};

export default ThirdChannelSettingList;
