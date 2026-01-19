import { Button, DatePicker, Space } from "antd";
import type { DatePickerProps } from "antd";
import { useTranslate } from "@refinedev/core";
import dayjs, { Dayjs } from "dayjs";
import isoWeek from "dayjs/plugin/isoWeek";
import { FC } from "react";

dayjs.extend(isoWeek);

type Props = DatePickerProps & {
    onFastSelectorChange?: (startAt: Dayjs, endAt: Dayjs) => void;
};

const CustomDatePicker: FC<Props> = ({ onFastSelectorChange, ...rest }) => {
    const translate = useTranslate();

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
                                          onFastSelectorChange(dayjs().startOf("day"), dayjs().endOf("days"))
                                      }
                                  >
                                      {translate("datePicker.today")}
                                  </Button>
                                  <Button
                                      size="small"
                                      onClick={() =>
                                          onFastSelectorChange(
                                              dayjs().subtract(1, "days").startOf("days"),
                                              dayjs().subtract(1, "days").endOf("days"),
                                          )
                                      }
                                  >
                                      {translate("datePicker.yesterday")}
                                  </Button>
                                  <Button
                                      size="small"
                                      onClick={() =>
                                          onFastSelectorChange(dayjs().startOf("isoWeek"), dayjs().endOf("isoWeek"))
                                      }
                                  >
                                      {translate("datePicker.thisWeek")}
                                  </Button>
                                  <Button
                                      size="small"
                                      onClick={() =>
                                          onFastSelectorChange(
                                              dayjs().subtract(1, "weeks").startOf("isoWeek"),
                                              dayjs().subtract(1, "weeks").endOf("isoWeek"),
                                          )
                                      }
                                  >
                                      {translate("datePicker.lastWeek")}
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
