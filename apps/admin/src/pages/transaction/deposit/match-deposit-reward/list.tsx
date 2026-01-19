import {
  CreateButton,
  DeleteButton,
  EditButton,
  List,
} from '@refinedev/antd';
import {
  Input,
  InputNumber,
  Modal,
  Radio,
  Space,
  Table,
} from 'antd';
import { useList, useResourceParams } from '@refinedev/core';
import ContentHeader from 'components/contentHeader';
import useUpdateModal from 'hooks/useUpdateModal';
import { DepositReward } from 'interfaces/depositReward';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const DepositRewardList: FC = () => {
  const { t } = useTranslation('transaction');
  const title = t('titles.quickChargeRewardList');

  const { resource } = useResourceParams();
  const resourceName = resource?.name;
  const { result, query } = useList<DepositReward>({
    resource: resourceName,
    pagination: { mode: 'off' },
  });
  const rewards = result;
  const isLoading = query.isLoading;

  const { modalProps, show } = useUpdateModal({
    resource: resourceName,
    formItems: [
      {
        label: t('fields.amountRange'),
        name: 'amount',
        children: <Input />,
      },
      {
        label: t('fields.rewardMode'),
        name: 'reward_unit',
        children: (
          <Radio.Group>
            <Radio value={1}>{t('types.perOrderReward')}</Radio>
            <Radio value={2}>{t('types.percentageReward')}</Radio>
          </Radio.Group>
        ),
      },
      {
        label: t('fields.rewardCommission'),
        name: 'reward_amount',
        children: <InputNumber className="w-full" />,
      },
    ],
  });

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <List
        title={<ContentHeader title={title} resource="deposit" />}
        headerButtons={
          <>
            <CreateButton>{t('actions.createQuickChargeReward')}</CreateButton>
          </>
        }
      >
        <Table
          dataSource={rewards?.data}
          loading={isLoading}
          pagination={false}
          columns={[
            {
              title: t('fields.amountRange'),
              render(value, record, index) {
                return `${record.min_amount}~${record.max_amount}`;
              },
            },
            {
              title: t('fields.rewardCommission'),
              render(value, record, index) {
                return `${record.reward_amount}/${
                  record.reward_unit === 1
                    ? t('units.perOrder')
                    : t('units.percentage')
                }`;
              },
            },
            {
              title: t('actions.operation'),
              render(value, record, index) {
                return (
                  <Space>
                    <EditButton
                      onClick={() =>
                        show({
                          title: t('actions.editQuickChargeReward'),
                          initialValues: {
                            ...record,
                            amount: `${record.min_amount}~${record.max_amount}`,
                          },
                          id: record.id,
                        })
                      }
                    >
                      {t('actions.edit')}
                    </EditButton>
                    <DeleteButton danger>{t('actions.delete')}</DeleteButton>
                  </Space>
                );
              },
            },
          ]}
        />
      </List>
      <Modal {...modalProps} />
    </>
  );
};

export default DepositRewardList;
