import { FC } from 'react';
import { DollarCircleOutlined } from '@ant-design/icons';
import { Card, Col, Row, Statistic } from 'antd';
import { intersectionWith, sumBy } from 'lodash';
import numeral from 'numeral';
import { Yellow, ProviderUserChannel as UserChannel, Meta } from '@morgan-ustd/shared';

interface StatisticsCardProps {
  data: UserChannel[] | undefined;
  meta: Meta | undefined;
  selectedKeys: React.Key[];
  t: (key: string) => string;
}

export const StatisticsCard: FC<StatisticsCardProps> = ({
  data,
  meta,
  selectedKeys,
  t,
}) => {
  const totalBalance = selectedKeys.length
    ? numeral(
        sumBy(
          intersectionWith(data, selectedKeys, (a, b) => a.id === b),
          a => +a.balance
        )
      ).format('0,0.00')
    : meta?.total_balance;

  return (
    <Row className="mb-4">
      <Col xs={24} md={12} lg={6}>
        <Card bordered style={{ border: `2.5px solid ${Yellow}` }}>
          <Statistic
            title={t('fields.totalBalance')}
            valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
            prefix={<DollarCircleOutlined />}
            value={totalBalance}
          />
        </Card>
      </Col>
    </Row>
  );
};

export default StatisticsCard;
