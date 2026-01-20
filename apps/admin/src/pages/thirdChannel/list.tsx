import { FC, useState } from 'react';
import { List, ListButton, useTable } from '@refinedev/antd';
import { Col, Divider, Input, InputNumber, Modal, Select } from 'antd';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { uniqBy } from 'lodash';
import { ListPageLayout, Channel } from '@morgan-ustd/shared';
import useSelector from 'hooks/useSelector';
import useUpdateModal from 'hooks/useUpdateModal';
import { ThirdChannel } from 'interfaces/thirdChannel';
import { useColumns, type ColumnDependencies } from './columns';

const ThirdChannelList: FC = () => {
  const { t } = useTranslation('thirdParty');

  const { selectProps: channelSelectProps, data: channels } = useSelector<Channel>({
    resource: 'channels',
    valueField: 'code',
  });

  const { data: thirdChannels } = useSelector<ThirdChannel>({
    resource: 'thirdchannel',
    valueField: 'name',
  });

  const [selectedThirdChannel, setSelectedThirdChannel] = useState<ThirdChannel | null>(null);
  const selectedThirdChannels = thirdChannels?.filter(
    third => third.name === selectedThirdChannel?.name
  );

  const { tableProps, searchFormProps } = useTable<ThirdChannel>({
    resource: 'thirdchannel',
    syncWithLocation: true,
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
              { label: t('filters.enabled'), value: 1 },
              { label: t('filters.disabled'), value: 0 },
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
              { label: t('fields.collectionAndPayout'), value: 1 },
              { label: t('fields.collection'), value: 2 },
              { label: t('fields.payout'), value: 3 },
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
                return selectedThirdChannels?.find(s => s.channel === channel.name);
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

  const columnDeps: ColumnDependencies = {
    t,
    show,
    feeShow,
    setSelectedThirdChannel,
  };

  const columns = useColumns(columnDeps);

  return (
    <List
      headerButtons={() => (
        <ListButton resource="merchant-third-channel">
          {t('buttons.thirdChannelSettings')}
        </ListButton>
      )}
    >
      <Helmet>
        <title>{t('title')}</title>
      </Helmet>

      <ListPageLayout>
        <ListPageLayout.Filter formProps={searchFormProps}>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item label={t('filters.thirdPartyName')} name="name_or_username">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item label={t('filters.channelType')} name="channel_code[]">
              <Select {...channelSelectProps} mode="multiple" allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item label={t('filters.status')} name="status[]">
              <Select
                options={[
                  { label: t('filters.enabled'), value: 1 },
                  { label: t('filters.disabled'), value: 0 },
                ]}
                mode="multiple"
                allowClear
              />
            </ListPageLayout.Filter.Item>
          </Col>
        </ListPageLayout.Filter>
      </ListPageLayout>

      <Divider />
      <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />

      <Modal {...modalProps} />
      <Modal {...feeModalProps} />
    </List>
  );
};

export default ThirdChannelList;
