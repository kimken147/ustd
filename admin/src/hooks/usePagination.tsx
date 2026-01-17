import { useEffect, useState } from "react";

type Props = {
    current: number;
    total: number;
    pageSize: number;
};

const usePagination = ({ current, total, pageSize }: Props) => {
    const [cloneCurrent, setCurrent] = useState(current);

    useEffect(() => {
        if (current !== cloneCurrent) {
            setCurrent(current);
        }
    }, [cloneCurrent, current]);

    return {
        total,
        current: cloneCurrent,
        pageSize,
        setCurrent,
    };
};

export default usePagination;
