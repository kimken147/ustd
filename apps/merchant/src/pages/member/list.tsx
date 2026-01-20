import { Col, Divider, Input } from 'antd';
import { CreateButton, List, useTable } from '@refinedev/antd';
import { ListPageLayout } from '@morgan-ustd/shared';
import type { Member } from 'interfaces/member';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useColumns } from './columns';

const MemberList: FC = () => {
  const { tableProps, searchFormProps } = useTable<Member>({
    syncWithLocation: true,
  });

  const columns = useColumns();

  return (
    <>
      <Helmet>
        <title>下级管理</title>
      </Helmet>
      <List
        title="下级管理"
        headerButtons={() => <CreateButton>建立下级帐号</CreateButton>}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label="商户名称" name="name_or_username">
                <Input allowClear />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <Divider />
        <ListPageLayout.Table {...tableProps} columns={columns} rowKey="id" />
      </List>
    </>
  );
};

export default MemberList;
