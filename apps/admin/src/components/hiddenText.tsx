import { useCan } from "@pankod/refine-core";
import { FC, useEffect, useState } from "react";
import hideText from "hide-text";
import { Button, Space, TextField } from "@pankod/refine-antd";
import { EyeOutlined } from "@ant-design/icons";
import useWithdrawStatus from "hooks/useWithdrawStatus";

type Props = {
    text: string;
    status: number;
};

const HiddenText: FC<Props> = ({ text, status }) => {
    const [show, setShow] = useState(false);
    const { Status } = useWithdrawStatus();
    const { data } = useCan({
        action: "34",
        resource: "SI",
    });
    const value = show
        ? text
        : hideText(text, {
              showRight: 4,
          });

    useEffect(() => {
        if (status === Status.成功 || status === Status.手动成功 || status === Status.失败) {
            setShow(false);
        }
    }, [Status.失败, Status.成功, Status.手动成功, status]);

    return (
        <Space>
            <TextField value={value} />
            <Button
                icon={<EyeOutlined className={data?.can ? "text-[#6eb9ff]" : ""} />}
                disabled={!data?.can}
                onClick={() => setShow(!show)}
            />
        </Space>
    );
};

export default HiddenText;
