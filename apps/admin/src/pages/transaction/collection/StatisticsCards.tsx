import { FC } from 'react';
import { Card, Col, ColProps, Row, Statistic } from 'antd';
import numeral from 'numeral';
import type { TransactionMeta, TransactionStat } from '@morgan-ustd/shared';

interface StatisticsCardsProps {
  meta: TransactionMeta | undefined;
  stat: TransactionStat | undefined;
  t: (key: string) => string;
}

const colProps: ColProps = {
  xs: 24,
  sm: 24,
  md: 12,
  lg: 4,
};

export const StatisticsCards: FC<StatisticsCardsProps> = ({ meta, stat, t }) => {
  const successRate = stat && meta?.total
    ? `${numeral(((stat?.total_success || 0) * 100) / meta?.total).format('0.00')}`
    : '0';

  return (
    <Row gutter={[16, 16]}>
      <Col {...colProps}>
        <Card className="border-[#ff4d4f] border-[2.5px]">
          <Statistic
            value={meta?.total}
            title={t('statistics.transactionCount')}
            valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
          />
        </Card>
      </Col>
      <Col {...colProps}>
        <Card className="border-[#7fd1b9] border-[2.5px]">
          <Statistic
            valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
            value={`${stat?.total_success ?? 0}/${meta?.total || 0}`}
            title={`${t('statistics.successRate')} ${successRate}%`}
          />
        </Card>
      </Col>
      <Col {...colProps}>
        <Card className="border-[#3f7cac] border-[2.5px]">
          <Statistic
            value={stat?.total_amount}
            title={t('statistics.transactionAmount')}
            valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
          />
        </Card>
      </Col>
      <Col {...colProps}>
        <Card className="border-[#f7b801] border-[2.5px]">
          <Statistic
            value={stat?.total_profit}
            title={t('statistics.transactionProfit')}
            valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
          />
        </Card>
      </Col>
      <Col {...colProps}>
        <Card className="border-[#f7b801] border-[2.5px]">
          <Statistic
            value={stat?.third_channel_fee}
            title={t('statistics.thirdPartyFee')}
            valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
          />
        </Card>
      </Col>
    </Row>
  );
};

export default StatisticsCards;
