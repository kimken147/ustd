import { InternalTransfer } from "interfaces/internalTransfer";
import { Withdraw } from "@morgan-ustd/shared";

export const getReceiptUrl = (record: Withdraw | InternalTransfer) => {
    const url =
        record.to_channel_account?.channel_code === "MAYA"
            ? `/maya/receipt/${record.order_number}`
            : `${process.env.REACT_APP_HOST}/v1/gcash/${record.system_order_number}/success-page`;

    return url;
};
