import { Col, DatePicker, Form, Radio, Select } from 'antd';
import type { FormInstance } from 'antd';
import CustomDatePicker from 'components/customDatePicker';
import { useSelector } from '@morgan-ustd/shared';
import type { Merchant } from '@morgan-ustd/shared';
import dayjs, { Dayjs } from 'dayjs';
import { ListPageLayout } from '@morgan-ustd/shared';
import { FC } from 'react';

interface FilterFormProps {
  form: FormInstance;
  t: (key: string) => string;
}

const FilterForm: FC<FilterFormProps> = ({ form, t }) => {
  const { selectProps: merchantSelectProps } = useSelector<Merchant>({
    valueField: 'username',
    resource: 'merchants',
  });

  return (
    <>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item
          label={t('filters.startDate')}
          name="started_at"
          getValueProps={(value: any) => {
            if (!value) return { value: undefined };
            if (dayjs.isDayjs(value)) return { value };
            let str = typeof value === 'string' ? decodeURIComponent(value) : String(value);
            str = str.replace(/(T\d{2}:\d{2}:\d{2}) (\d{2}:\d{2})$/, '$1+$2');
            const d = dayjs(str);
            return { value: d.isValid() ? d : undefined };
          }}
        >
          <CustomDatePicker
            showTime
            className="w-full"
            onFastSelectorChange={(startAt, endAt) =>
              form.setFieldsValue({
                started_at: startAt,
                ended_at: endAt,
              })
            }
          />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item
          label={t('filters.endDate')}
          name="ended_at"
          getValueProps={(value: any) => {
            if (!value) return { value: undefined };
            if (dayjs.isDayjs(value)) return { value };
            let str = typeof value === 'string' ? decodeURIComponent(value) : String(value);
            str = str.replace(/(T\d{2}:\d{2}:\d{2}) (\d{2}:\d{2})$/, '$1+$2');
            const d = dayjs(str);
            return { value: d.isValid() ? d : undefined };
          }}
        >
          <DatePicker
            showTime
            className="w-full"
            disabledDate={current => {
              const startAt = form.getFieldValue('started_at') as Dayjs;
              return current && (current > startAt.add(1, 'month') || current < startAt);
            }}
          />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('filters.merchantNameOrAccount')} name="merchant_name_or_username[]">
          <Select {...merchantSelectProps} mode="multiple" allowClear />
        </ListPageLayout.Filter.Item>
      </Col>
      <Col xs={24} md={6}>
        <ListPageLayout.Filter.Item label={t('filters.classification')} name="timeType">
          <Radio.Group>
            <Radio value="created_at">{t('filters.byCreateTime')}</Radio>
            <Radio value="confirmed_at">{t('filters.bySuccessTime')}</Radio>
          </Radio.Group>
        </ListPageLayout.Filter.Item>
      </Col>
    </>
  );
};

export default FilterForm;
