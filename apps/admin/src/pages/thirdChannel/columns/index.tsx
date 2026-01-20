import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import Badge from 'components/badge';
import type { ColumnDependencies, ThirdChannelColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): ThirdChannelColumn[] {
  const { t, show, feeShow, setSelectedThirdChannel } = deps;

  return [
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
      render(value, record) {
        const color = value === 1 ? '#16a34a' : '#ff4d4f';
        const text = value === 1 ? t('filters.enabled') : t('filters.disabled');
        return (
          <Space>
            <Badge color={color} text={text} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  filterFormItems: ['status'],
                  title: t('actions.editStatus'),
                  id: record.id,
                  initialValues: { status: value },
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
      render(value, record) {
        return (
          <Space>
            {value ? (
              <Button
                icon={<EditOutlined className="text-[#6eb9ff]" />}
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
      render(value, record) {
        let text = '';
        if (value === 1) text = t('fields.collectionAndPayout');
        else if (value === 2) text = t('fields.collection');
        else text = t('fields.payout');
        return (
          <Space>
            <TextField value={text} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  filterFormItems: ['type2'],
                  title: t('actions.editType'),
                  id: record.id,
                  initialValues: { type2: value },
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
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  filterFormItems: ['auto_daifu_threshold_min'],
                  title: t('actions.editStatus'),
                  id: record.id,
                  initialValues: { auto_daifu_threshold_min: value },
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
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  filterFormItems: ['auto_daifu_threshold'],
                  title: t('actions.editStatus'),
                  id: record.id,
                  initialValues: { auto_daifu_threshold: value },
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
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  filterFormItems: ['custom_url'],
                  title: t('actions.editCustomUrl'),
                  id: record.id,
                  initialValues: { custom_url: value },
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
      render(value, record) {
        return (
          <Space>
            <TextField value={value} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  filterFormItems: ['white_ip'],
                  title: t('actions.editCallbackIp'),
                  id: record.id,
                  initialValues: { white_ip: value },
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
      render(value, record) {
        return (
          <Space>
            <TextField value={value} ellipsis style={{ width: 150 }} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  filterFormItems: ['key'],
                  title: t('actions.editKey'),
                  id: record.id,
                  initialValues: { key: value },
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
      render(value, record) {
        return (
          <Space>
            <TextField value={value} ellipsis style={{ width: 150 }} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  filterFormItems: ['key2'],
                  title: t('actions.editKey2'),
                  id: record.id,
                  initialValues: { key2: value },
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
      render(value, record) {
        return (
          <Space>
            <TextField value={value} ellipsis style={{ width: 150 }} />
            <Button
              icon={<EditOutlined className="text-[#6eb9ff]" />}
              onClick={() => {
                show({
                  filterFormItems: ['key3'],
                  title: t('actions.editKey3'),
                  id: record.id,
                  initialValues: { key3: value },
                });
              }}
            />
          </Space>
        );
      },
    },
  ];
}
