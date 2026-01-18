import { TextField } from "@refinedev/antd";
import numeral from "numeral";

export function getSign(number: number) {
    let color = "";
    const amount = numeral(number).value();
    if (amount !== null) {
        if (amount > 0) color = "text-[#16A34A]";
        else if (amount < 0) color = "text-[#FF4D4F]";
    }
    return <TextField value={number} className={color} />;
}
