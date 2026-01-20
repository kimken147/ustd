import { Col, DatePicker, Input, Radio } from 'antd';
import type { FormInstance } from 'antd';
import CustomDatePicker from 'components/customDatePicker';
import { useSelector } from '@morgan-ustd/shared';
import type { Channel } from '@morgan-ustd/shared';
import { ListPageLayout } from '@morgan-ustd/shared';
import { useTransactionStatus, useTransactionCallbackStatus } from '@morgan-ustd/shared';
import type { Descendant } from 'interfaces/descendant';
import { FC } from 'react';

interface FilterFormProps {
  form: FormInstance;
  t: (key: string) => string;
}

const FilterForm: FC<FilterFormProps> = ({ form, t }) => {
  const { Select: ChannelSelect } = useSelector<Channel>({
    resource: 'channels',
    valueField: 'code',
    labelRender: record => t(`channels.${record.code}`),
  });
  const { Select: DescendantSelect } = useSelector<Descendant>({
    valueField: 'username',
    resource: 'descendants',
    labelField: 'username',
  });
  const { Select: TransStatSelect } = useTransactionStatus();
  const { Select: TranCallbackSelect } = useTransactionCallbackStatus();

  return (
    <>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item
          label={t('datePicker.startDate')}
          name="started_at"
          trigger="onSelect"
          rules={[{ required: true }]}
        >
          <CustomDatePicker
            showTime
            className="w-full"
            onFastSelectorChange={(startAt, endAt) =>
              form.setFieldsValue({ started_at: startAt, ended_at: endAt })
            }
          />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('datePicker.endDate')} name="ended_at" trigger="onSelect">
          <DatePicker showTime className="w-full" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('collection.fields.transactionNo')} name="order_number_or_system_order_number">
          <Input allowClear />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('collection.fields.merchantNo')} name="descendant_merchent_username_or_name">
          <DescendantSelect mode="multiple" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('collection.fields.realName')} name="real_name">
          <Input />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('collection.fields.channels')} name="channel_code[]">
          <ChannelSelect mode="multiple" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('collection.fields.amount')} name="amount">
          <Input />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('collection.fields.transactionStatus')} name="status[]">
          <TransStatSelect mode="multiple" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('collection.fields.callbackStatus')} name="notify_status[]">
          <TranCallbackSelect mode="multiple" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={12}>
        <ListPageLayout.Filter.Item label={t('collection.fields.category')} name="confirmed">
          <Radio.Group>
            <Radio value="created">{t('collection.fields.queryOrderWithCreateAt')}</Radio>
            <Radio value="confirmed">{t('collection.fields.queryOrderWithSucceedAt')}</Radio>
          </Radio.Group>
        </ListPageLayout.Filter.Item>
      </Col>
    </>
  );
};

export default FilterForm;
