import { Col, DatePicker, Input, Radio, Select } from 'antd';
import type { FormProps, FormInstance } from 'antd';
import { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { ListPageLayout, TransactionSubType } from '@morgan-ustd/shared';
import CustomDatePicker from 'components/customDatePicker';

export interface FilterFormProps {
  formProps: FormProps;
  form: FormInstance;
  MerchantSelect: React.ComponentType<any>;
  ChannelSelect: React.ComponentType<any>;
  ThirdChannelSelect: React.ComponentType<any>;
  WithdrawStatusSelect: React.ComponentType<any>;
  TranCallbackSelect: React.ComponentType<any>;
  loading?: boolean;
}

export function FilterForm({
  formProps,
  form,
  MerchantSelect,
  ChannelSelect,
  ThirdChannelSelect,
  WithdrawStatusSelect,
  TranCallbackSelect,
  loading,
}: FilterFormProps) {
  const { t } = useTranslation('transaction');

  const colProps = { xs: 24, md: 6 };

  return (
    <ListPageLayout.Filter formProps={formProps} loading={loading}>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.startDate')}
          name="started_at"
          trigger="onSelect"
          rules={[{ required: true }]}
        >
          <CustomDatePicker
            showTime
            className="w-full"
            onFastSelectorChange={(startAt: Dayjs, endAt: Dayjs) =>
              form.setFieldsValue({
                started_at: startAt,
                ended_at: endAt,
              })
            }
          />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.endDate')}
          name="ended_at"
        >
          <DatePicker
            showTime
            className="w-full"
            disabledDate={current => {
              const startAt = form.getFieldValue('started_at') as Dayjs;
              return (
                current &&
                (current > startAt.add(3, 'month') || current < startAt)
              );
            }}
          />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.merchantOrderOrSystemOrder')}
          name="order_number_or_system_order_number"
        >
          <Input allowClear />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.orderStatus')}
          name="status[]"
        >
          <WithdrawStatusSelect mode="multiple" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.merchantNameOrAccount')}
          name="name_or_username[]"
        >
          <MerchantSelect mode="multiple" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.channel')}
          name="channel_code[]"
        >
          <ChannelSelect mode="multiple" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.orderAmount')}
          name="amount"
        >
          <Input placeholder={t('fields.amountRange')} allowClear />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.agencyAccount')}
          name="account"
        >
          <Input />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.thirdPartyName')}
          name="thirdchannel_id[]"
        >
          <ThirdChannelSelect mode="multiple" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.bankCardKeyword')}
          name="bank_card_q"
        >
          <Input />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('fields.callbackStatus')}
          name="notify_status[]"
        >
          <TranCallbackSelect mode="multiple" />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item
          label={t('withdraw.agencyType')}
          name="sub_type[]"
        >
          <Select
            mode="multiple"
            options={[
              {
                label: t('types.withdraw'),
                value: TransactionSubType.SUB_TYPE_WITHDRAW,
              },
              {
                label: t('types.agency'),
                value: TransactionSubType.SUB_TYPE_AGENCY_WITHDRAW,
              },
              {
                label: t('types.bonusWithdraw'),
                value: TransactionSubType.SUB_TYPE_WITHDRAW_PROFIT,
              },
            ]}
          />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col {...colProps}>
        <ListPageLayout.Filter.Item label={t('fields.category')} name="confirmed">
          <Radio.Group>
            <Radio value={'created'}>{t('filters.byCreateTime')}</Radio>
            <Radio value={'confirmed'}>{t('filters.bySuccessTime')}</Radio>
          </Radio.Group>
        </ListPageLayout.Filter.Item>
      </Col>
    </ListPageLayout.Filter>
  );
}

export default FilterForm;
