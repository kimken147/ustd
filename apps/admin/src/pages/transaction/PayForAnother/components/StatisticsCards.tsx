import { Card, Col, ColProps, Row, Statistic } from 'antd';
import { useTranslation } from 'react-i18next';
import { WithdrawMeta } from '@morgan-ustd/shared';

export interface StatisticsCardsProps {
  meta: WithdrawMeta | undefined;
}

const colProps: ColProps = { xs: 24, sm: 24, md: 6 };
const valueStyle = { fontStyle: 'italic' as const, fontWeight: 'bold' as const };

export function StatisticsCards({ meta }: StatisticsCardsProps) {
  const { t } = useTranslation('transaction');

  return (
    <Row gutter={[16, 16]}>
      <Col {...colProps}>
        <Card className="border-[#ff4d4f] border-[2.5px]">
          <Statistic
            value={meta?.total}
            title={t('statistics.paymentCount')}
            valueStyle={valueStyle}
          />
        </Card>
      </Col>
      <Col {...colProps}>
        <Card className="border-[#3f7cac] border-[2.5px]">
          <Statistic
            value={meta?.total_amount}
            title={t('statistics.paymentAmount')}
            valueStyle={valueStyle}
          />
        </Card>
      </Col>
      <Col {...colProps}>
        <Card className="border-[#f7b801] border-[2.5px]">
          <Statistic
            value={meta?.total_profit}
            title={t('statistics.paymentProfit')}
            valueStyle={valueStyle}
          />
        </Card>
      </Col>
      <Col {...colProps}>
        <Card className="border-[#f7b801] border-[2.5px]">
          <Statistic
            value={meta?.third_channel_fee}
            title={t('statistics.thirdPartyFee')}
            valueStyle={valueStyle}
          />
        </Card>
      </Col>
    </Row>
  );
}

export default StatisticsCards;
