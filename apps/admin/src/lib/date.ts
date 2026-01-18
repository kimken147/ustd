import relativeTime from "dayjs/plugin/relativeTime";
import isoWeek from "dayjs/plugin/isoWeek";
import dayjs from "dayjs";
import "dayjs/locale/zh-cn";

export const Format = "YYYY-MM-DD HH:mm:ss";

export function initDayjs() {
    dayjs.extend(relativeTime);
    dayjs.extend(isoWeek);
    dayjs.locale("zh-cn");
}
