import { Col, Divider, Input } from 'antd';
import { CreateButton, List, useTable } from '@refinedev/antd';
import { useTranslate } from '@refinedev/core';
import { ListPageLayout, formValuesToCrudFilters } from '@morgan-ustd/shared';
import type { Member } from 'interfaces/member';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useColumns } from './columns';

const MemberList: FC = () => {
  const t = useTranslate();
  const title = t('member.title');
  const { tableProps, searchFormProps } = useTable<Member>({
    onSearch: formValuesToCrudFilters,
    syncWithLocation: true,
  });

  const columns = useColumns();

  return (
    <>
      <Helmet>
        <title>{title}</title>
      </Helmet>
      <List
        title={title}
        headerButtons={() => <CreateButton>{t('member.buttons.create')}</CreateButton>}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={8}>
              <ListPageLayout.Filter.Item label={t('member.fields.merchantName')} name="name_or_username">
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
