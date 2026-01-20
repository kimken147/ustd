import { Col, DatePicker, Input, Select } from 'antd';
import type { FormInstance } from 'antd';
import CustomDatePicker from 'components/customDatePicker';
import { ListPageLayout, SelectOption } from '@morgan-ustd/shared';
import { FC } from 'react';

interface FilterFormProps {
  form: FormInstance;
  t: (key: string) => string;
  Status: Record<string, number>;
}

const FilterForm: FC<FilterFormProps> = ({ form, t, Status }) => {
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
        <ListPageLayout.Filter.Item label={t('walletHistory.fields.alterationCategories')} name="type[]">
          <Select
            mode="multiple"
            allowClear
            options={Object.values(Status).map<SelectOption>(value => ({
              label: t(`walletHistory.status.${value}`),
              value: value.toString(),
            }))}
          />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('note')} name="note">
          <Input allowClear />
        </ListPageLayout.Filter.Item>
      </Col>
    </>
  );
};

export default FilterForm;
