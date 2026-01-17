import { Spin } from "@pankod/refine-antd";
import { FC, useCallback, useState } from "react";

const useLoading = () => {
    const [isShow, setShow] = useState(false);

    const LoadingFullscreen: FC = () => {
        return (
            <div
                className={`w-full h-full absolute top-0 left-0 justify-center items-center bg-black/5 ${
                    isShow ? "flex" : "hidden"
                }`}
            >
                <Spin size="large" />
            </div>
        );
    };

    const show = useCallback(() => {
        setShow(true);
    }, []);

    const hide = useCallback(() => {
        setShow(false);
    }, []);

    return {
        LoadingFullscreen,
        show,
        hide,
        isLoading: isShow,
    };
};

export default useLoading;
