import numeral from "numeral";

export function getSign(number: number) {
    return `${number >= 0 ? "+" : "-"}${numeral(number).format("0,0.00")}`;
}
