import { SelectProps } from "@pankod/refine-antd";
import { singleton } from "@ood/singleton";
import { DefaultOptionType } from "rc-select/lib/Select";
import Badge from "components/badge";

export interface ITransaction {
    statusMap: Record<
        | "已建立"
        | "匹配中"
        | "等待付款"
        | "成功"
        | "手动成功"
        | "匹配超时"
        | "付款超时"
        | "失败"
        | "收款确认"
        | "三方处理中",
        number
    >;
    mapStatusText: (status: number) => string;
    getSelectOptions: () => SelectProps["options"];
}

@singleton
export class DepositHelper implements ITransaction {
    statusMap: Record<string, number> = {
        已建立: 1,
        匹配中: 2,
        等待付款: 3,
        成功: 4,
        手动成功: 5,
        匹配超时: 6,
        付款超时: 7,
        失败: 8,
        收款确认: 10,
        三方处理中: 11,
    };

    mapStatusText(status: number) {
        switch (status) {
            case this.statusMap.已建立:
                return "已建立";
            case this.statusMap.匹配中:
                return "匹配中";
            case this.statusMap.等待付款:
                return "等待付款";
            case this.statusMap.成功:
                return "成功";
            case this.statusMap.手动成功:
                return "手动成功";
            case this.statusMap.匹配超时:
                return "匹配超时";
            case this.statusMap.付款超时:
                return "付款超时";
            case this.statusMap.失败:
                return "失败";
            case this.statusMap.三方处理中:
                return "等待付款";
            default:
                return "";
        }
    }

    getSelectOptions() {
        return Object.values(this.statusMap).map<DefaultOptionType>((value) => ({
            label: this.mapStatusText(value),
            value,
        }));
    }

    getStatusDisplay(value: number, isRefund: boolean = false) {
        let color = "";
        if ([this.statusMap.成功, this.statusMap.手动成功].includes(value)) {
            color = "#16a34a";
        } else if ([this.statusMap.失败].includes(value)) {
            color = "#ff4d4f";
        } else if ([this.statusMap.等待付款, this.statusMap.三方处理中].includes(value)) {
            color = "#1677ff";
        } else if ([this.statusMap.已建立, this.statusMap.匹配中].includes(value)) {
            color = "#ffbe4d";
        } else if (value === this.statusMap.匹配超时) {
            color = "#bebebe";
        } else if ([this.statusMap.付款超时].includes(value)) {
            color = "#ff4d4f";
            return <Badge text={`${this.mapStatusText(value)}${isRefund ? "(退)" : ""}`} color={color} />;
        }
        return <Badge text={this.mapStatusText(value)} color={color} />;
    }
}
