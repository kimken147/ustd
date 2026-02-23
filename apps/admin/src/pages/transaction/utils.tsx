import { InternalTransfer } from "interfaces/internalTransfer";
import { Withdraw } from "@morgan-ustd/shared";

export const getReceiptUrl = (record: Withdraw | InternalTransfer) => {
    return `${process.env.REACT_APP_HOST}/v1/receipt/${record.system_order_number}`;
};
