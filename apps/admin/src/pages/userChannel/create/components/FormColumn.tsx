// components/FormColumn.tsx
import {
  Col,
  ColProps,
} from 'antd';
import { ReactNode } from "react";

interface FormColumnProps extends ColProps {
    children: ReactNode;
}

export const FormColumn = ({ children, ...props }: FormColumnProps) => {
    const defaultProps: ColProps = {
        xs: 24,
        md: 12,
        lg: 6,
        ...props,
    };

    return <Col {...defaultProps}>{children}</Col>;
};
