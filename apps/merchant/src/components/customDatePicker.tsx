import { Button, DatePicker, DatePickerProps, Space } from "@pankod/refine-antd";
import { useTranslate } from "@pankod/refine-core";
import dayjs, { Dayjs } from "dayjs";
import { FC } from "react";

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
                                  {/* <Button
                              size="small"
                              onClick={() =>
                                  onFastSelectorChange(dayjs().startOf("months"), dayjs().endOf("months"))
                              }
                          >
                              本月
                          </Button>
                          <Button
                              size="small"
                              onClick={() =>
                                  onFastSelectorChange(
                                      dayjs().subtract(1, "months").startOf("months"),
                                      dayjs().subtract(1, "months").endOf("months"),
                                  )
                              }
                          >
                              上月
                          </Button> */}
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
