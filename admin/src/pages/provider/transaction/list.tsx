import { DeleteOutlined, PlusOutlined } from "@ant-design/icons";
import { Button, Checkbox, Divider, Input, List, Modal, Space, TableColumnProps, TextField } from "@pankod/refine-antd";
import ContentHeader from "components/contentHeader";
import useProvider from "hooks/useProvider";
import useTable from "hooks/useTable";
import useUpdateModal from "hooks/useUpdateModal";
import { MatchTransactionGroup, TransactionGroup } from "interfaces/transactionGroup";
import Enviroment from "lib/env";
import { FC } from "react";
import { Helmet } from "react-helmet";
import { useTranslation } from "react-i18next";

const TransactionGroupList: FC = () => {
    const { t } = useTranslation('providers');
    const { t: tc } = useTranslation(); // common namespace
    const isPaufen = Enviroment.isPaufen;
    const name = isPaufen ? t('transactionGroup.provider') : t('transactionGroup.group');
    const { Select: ProviderSelect } = useProvider();
    const { Form, Table } = useTable<MatchTransactionGroup>({
        formItems: [
            {
                label: t('transactionGroup.merchantName'),
                name: "name_or_username",
                children: <Input />,
            },
        ],
    });
    const {
        modalProps,
        show,
        Modal: UpdateModal,
    } = useUpdateModal({
        formItems: [
            {
                label: name,
                name: "provider_id",
                children: <ProviderSelect />,
                rules: [
                    {
                        required: true,
                    },
                ],
            },
            {
                name: "merchant_id",
                hidden: true,
            },
            {
                name: "personal_enable",
                label: t('transactionGroup.agentLine'),
                children: <Checkbox />,
                valuePropName: "checked",
                extra: t('transactionGroup.agentLineDescription'),
            },
        ],
    });
    const columns: TableColumnProps<MatchTransactionGroup>[] = [
        {
            title: t('transactionGroup.merchantName'),
            dataIndex: "name",
        },
        {
            title: t('transactionGroup.username'),
            dataIndex: "username",
        },
        {
            title: name,
            dataIndex: "transaction_groups",
            render(value: TransactionGroup[], record, index) {
                return (
                  <Space>
                      {value.map((group) => (
                        <Space key={group.id}>
                            <TextField
                              value={`${group.personal_enable ? `(${t('transactionGroup.agentLine')})` : ""}${group.provider_name}`}
                              code
                            />
                            <Button
                              icon={
                                  <DeleteOutlined
                                    style={{
                                        color: "#ff4d4f",
                                    }}
                                  />
                              }
                              size="small"
                              onClick={() =>
                                UpdateModal.confirm({
                                    title: t('transactionGroup.confirmDelete', { name }),
                                    id: group.id,
                                    mode: "delete",
                                })
                              }
                            />
                        </Space>
                      ))}
                  </Space>
                );
            },
        },
        {
            title: tc('operation'),
            render(value, record, index) {
                return (
                  <Button
                    icon={<PlusOutlined />}
                    type="primary"
                    onClick={() =>
                      show({
                          title: t('transactionGroup.addTitle', { name }),
                          initialValues: {
                              merchant_id: record.id,
                              personal_enable: false,
                          },
                          mode: "create",
                          confirmTitle: t('transactionGroup.confirmAdd', { name }),
                          successMessage: t('transactionGroup.addSuccess'),
                      })
                    }
                  >
                      {t('transactionGroup.add')}
                  </Button>
                );
            },
        },
    ];
    return (
      <List title={<ContentHeader title={t('titles.moneyInDirectLine')} resource="providers" />}>
          <Helmet>
              <title>{t('titles.moneyInDirectLine')}</title>
          </Helmet>
          <Form />
          <Divider />
          <Table columns={columns} />
          <Modal {...modalProps} />
      </List>
    );
};

export default TransactionGroupList;