import { EditOutlined } from '@ant-design/icons';
import {
  List,
  ListButton,
  TextField,
} from '@refinedev/antd';
import {
  Button,
  Divider,
  Input,
  InputNumber,
  Modal,
  Select,
  Space,
  Table,
  TableColumnProps,
} from 'antd';
import Badge from 'components/badge';
import useSelector from 'hooks/useSelector';
import useTable from 'hooks/useTable';
import useUpdateModal from 'hooks/useUpdateModal';
import { Channel } from '@morgan-ustd/shared';
import { ThirdChannel } from 'interfaces/thirdChannel';
import { uniqBy } from 'lodash';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const ThirdChannelList: FC = () => {
  const { t } = useTranslation('thirdParty');
  const { selectProps: channelSelectProps, data: channels } =
    useSelector<Channel>({
      resource: 'channels',
      valueField: 'code',
    });

  const { data: thirdChannels } = useSelector<ThirdChannel>({
    resource: 'thirdchannel',
    valueField: 'name',
  });

  const [selectedThirdChannel, setSelectedThirdChannel] =
    useState<ThirdChannel | null>();
  const selectedThirdChannels = thirdChannels?.filter(
    third => third.name === selectedThirdChannel?.name
  );

  const { Form, tableProps, tableOutterStyle } = useTable({
    formItems: [
      {
        label: t('filters.thirdPartyName'),
        name: 'name_or_username',
        children: <Input />,
      },
      {
        label: t('filters.channelType'),
        name: 'channel_code[]',
        children: <Select {...channelSelectProps} mode="multiple" />,
      },

      {
        label: t('filters.status'),
        name: 'status[]',
        children: (
          <Select
            options={[
              {
                label: t('filters.enabled'),
                value: 1,
              },
              {
                label: t('filters.disabled'),
                value: 0,
              },
            ]}
            mode="multiple"
          />
        ),
      },
    ],
  });

  const { modalProps, show } = useUpdateModal({
    resource: 'thirdchannel',
    formItems: [
      {
        label: t('filters.status'),
        name: 'status',
        children: (
          <Select
            options={[
              {
                label: t('filters.enabled'),
                value: 1,
              },
              {
                label: t('filters.disabled'),
                value: 0,
              },
            ]}
          />
        ),
      },
      {
        label: t('fields.type'),
        name: 'type2',
        children: (
          <Select
            options={[
              {
                label: t('fields.collectionAndPayout'),
                value: 1,
              },
              {
                label: t('fields.collection'),
                value: 2,
              },
              {
                label: t('fields.payout'),
                value: 3,
              },
            ]}
          />
        ),
      },
      {
        label: t('fields.autoPushThresholdMax'),
        name: 'auto_daifu_threshold',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.autoPushThresholdMin'),
        name: 'auto_daifu_threshold_min',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.customUrlGateway'),
        name: 'custom_url',
        children: <Input />,
      },
      {
        label: t('fields.callbackIp'),
        name: 'white_ip',
        children: <Input />,
      },
      {
        label: t('fields.thirdPartyMerchantId'),
        name: 'merchant_id',
        children: <Input />,
      },
      {
        label: t('fields.key'),
        name: 'key',
        children: <Input />,
      },
      {
        label: t('fields.key2'),
        name: 'key2',
        children: <Input />,
      },
      {
        label: t('fields.key3'),
        name: 'key3',
        children: <Input />,
      },
    ],
  });

  const { modalProps: feeModalProps, show: feeShow } = useUpdateModal({
    resource: 'thirdchannel',
    onCancel() {
      setSelectedThirdChannel(null);
    },
    formItems: [
      {
        label: t('fields.thirdChannel'),
        name: 'thirdChannel',
        children: (
          <Select
            disabled
            options={uniqBy(thirdChannels, t => t.name).map(t => ({
              label: t.thirdChannel,
              value: t.name,
            }))}
            defaultValue={selectedThirdChannel?.name}
          />
        ),
      },
      {
        label: t('filters.channelType'),
        name: 'channel_code',
        children: (
          <Select
            disabled
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
            defaultValue={selectedThirdChannel?.channel}
          />
        ),
      },
      {
        label: t('fields.collectionMinLimit'),
        name: 'deposit_min',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.collectionMaxLimit'),
        name: 'deposit_max',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.feePercent'),
        name: 'deposit_fee_percent',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.payoutMinLimit'),
        name: 'daifu_min',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.payoutMaxLimit'),
        name: 'daifu_max',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.feeAmount'),
        name: 'withdraw_fee',
        children: <InputNumber className="w-full" />,
      },
      {
        label: t('fields.feePercent'),
        name: 'daifu_fee_percent',
        children: <InputNumber className="w-full" />,
      },
      {
        name: 'is_batch',
        initialValue: true,
        hidden: true,
      },
    ],
  });

  const columns: TableColumnProps<ThirdChannel>[] = [
    {
      title: t('fields.thirdPartyName'),
      dataIndex: 'name',
    },
    {
      title: t('filters.channelType'),
      dataIndex: 'channel',
    },
    {
      title: t('fields.thirdPartyMerchantId'),
      dataIndex: 'merchant_id',
    },
    {
      title: t('fields.thirdPartyAccount'),
      dataIndex: 'balance',
    },
    {
      title: t('fields.status'),
      dataIndex: 'status',
      render: (value, record) => {
        const color = value === 1 ? '#16a34a' : '#ff4d4f';
        const text = value === 1 ? t('filters.enabled') : t('filters.disabled');
        return (
          <Space>
            <Badge color={color} text={text} />
            <Button
              icon={<EditOutlined className={'text-[#6eb9ff]'} />}
              onClick={() => {
                show({
                  filterFormItems: ['status'],
                  title: t('actions.editStatus'),
                  id: record.id,
                  initialValues: {
                    status: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('actions.batchFeeModification'),
      dataIndex: 'merchants',
      render(value, record, index) {
        return (
          <Space>
            {value ? (
              <Button
                icon={<EditOutlined className={'text-[#6eb9ff]'} />}
                onClick={() => {
                  setSelectedThirdChannel(record);
                  feeShow({
                    title: t('actions.batchFeeModification'),
                    id: record.id,
                  });
                }}
              />
            ) : null}
          </Space>
        );
      },
    },
    {
      title: t('fields.type'),
      dataIndex: 'type',
      render: (value, record) => {
        let text = '';
        if (value === 1) text = t('fields.collectionAndPayout');
        else if (value === 2) text = t('fields.collection');
        else text = t('fields.payout');
        return (
          <Space>
            <TextField value={text} />
            <Button
              icon={<EditOutlined className={'text-[#6eb9ff]'} />}
              onClick={() => {
                show({
                  filterFormItems: ['type2'],
                  title: t('actions.editType'),
                  id: record.id,
                  initialValues: {
                    type2: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.autoPushThresholdMin'),
      dataIndex: 'auto_daifu_threshold_min',
      render: (value, record) => {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className={'text-[#6eb9ff]'} />}
              onClick={() => {
                show({
                  filterFormItems: ['auto_daifu_threshold_min'],
                  title: t('actions.editStatus'),
                  id: record.id,
                  initialValues: {
                    auto_daifu_threshold_min: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.autoPushThresholdMax'),
      dataIndex: 'auto_daifu_threshold',
      render: (value, record) => {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className={'text-[#6eb9ff]'} />}
              onClick={() => {
                show({
                  filterFormItems: ['auto_daifu_threshold'],
                  title: t('actions.editStatus'),
                  id: record.id,
                  initialValues: {
                    auto_daifu_threshold: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.customUrlGateway'),
      dataIndex: 'custom_url',
      render(value, record, index) {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className={'text-[#6eb9ff]'} />}
              onClick={() => {
                show({
                  filterFormItems: ['custom_url'],
                  title: t('actions.editCustomUrl'),
                  id: record.id,
                  initialValues: {
                    custom_url: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.callbackIp'),
      dataIndex: 'white_ip',
      render(value, record, index) {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className={'text-[#6eb9ff]'} />}
              onClick={() => {
                show({
                  filterFormItems: ['white_ip'],
                  title: t('actions.editCallbackIp'),
                  id: record.id,
                  initialValues: {
                    white_ip: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.key'),
      dataIndex: 'key',
      render(value, record, index) {
        return (
          <Space>
            <TextField value={value} ellipsis style={{ width: 150 }} />
            <Button
              icon={<EditOutlined className={'text-[#6eb9ff]'} />}
              onClick={() => {
                show({
                  filterFormItems: ['key'],
                  title: t('actions.editKey'),
                  id: record.id,
                  initialValues: {
                    key: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.key2'),
      dataIndex: 'key2',
      render(value, record, index) {
        return (
          <Space>
            <TextField value={value} ellipsis style={{ width: 150 }} />
            <Button
              icon={<EditOutlined className={'text-[#6eb9ff]'} />}
              onClick={() => {
                show({
                  filterFormItems: ['key2'],
                  title: t('actions.editKey2'),
                  id: record.id,
                  initialValues: {
                    key2: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.key3'),
      dataIndex: 'key3',
      render(value, record, index) {
        return (
          <Space>
            <TextField value={value} ellipsis style={{ width: 150 }} />
            <Button
              icon={<EditOutlined className={'text-[#6eb9ff]'} />}
              onClick={() => {
                show({
                  filterFormItems: ['key3'],
                  title: t('actions.editKey3'),
                  id: record.id,
                  initialValues: {
                    key3: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
  ];
  return (
    <List
      headerButtons={() => (
        <>
          <ListButton resourceNameOrRouteName="merchant-third-channel">
            {t('buttons.thirdChannelSettings')}
          </ListButton>
        </>
      )}
    >
      <Helmet>
        <title>{t('title')}</title>
      </Helmet>
      <Form />
      <Divider />
      <div style={tableOutterStyle}>
        <Table {...tableProps} columns={columns} rowKey="id" />
      </div>
      <Modal {...modalProps} />
      <Modal {...feeModalProps} />
    </List>
  );
};

export default ThirdChannelList;
