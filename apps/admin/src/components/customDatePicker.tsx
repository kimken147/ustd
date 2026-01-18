import { Button, DatePicker, Space } from 'antd';
import type { DatePickerProps } from 'antd';
import dayjs, { Dayjs } from 'dayjs';
import { FC } from 'react';
import { useTranslation } from 'react-i18next';

type Props = DatePickerProps & {
  onFastSelectorChange?: (startAt: Dayjs, endAt: Dayjs) => void;
};

const CustomDatePicker: FC<Props> = ({ onFastSelectorChange, ...rest }) => {
  const { t } = useTranslation();
  return (
    <DatePicker
      renderExtraFooter={
        onFastSelectorChange
          ? () => {
              return (
                <Space className="px-4">
                  <Button
                    size="small"
                    onClick={() =>
                      onFastSelectorChange(
                        dayjs().startOf('day'),
                        dayjs().endOf('days')
                      )
                    }
                  >
                    {t('datePicker.today')}
                  </Button>
                  <Button
                    size="small"
                    onClick={() =>
                      onFastSelectorChange(
                        dayjs().subtract(1, 'days').startOf('days'),
                        dayjs().subtract(1, 'days').endOf('days')
                      )
                    }
                  >
                    {t('datePicker.yesterday')}
                  </Button>
                  <Button
                    size="small"
                    onClick={() =>
                      onFastSelectorChange(
                        dayjs().startOf('months'),
                        dayjs().endOf('months')
                      )
                    }
                  >
                    {t('datePicker.thisMonth')}
                  </Button>
                  <Button
                    size="small"
                    onClick={() =>
                      onFastSelectorChange(
                        dayjs().subtract(1, 'months').startOf('months'),
                        dayjs().subtract(1, 'months').endOf('months')
                      )
                    }
                  >
                    {t('datePicker.lastMonth')}
                  </Button>
                  <Button
                    size="small"
                    onClick={() =>
                      onFastSelectorChange(
                        dayjs().startOf('isoWeek'),
                        dayjs().endOf('isoWeek')
                      )
                    }
                  >
                    {t('datePicker.thisWeek')}
                  </Button>
                  <Button
                    size="small"
                    onClick={() =>
                      onFastSelectorChange(
                        dayjs().subtract(1, 'weeks').startOf('isoWeek'),
                        dayjs().subtract(1, 'weeks').endOf('isoWeek')
                      )
                    }
                  >
                    {t('datePicker.lastWeek')}
                  </Button>
                </Space>
              );
            }
          : undefined
      }
      {...rest}
    />
  );
};

export default CustomDatePicker;
