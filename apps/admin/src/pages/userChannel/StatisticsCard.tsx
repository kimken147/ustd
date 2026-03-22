import { FC } from 'react';
import { DollarCircleOutlined, WalletOutlined, ThunderboltOutlined } from '@ant-design/icons';
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
  const selected = selectedKeys.length
    ? intersectionWith(data, selectedKeys, (a, b) => a.id === b)
    : null;

  const totalBalance = selected
    ? numeral(sumBy(selected, a => +a.balance)).format('0,0.00')
    : meta?.total_balance;

  const totalOnchainUsdt = selected
    ? numeral(sumBy(selected, a => +(a.onchain_usdt_balance || 0))).format('0,0.000000')
    : numeral(meta?.total_onchain_usdt_balance).format('0,0.000000');

  const totalOnchainGas = selected
    ? numeral(sumBy(selected, a => +(a.onchain_native_balance || 0))).format('0,0.000000')
    : numeral(meta?.total_onchain_native_balance).format('0,0.000000');

  return (
    <Row className="mb-4" gutter={[16, 16]}>
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
      <Col xs={24} md={12} lg={6}>
        <Card bordered style={{ border: '2.5px solid #1890ff' }}>
          <Statistic
            title="USDT"
            valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
            prefix={<WalletOutlined />}
            value={totalOnchainUsdt}
          />
        </Card>
      </Col>
      <Col xs={24} md={12} lg={6}>
        <Card bordered style={{ border: '2.5px solid #52c41a' }}>
          <Statistic
            title="Gas"
            valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
            prefix={<ThunderboltOutlined />}
            value={totalOnchainGas}
          />
        </Card>
      </Col>
    </Row>
  );
};

export default StatisticsCard;
