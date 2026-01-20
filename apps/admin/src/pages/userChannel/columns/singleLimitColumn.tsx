import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import numeral from 'numeral';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createSingleLimitColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, canEdit, showUpdateModal } = deps;

  return {
    dataIndex: 'single_limit',
    title: t('fields.singleLimitReceive'),
    render(_, record) {
      let singleLimit = '';
      let singleLimitAll = '';
      let singleLimitStyle: React.CSSProperties = {
        paddingLeft: '15px',
        paddingRight: '15px',
      };

      if (record.single_min_limit !== null && record.single_min_limit !== undefined) {
        singleLimit = numeral(record.single_min_limit).format('0,0.00') + '~';
        singleLimitStyle.paddingLeft = '0px';
      }

      if (record.single_max_limit !== null && record.single_max_limit !== undefined) {
        if (singleLimit === '') {
          singleLimit = '~' + numeral(record.single_max_limit).format('0,0.00');
        } else {
          singleLimit += numeral(record.single_max_limit).format('0,0.00');
        }
        singleLimitStyle.paddingLeft = '0px';
      }

      if (singleLimit !== ' ') {
        singleLimitAll = singleLimit;
      }

      if (
        record.single_min_limit === null &&
        record.withdraw_single_min_limit === null
      ) {
        singleLimitStyle.paddingLeft = '0px';
        singleLimitStyle.paddingRight = '0px';
      }

      return (
        <Space>
          <TextField value={singleLimitAll} style={singleLimitStyle} />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                id: record.id,
                initialValues: {
                  single_min_limit: record.single_min_limit,
                  single_max_limit: record.single_max_limit,
                  allow_unlimited: true,
                },
                filterFormItems: [
                  'single_min_limit',
                  'single_max_limit',
                  'allow_unlimited',
                ],
                title: t('actions.editSingleLimit'),
              });
            }}
          />
        </Space>
      );
    },
  };
}
