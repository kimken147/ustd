import { FC } from 'react';
import { DollarCircleOutlined } from '@ant-design/icons';
import { Card, Col, Row, Statistic } from 'antd';
import { Yellow } from '@morgan-ustd/shared';

interface StatisticsCardProps {
  totalBalance?: number;
  label: string;
}

const StatisticsCard: FC<StatisticsCardProps> = ({ totalBalance, label }) => {
  return (
    <Row>
      <Col xs={24} md={12} lg={6}>
        <Card
          style={{
            border: `2.5px solid ${Yellow}`,
          }}
        >
          <Statistic
            title={label}
            value={totalBalance}
            valueStyle={{ fontStyle: 'italic', fontWeight: 'bold' }}
            prefix={<DollarCircleOutlined />}
          />
        </Card>
      </Col>
    </Row>
  );
};

export default StatisticsCard;
