import { EditOutlined } from '@ant-design/icons';
import {
  TextField,
} from '@refinedev/antd';
import {
  Badge,
  Popover,
  Space,
} from 'antd';
import useChannelStatus from 'hooks/useChannelStatus';
import useUpdateModal from 'hooks/useUpdateModal';
import { ProviderUserChannel as UserChannel } from '@morgan-ustd/shared';
import { FC } from 'react';
import { useTranslation } from 'react-i18next';

type Props = {
  record: UserChannel;
};
export const ChannelStatusChanger: FC<Props> = ({ record: { status, id } }) => {
  const { t } = useTranslation('userChannel');
  const { getChannelStatusText } = useChannelStatus();
  const text = getChannelStatusText(status);
  const { Modal } = useUpdateModal();
  return (
    <Space>
      <Badge status={status === 2 ? 'success' : 'error'} />
      <TextField value={text} />
      <Popover
        trigger={'click'}
        content={
          <ul className="popover-edit-list">
            {[0, 1, 2]
              .filter(x => x !== status)
              .map(status => (
                <li
                  key={status}
                  onClick={() => {
                    Modal.confirm({
                      id,
                      values: {
                        status,
                      },
                      title: t('confirmation.changeStatus'),
                      className: 'z-10',
                    });
                  }}
                >
                  {getChannelStatusText(status)}
                </li>
              ))}
          </ul>
        }
      >
        <EditOutlined className="text-[#6eb9ff]" />
      </Popover>
    </Space>
  );
};
