import numeral from "numeral";

export interface NumberSignResult {
    value: number;
    className: string;
}

export function getNumberSign(value: number): NumberSignResult {
    let className = "";
    const amount = numeral(value).value();
    if (amount !== null) {
        if (amount > 0) className = "text-[#16A34A]";
        else if (amount < 0) className = "text-[#FF4D4F]";
    }
    return { value, className };
}

/**
 * Format a number with sign coloring (positive=green, negative=red)
 * Returns the className to apply for styling
 */
export function getSignClassName(value: number): string {
    const amount = numeral(value).value();
    if (amount !== null) {
        if (amount > 0) return "text-[#16A34A]";
        else if (amount < 0) return "text-[#FF4D4F]";
    }
    return "";
}
