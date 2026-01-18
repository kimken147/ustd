import type { SelectProps } from "antd";

export type SelectOptions = NonNullable<SelectProps["options"]>;
export type SelectOption = SelectOptions[0];
