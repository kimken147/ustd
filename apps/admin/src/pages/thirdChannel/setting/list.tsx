import { FC, useState } from 'react';
import { List, useTable } from '@refinedev/antd';
import { Col, Divider, Input, InputNumber, Modal, Select } from 'antd';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { ListPageLayout, Channel } from '@morgan-ustd/shared';
import ContentHeader from 'components/contentHeader';
import useSelector from 'hooks/useSelector';
import useUpdateModal from 'hooks/useUpdateModal';
import { MerchantThirdChannel } from 'interfaces/merchantThirdChannel';
import { ThirdChannel } from 'interfaces/thirdChannel';
import { useColumns, type ColumnDependencies } from './columns';

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

  const {
    tableProps,
    searchFormProps,
    tableQuery: { refetch },
  } = useTable<MerchantThirdChannel>({
    resource: 'merchant-third-channel',
    syncWithLocation: true,
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
            options={thirdChannels?.map(tc => ({
              label: `${tc.thirdChannel}(${tc.channel})`,
              value: tc.id,
            }))}
            value={selectedThirdChannelId}
            onChange={value => setSelectedThirdChannelId(value)}
          />
        ),
        rules: [{ required: true }],
      },
      {
        label: t('filters.channelType'),
        name: 'channel_code',
        children: (
          <Select
            options={channels
              ?.filter(channel => {
                return selectedThirdChannels?.find(s => s.channel === channel.name);
              })
              .map(channel => ({
                label: channel.name,
                value: channel.code,
              }))}
          />
        ),
        hidden: !selectedThirdChannelId,
        rules: [{ required: true }],
      },
      {
        label: t('fields.collectionMinLimit'),
        name: 'deposit_min',
        children: <InputNumber className="w-full" />,
        rules: [{ required: true }],
      },
      {
        label: t('fields.collectionMaxLimit'),
        name: 'deposit_max',
        children: <InputNumber className="w-full" />,
        rules: [{ required: true }],
      },
      {
        label: t('fields.feePercent'),
        name: 'deposit_fee_percent',
        children: <InputNumber className="w-full" />,
        rules: [{ required: true }],
      },
      {
        label: t('fields.payoutMinLimit'),
        name: 'daifu_min',
        children: <InputNumber className="w-full" />,
        rules: [{ required: true }],
      },
      {
        label: t('fields.payoutMaxLimit'),
        name: 'daifu_max',
        children: <InputNumber className="w-full" />,
        rules: [{ required: true }],
      },
      {
        label: t('fields.feeAmount'),
        name: 'withdraw_fee',
        children: <InputNumber className="w-full" />,
        rules: [{ required: true }],
      },
      {
        label: t('fields.feePercent'),
        name: 'daifu_fee_percent',
        children: <InputNumber className="w-full" />,
        rules: [{ required: true }],
      },
    ],
    transferFormValues(record) {
      const channel = channels?.find(ch => ch.code === record.channel_code);
      if (channel) {
        const thirdChannelId = thirdChannels?.find(
          tc =>
            tc.id === record.thirdChannel &&
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

  const columnDeps: ColumnDependencies = {
    t,
    show,
    setSelectedThirdChannelId,
    refetch,
    UpdateModal,
  };

  const columns = useColumns(columnDeps);

  return (
    <List
      title={<ContentHeader title={t('buttons.thirdChannelSettings')} resource="thirdchannel" />}
    >
      <Helmet>
        <title>{t('buttons.thirdChannelSettings')}</title>
      </Helmet>

      <ListPageLayout>
        <ListPageLayout.Filter formProps={searchFormProps}>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item label={t('filters.user')} name="name_or_username">
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item
              label={t('filters.thirdPartyName')}
              name="thirdchannel_name"
            >
              <Input allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
          <Col xs={24} md={8}>
            <ListPageLayout.Filter.Item label={t('filters.channelType')} name="channel_code[]">
              <ChannelSelect mode="multiple" allowClear />
            </ListPageLayout.Filter.Item>
          </Col>
        </ListPageLayout.Filter>
      </ListPageLayout>

      <Divider />
      <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />

      <Modal {...modalProps} />
    </List>
  );
};

export default ThirdChannelSettingList;
